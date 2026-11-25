#!/usr/bin/env bash
# =============================================================================
# HTTP functional test suite
# =============================================================================
# Drives the running application with curl and cross-checks the results
# against the database. Destructive: it creates and deletes reservations.
#
# Run ONLY against a disposable test database loaded from database/schema.sql
# and database/seed.sql, with an admin created by scripts/create-admin.php:
#
#   BASE_URL=http://127.0.0.1:8080 \
#   MYSQL="mysql -h127.0.0.1 -P3307 -uroot -pYOUR_TEST_PASSWORD" \
#   DB_NAME=tennis_court_reservation \
#   ADMIN_USER=testadmin ADMIN_PASSWORD='...' \
#   bash tests/http-functional-test.sh
#
# Requires bash, curl and GNU date (Git Bash on Windows works).
# =============================================================================
set -u
B=${BASE_URL:-http://127.0.0.1:8080}
: "${MYSQL:?Set MYSQL to the mysql client command for the test database}"
: "${ADMIN_PASSWORD:?Set ADMIN_PASSWORD for the test admin}"
ADMIN_USER=${ADMIN_USER:-testadmin}
DB_NAME=${DB_NAME:-tennis_court_reservation}
case "$DB_NAME" in *test*|tennis_court_reservation) ;; *) echo "Refusing to run against database '$DB_NAME'"; exit 2;; esac
M="$MYSQL $DB_NAME -N -e"
PASS=0; FAIL=0
ok()   { PASS=$((PASS+1)); echo "PASS  $1"; }
bad()  { FAIL=$((FAIL+1)); echo "FAIL  $1  ($2)"; }
check(){ if [ "$2" = "$3" ]; then ok "$1"; else bad "$1" "expected [$3] got [$2]"; fi; }
contains(){ if printf '%s' "$2" | grep -q -- "$3"; then ok "$1"; else bad "$1" "missing [$3]"; fi; }
lacks(){ if printf '%s' "$2" | grep -q -- "$3"; then bad "$1" "unexpected [$3]"; else ok "$1"; fi; }
token(){ grep -o 'name="csrf_token" value="[0-9a-f]*"' | head -1 | sed 's/.*value="//;s/"//'; }
code(){ curl -s -o /dev/null -w '%{http_code}' "$@"; }
loc(){ curl -s -o /dev/null -w '%{redirect_url}' "$@"; }
blocked(){ local c; c=$(code "$2"); if [ "$c" = 403 ] || [ "$c" = 404 ]; then ok "$1"; else bad "$1" "expected 403/404 got [$c]"; fi; }

PJ=$(mktemp); AJ=$(mktemp)   # public / admin cookie jars
TOMORROW=$(date -d tomorrow +%F); YESTERDAY=$(date -d yesterday +%F)
$M "DELETE FROM login_attempts"   # start without rate-limit history from earlier runs

echo "== Public booking page"
H=$(curl -s -c $PJ -b $PJ "$B/?court_id=2&date=$TOMORROW")
check "index returns 200" "$(code "$B/")" 200
check "court options rendered (7 courts)" "$(printf '%s' "$H" | grep -c 'option-title">Court')" 7
check "7 date shortcuts rendered" "$(printf '%s' "$H" | grep -c 'option option-compact')" 7
contains "selected court marked" "$H" 'aria-current="true"'
contains "free hours shown per court" "$H" 'hours free'
contains "slots rendered from DB" "$H" '08:00 – 09:00'
lacks "no customer-editable price field" "$H" 'name="price"'
contains "CSP header sent" "$(curl -s -D - -o /dev/null $B/)" "Content-Security-Policy"
lacks "no inline style attributes" "$H" ' style="'
lacks "no inline event handlers" "$H" ' onclick='
contains "invalid date falls back to today" "$(curl -s "$B/?date=2025-02-31")" "value=\"$(date +%F)\""
contains "past date falls back to today" "$(curl -s "$B/?date=$YESTERDAY")" "value=\"$(date +%F)\""
contains "seeded booked slot shown as Booked (court 1 tomorrow 09:00)" "$(curl -s "$B/?court_id=1&date=$TOMORROW")" 'slot slot-booked'

echo "== Reservation creation"
T=$(printf '%s' "$H" | token)
SLOT7=$($M "SELECT time_slot_id FROM time_slots WHERE start_time='14:00:00'")
check "GET reserve.php is rejected (405)" "$(code "$B/reserve.php")" 405
check "POST without CSRF token rejected (400)" "$(code -b $PJ -d "court_id=2&reservation_date=$TOMORROW&time_slot_id=$SLOT7&name=Test&phone=0500 000 00 99" "$B/reserve.php")" 400
R=$(curl -s -b $PJ -c $PJ -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "csrf_token=$T" -d "court_id=2&reservation_date=$TOMORROW&time_slot_id=$SLOT7&price=1" --data-urlencode "name=Test Customer" --data-urlencode "phone=0500 000 00 99" "$B/reserve.php")
check "valid booking redirects 303" "${R%% *}" 303
F=$(curl -s -b $PJ -c $PJ "${R#* }")
contains "success flash shown" "$F" 'confirmed: Court 2'
check "price calculated on server (browser sent price=1)" "$($M "SELECT price FROM reservations r JOIN customers c USING(customer_id) WHERE c.phone='0500 000 00 99'")" "3000.00"
contains "booked slot now disabled" "$(curl -s "$B/?court_id=2&date=$TOMORROW")" 'slot slot-booked'
T=$(printf '%s' "$F" | token)
R=$(curl -s -b $PJ -c $PJ -o /dev/null -w '%{redirect_url}' --data-urlencode "csrf_token=$T" -d "court_id=2&reservation_date=$TOMORROW&time_slot_id=$SLOT7" --data-urlencode "name=Someone Else" --data-urlencode "phone=0500 000 00 98" "$B/reserve.php")
F=$(curl -s -b $PJ -c $PJ "$R")
contains "double booking rejected" "$F" 'just been booked'
check "still exactly one booking in that slot" "$($M "SELECT COUNT(*) FROM reservations WHERE court_id=2 AND reservation_date='$TOMORROW' AND time_slot_id=$SLOT7")" 1
T=$(printf '%s' "$F" | token)
F=$(curl -s -L -b $PJ -c $PJ --data-urlencode "csrf_token=$T" -d "court_id=2&reservation_date=$YESTERDAY&time_slot_id=$SLOT7&name=X&phone=0500 000 00 97" "$B/reserve.php")
contains "past date rejected" "$F" 'past dates'
T=$(printf '%s' "$F" | token)
F=$(curl -s -L -b $PJ -c $PJ --data-urlencode "csrf_token=$T" -d "court_id=2&reservation_date=$TOMORROW&time_slot_id=$((SLOT7+1))&name=&phone=123" "$B/reserve.php")
contains "missing name rejected" "$F" 'Please enter your name'
contains "short phone rejected" "$F" 'valid phone number'
T=$(printf '%s' "$F" | token)
F=$(curl -s -L -b $PJ -c $PJ --data-urlencode "csrf_token=$T" -d "court_id=999&reservation_date=2026-02-30&time_slot_id=999&name=X&phone=0500 000 00 96" "$B/reserve.php")
contains "unknown court rejected" "$F" 'valid court'
contains "impossible date rejected" "$F" 'valid date'
contains "unknown slot rejected" "$F" 'available time slot'
T=$(printf '%s' "$F" | token)
F=$(curl -s -L -b $PJ -c $PJ --data-urlencode "csrf_token=$T" -d "court_id=3&reservation_date=$TOMORROW&time_slot_id=$((SLOT7+2))" --data-urlencode 'name=<script>alert(1)</script>' -d "phone=0500 000 00 95" "$B/reserve.php")
contains "XSS payload stored but escaped in flash/page" "$F" 'confirmed'
lacks "no raw <script> echoed" "$F" '<script>alert'

echo "== Admin authentication"
check "dashboard redirects when signed out" "$(loc "$B/admin/dashboard.php")" "$B/admin/login.php"
for p in reservations schedule payments time-slots; do check "$p.php requires login" "$(code "$B/admin/$p.php")" 303; done
check "action endpoint requires login" "$(code -X POST "$B/admin/actions/delete-reservation.php")" 303
L=$(curl -s -c $AJ -b $AJ "$B/admin/login.php"); T=$(printf '%s' "$L" | token)
OLDSID=$(grep tcrs_session $AJ | awk '{print $7}')
F=$(curl -s -L -c $AJ -b $AJ --data-urlencode "csrf_token=$T" -d "username=$ADMIN_USER&password=wrong-password" "$B/admin/login.php")
contains "wrong password rejected" "$F" 'Invalid username or password'
T=$(printf '%s' "$F" | token)
F=$(curl -s -L -c $AJ -b $AJ --data-urlencode "csrf_token=$T" -d "username=nobody-$RANDOM&password=$ADMIN_PASSWORD" "$B/admin/login.php")
contains "unknown user gets same message" "$F" 'Invalid username or password'
T=$(printf '%s' "$F" | token)
R=$(curl -s -c $AJ -b $AJ -o /dev/null -w '%{redirect_url}' --data-urlencode "csrf_token=$T" -d "username=$ADMIN_USER&password=$ADMIN_PASSWORD" "$B/admin/login.php")
check "correct login redirects to dashboard" "$R" "$B/admin/dashboard.php"
NEWSID=$(grep tcrs_session $AJ | awk '{print $7}')
if [ "$OLDSID" != "$NEWSID" ]; then ok "session id regenerated on login"; else bad "session id regenerated on login" "same id"; fi
contains "session cookie is HttpOnly + SameSite=Lax" "$(curl -s -D - -o /dev/null "$B/admin/login.php")" 'HttpOnly; SameSite=Lax'
contains "admin pages are not cacheable" "$(curl -s -D - -o /dev/null -b $AJ "$B/admin/dashboard.php")" 'no-store'

echo "== Dashboard"
D=$(curl -s -b $AJ "$B/admin/dashboard.php")
check "today count matches DB" "$(printf '%s' "$D" | grep -A1 'Reservations today' | sed -n 2p | grep -o '[0-9]\+' | head -1)" "$($M "SELECT COUNT(*) FROM reservations WHERE reservation_date=CURDATE()")"
check "total count matches DB" "$(printf '%s' "$D" | grep -A1 'Total reservations' | sed -n 2p | grep -o '[0-9]\+' | head -1)" "$($M "SELECT COUNT(*) FROM reservations")"
check "most booked court matches DB" "$(printf '%s' "$D" | grep -A1 'Most booked court' | sed -n 2p | sed 's/<[^>]*>//g;s/^ *//')" "$($M "SELECT c.court_name FROM reservations r JOIN courts c USING(court_id) GROUP BY court_id ORDER BY COUNT(*) DESC, court_id LIMIT 1")"
check "paid income matches DB" "$(printf '%s' "$D" | grep -A1 'Paid income' | sed -n 2p | sed 's/<[^>]*>//g;s/^ *//')" "$($M "SELECT CONCAT(FORMAT(COALESCE(SUM(price),0),2),' TL') FROM reservations WHERE is_paid=1")"
check "unpaid income matches DB" "$(printf '%s' "$D" | grep -A1 'Unpaid income' | sed -n 2p | sed 's/<[^>]*>//g;s/^ *//')" "$($M "SELECT CONCAT(FORMAT(COALESCE(SUM(price),0),2),' TL') FROM reservations WHERE is_paid=0")"
check "next-5 table has 5 rows" "$(printf '%s' "$D" | grep -c 'data-label="Date"')" 5

echo "== Reservation management"
check "unfiltered list shows all rows" "$(curl -s -b $AJ "$B/admin/reservations.php" | grep -c 'data-label="Date"')" "$($M "SELECT COUNT(*) FROM reservations")"
check "filter by date" "$(curl -s -b $AJ "$B/admin/reservations.php?date=$TOMORROW" | grep -c 'data-label="Date"')" "$($M "SELECT COUNT(*) FROM reservations WHERE reservation_date='$TOMORROW'")"
check "filter by name" "$(curl -s -b $AJ "$B/admin/reservations.php?search=Three" | grep -c 'data-label="Date"')" "$($M "SELECT COUNT(*) FROM reservations JOIN customers USING(customer_id) WHERE name LIKE '%Three%'")"
check "filter by phone" "$(curl -s -b $AJ "$B/admin/reservations.php?search=00%2002" | grep -c 'data-label="Date"')" "$($M "SELECT COUNT(*) FROM reservations JOIN customers USING(customer_id) WHERE phone LIKE '%00 02%'")"
check "filter unpaid" "$(curl -s -b $AJ "$B/admin/reservations.php?payment=unpaid" | grep -c 'data-label="Date"')" "$($M "SELECT COUNT(*) FROM reservations WHERE is_paid=0")"
check "filter paid" "$(curl -s -b $AJ "$B/admin/reservations.php?payment=paid" | grep -c 'data-label="Date"')" "$($M "SELECT COUNT(*) FROM reservations WHERE is_paid=1")"
check "LIKE wildcard is literal ('%' matches nothing)" "$(curl -s -b $AJ "$B/admin/reservations.php?search=%25" | grep -c 'data-label="Date"')" 0
check "SQL injection attempt in search is harmless" "$(code -b $AJ "$B/admin/reservations.php?search=%27%20OR%201%3D1%20--%20")" 200
contains "XSS name escaped in admin list" "$(curl -s -b $AJ "$B/admin/reservations.php")" '&lt;script&gt;alert(1)&lt;/script&gt;'
RID=$($M "SELECT reservation_id FROM reservations r JOIN customers c USING(customer_id) WHERE c.phone='0500 000 00 99'")
P=$(curl -s -b $AJ "$B/admin/reservations.php"); T=$(printf '%s' "$P" | token)
check "GET on payment action rejected (405)" "$(code -b $AJ "$B/admin/actions/update-payment-status.php?reservation_id=$RID&is_paid=1")" 405
check "payment action without CSRF rejected (400)" "$(code -b $AJ -d "reservation_id=$RID&is_paid=1" "$B/admin/actions/update-payment-status.php")" 400
R=$(loc -b $AJ --data-urlencode "csrf_token=$T" -d "reservation_id=$RID&is_paid=1&return_to=reservations.php%3Fpayment%3Dunpaid" "$B/admin/actions/update-payment-status.php")
check "mark paid redirects back with filters" "$R" "$B/admin/reservations.php?payment=unpaid"
check "reservation marked paid in DB" "$($M "SELECT is_paid FROM reservations WHERE reservation_id=$RID")" 1
curl -s -o /dev/null -b $AJ --data-urlencode "csrf_token=$T" -d "reservation_id=$RID&is_paid=0" "$B/admin/actions/update-payment-status.php"
check "mark unpaid works" "$($M "SELECT is_paid FROM reservations WHERE reservation_id=$RID")" 0
check "open redirect blocked" "$(loc -b $AJ --data-urlencode "csrf_token=$T" -d "reservation_id=$RID&is_paid=0&return_to=https://evil.example" "$B/admin/actions/update-payment-status.php")" "$B/admin/reservations.php"
check "GET delete rejected (405)" "$(code -b $AJ "$B/admin/actions/delete-reservation.php?reservation_id=$RID")" 405
curl -s -o /dev/null -b $AJ --data-urlencode "csrf_token=$T" -d "reservation_id=$RID" "$B/admin/actions/delete-reservation.php"
check "reservation deleted" "$($M "SELECT COUNT(*) FROM reservations WHERE reservation_id=$RID")" 0
check "injection in delete id is ignored" "$(curl -s -L -b $AJ --data-urlencode "csrf_token=$T" -d "reservation_id=1 OR 1=1" "$B/admin/actions/delete-reservation.php" | grep -c 'no longer exists')" 1

echo "== Daily schedule"
S=$(curl -s -b $AJ "$B/admin/schedule.php?date=$TOMORROW")
check "schedule grid has one row per slot" "$(printf '%s' "$S" | grep -c '<th scope="row">')" "$($M "SELECT COUNT(*) FROM time_slots")"
check "paid cells match DB" "$(printf '%s' "$S" | grep -c 'class="cell-paid"')" "$($M "SELECT COUNT(*) FROM reservations WHERE reservation_date='$TOMORROW' AND is_paid=1")"
check "unpaid cells match DB" "$(printf '%s' "$S" | grep -c 'class="cell-unpaid"')" "$($M "SELECT COUNT(*) FROM reservations WHERE reservation_date='$TOMORROW' AND is_paid=0")"
check "available cells = courts*slots - booked" "$(printf '%s' "$S" | grep -c 'class="cell-available"')" "$($M "SELECT (SELECT COUNT(*) FROM courts)*(SELECT COUNT(*) FROM time_slots) - (SELECT COUNT(*) FROM reservations WHERE reservation_date='$TOMORROW')")"
check "invalid schedule date falls back (200)" "$(code -b $AJ "$B/admin/schedule.php?date=garbage")" 200

echo "== Payments"
Y=$(curl -s -b $AJ "$B/admin/payments.php")
contains "payments total matches DB" "$Y" "$($M "SELECT CONCAT(FORMAT(SUM(price),2),' TL') FROM reservations")"
check "payments 'unpaid' tab rows" "$(curl -s -b $AJ "$B/admin/payments.php?status=unpaid" | grep -c 'data-label="Date"')" "$($M "SELECT COUNT(*) FROM reservations WHERE is_paid=0")"
lacks "payments page has no delete button (as original)" "$Y" 'delete-reservation.php'

echo "== Time slots"
P=$(curl -s -b $AJ "$B/admin/time-slots.php"); T=$(printf '%s' "$P" | token)
F=$(curl -s -L -b $AJ -c $AJ --data-urlencode "csrf_token=$T" -d "start_time=20:30&end_time=21:30" "$B/admin/actions/create-time-slot.php")
contains "overlapping slot rejected" "$F" 'overlaps'
F=$(curl -s -L -b $AJ -c $AJ --data-urlencode "csrf_token=$T" -d "start_time=22:00&end_time=21:00" "$B/admin/actions/create-time-slot.php")
contains "end before start rejected" "$F" 'must be after'
F=$(curl -s -L -b $AJ -c $AJ --data-urlencode "csrf_token=$T" -d "start_time=21:00&end_time=22:30" "$B/admin/actions/create-time-slot.php")
contains "new 90-minute slot added" "$F" '21:00 – 22:30 added'
contains "90-minute slot priced at 1.5 x hourly rate" "$F" '4,500.00 TL'
contains "new slot appears on booking page" "$(curl -s "$B/?date=$TOMORROW")" '21:00 – 22:30'
NEWSLOT=$($M "SELECT time_slot_id FROM time_slots WHERE start_time='21:00:00'")
USEDSLOT=$($M "SELECT time_slot_id FROM reservations LIMIT 1")
F=$(curl -s -L -b $AJ -c $AJ --data-urlencode "csrf_token=$T" -d "time_slot_id=$USEDSLOT" "$B/admin/actions/delete-time-slot.php")
contains "slot with reservations cannot be deleted" "$F" 'cannot be deleted'
F=$(curl -s -L -b $AJ -c $AJ --data-urlencode "csrf_token=$T" -d "time_slot_id=$NEWSLOT" "$B/admin/actions/delete-time-slot.php")
contains "unused slot deleted" "$F" 'was deleted'

echo "== Phone normalization and customer matching"
# book <court> <date> <slot id> <name> <phone>: prints the confirmation / error page
book(){ local t; t=$(curl -s -c $PJ -b $PJ "$B/" | token)
  curl -s -L -c $PJ -b $PJ --data-urlencode "csrf_token=$t" -d "court_id=$1&reservation_date=$2&time_slot_id=$3" \
       --data-urlencode "name=$4" --data-urlencode "phone=$5" "$B/reserve.php"; }
D5=$(date -d '+5 day' +%F)
CUSTOMERS_BEFORE=$($M "SELECT COUNT(*) FROM customers")
contains "booking with +90 format of an existing number" "$(book 3 $D5 1 'Demo Customer One' '+90 500 000 00 01')" 'confirmed'
check "same name + equivalent phone reuses the customer" "$($M "SELECT customer_id FROM reservations WHERE court_id=3 AND reservation_date='$D5' AND time_slot_id=1")" 1
contains "booking with national format without leading 0" "$(book 3 $D5 2 'demo customer one' '500 000 00 01')" 'confirmed'
check "name match ignores letter case" "$($M "SELECT customer_id FROM reservations WHERE court_id=3 AND reservation_date='$D5' AND time_slot_id=2")" 1
check "no customer records created so far" "$($M "SELECT COUNT(*) FROM customers")" "$CUSTOMERS_BEFORE"
contains "different person sharing the phone can book" "$(book 3 $D5 3 'Shared Phone Person' '0500-000-00-01')" 'confirmed'
check "different name on same phone creates a separate customer" "$($M "SELECT COUNT(*) FROM customers")" "$((CUSTOMERS_BEFORE + 1))"
check "new customer stored with normalized phone" "$($M "SELECT phone_normalized FROM customers WHERE name='Shared Phone Person'")" '+905000000001'
check "phone kept as entered for display" "$($M "SELECT phone FROM customers WHERE name='Shared Phone Person'")" '0500-000-00-01'
check "existing records were not merged" "$($M "SELECT COUNT(*) FROM customers WHERE phone_normalized='+905000000001'")" 2
contains "incomplete Turkish number rejected" "$(book 3 $D5 4 'Someone' '0532 123 45 6')" 'valid phone number'
check "admin search by phone in another format" "$(curl -s -b $AJ "$B/admin/reservations.php?search=%2B90%20500%20000%2000%2001" | grep -c 'data-label="Date"')" "$($M "SELECT COUNT(*) FROM reservations JOIN customers USING(customer_id) WHERE phone_normalized='+905000000001'")"

echo "== Logout"
T=$(curl -s -b $AJ "$B/admin/dashboard.php" | token)
check "GET logout rejected (405)" "$(code -b $AJ "$B/admin/logout.php")" 405
check "logout redirects to login" "$(loc -b $AJ -c $AJ --data-urlencode "csrf_token=$T" "$B/admin/logout.php")" "$B/admin/login.php?signed_out=1"
check "after logout, dashboard redirects" "$(code -b $AJ "$B/admin/dashboard.php")" 303

echo "== Login rate limiting"
RJ=$(mktemp)
# login <user> <password>: prints "<status> <redirect>" for one attempt with a fresh token
login(){ local t; t=$(curl -s -c $RJ -b $RJ "$B/admin/login.php" | token)
  curl -s -c $RJ -b $RJ -o /dev/null -w '%{http_code} %{redirect_url}' --data-urlencode "csrf_token=$t" \
       --data-urlencode "username=$1" --data-urlencode "password=$2" "$B/admin/login.php"; }
$M "DELETE FROM login_attempts"
for i in 1 2 3 4 5; do login "$ADMIN_USER" "wrong-password-$i" >/dev/null; done
check "5 failures recorded" "$($M "SELECT COUNT(*) FROM login_attempts")" 5
check "usernames stored only as SHA-256 hashes" "$($M "SELECT COUNT(*) FROM login_attempts WHERE username_hash = SHA2(LOWER('$ADMIN_USER'), 256)")" 5
R=$(login "$ADMIN_USER" "$ADMIN_PASSWORD")
check "6th attempt refused with 429 even with the correct password" "${R%% *}" 429
T=$(curl -s -c $RJ -b $RJ "$B/admin/login.php" | token)
contains "lockout message shown" "$(curl -s -c $RJ -b $RJ --data-urlencode "csrf_token=$T" --data-urlencode "username=$ADMIN_USER" -d "password=x" "$B/admin/login.php")" 'Too many failed sign-in attempts'
check "refused attempts are not counted again" "$($M "SELECT COUNT(*) FROM login_attempts")" 5
check "still locked out of the dashboard" "$(code -b $RJ "$B/admin/dashboard.php")" 303
R=$(login "other-user-$RANDOM" "whatever-password")
check "other usernames from the same IP are not locked by the per-user limit" "${R%% *}" 303
$M "UPDATE login_attempts SET attempted_at = attempted_at - INTERVAL 16 MINUTE"
R=$(login "$ADMIN_USER" "$ADMIN_PASSWORD")
check "lock expires after the 15-minute window" "${R#* }" "$B/admin/dashboard.php"
check "successful login clears that user's failures" "$($M "SELECT COUNT(*) FROM login_attempts WHERE username_hash = SHA2(LOWER('$ADMIN_USER'), 256)")" 0
$M "DELETE FROM login_attempts"
RJ=$(mktemp)   # fresh session: the previous step signed in, and signed-in users are redirected away from the login form
for i in $(seq 1 20); do login "probe-$i" "wrong" >/dev/null; done
check "per-IP: 20 failures across different usernames recorded" "$($M "SELECT COUNT(*) FROM login_attempts")" 20
R=$(login "fresh-user" "wrong")
check "per-IP limit: 21st failure in the window is refused (429)" "${R%% *}" 429
$M "DELETE FROM login_attempts"

echo "== Files outside public/ are not served"
for f in .env src/config.php database/schema.sql; do blocked "/../$f not reachable" "$B/../$f"; done
check "removed success.php is gone (PII leak)" "$(code "$B/success.php?id=1")" 404
# When the whole project folder is served (e.g. copied into XAMPP htdocs), set
# PROJECT_ROOT_URL to its URL to verify the .htaccess deny rules.
if [ -n "${PROJECT_ROOT_URL:-}" ]; then
  for f in "" .env .env.example .htaccess src/config.php database/schema.sql scripts/create-admin.php \
           storage/logs/ templates/header.php tests/unit-tests.php .git/config private/ docs/; do
    blocked "project root: /$f denied" "$PROJECT_ROOT_URL/$f"
  done
  for d in assets/ assets/css/ admin/actions/; do blocked "directory listing disabled: public/$d" "$B/$d"; done
fi

echo; echo "RESULT: $PASS passed, $FAIL failed"
[ "$FAIL" -eq 0 ]
