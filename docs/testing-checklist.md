# Testing Checklist

Results recorded on **2026-11-25**.

**Environment:** PHP 8.2.12 (CLI and built-in web server), MariaDB 10.4.32 in
an isolated instance on port 3307 with a temporary data directory. No
production or personal database was used.

**How to re-run:** see the header of `tests/http-functional-test.sh`.

## Automated HTTP functional tests: 84 passed, 0 failed

| Area | Checks | Result |
|---|---|---|
| Booking page | page loads, 7 courts listed, slots read from the database, no price input, booked slots disabled, invalid / past dates fall back to today, CSP header, no inline styles or handlers | ✅ |
| Reservation creation | valid booking (303 redirect + confirmation), **price stored as 3000.00 although the browser sent `price=1`**, double booking rejected, past date, missing name, short phone, unknown court, impossible date, unknown slot rejected, GET → 405, missing CSRF token → 400 | ✅ |
| XSS | `<script>` in a customer name is stored as text and escaped on the public and admin pages | ✅ |
| Admin authentication | every admin page and action redirects when signed out; wrong password and unknown user show the same message; correct login; **session id changes on login**; cookie `HttpOnly; SameSite=Lax`; `Cache-Control: no-store` | ✅ |
| Dashboard | today / total counts, most booked court, paid and unpaid income match SQL computed independently; next-5 table | ✅ |
| Reservation management | filters by date, name, phone, paid, unpaid; `%` searched literally; SQL injection string harmless; mark paid / unpaid; redirect keeps filters; open redirect blocked; delete; GET → 405; missing CSRF → 400; injected id ignored | ✅ |
| Daily schedule | one row per slot; paid, unpaid and available cell counts match the database; invalid date handled | ✅ |
| Payments | totals match the database; status tabs; no delete button (as in the original) | ✅ |
| Time slots | overlap rejected, end-before-start rejected, 90-minute slot added and priced at 4,500.00 TL, new slot bookable, slot with reservations cannot be deleted, unused slot deleted | ✅ |
| Logout | GET → 405, POST logs out, dashboard inaccessible afterwards | ✅ |
| File exposure | `.env`, `src/`, `database/` not reachable through the web server; removed `success.php` returns 404 | ✅ |

## Additional manual checks

| Check | Result |
|---|---|
| `php -l` on every PHP file | ✅ no syntax errors |
| 20 concurrent booking requests for the same court, date and slot | ✅ exactly 1 reservation stored |
| `schema.sql` + `seed.sql` import into an empty database | ✅ |
| Constraints reject duplicate slot, missing customer (FK), negative price, end-before-start slot, deleting a slot in use | ✅ |
| Migration 000 → 001 on the original dump (without `price`/`is_paid`) | ✅ all rows preserved and converted |
| Migration 000 → 001 on the dump with `price`/`is_paid` added | ✅ |
| Migration 002 after 001: resulting columns identical to a fresh `schema.sql` install | ✅ |
| Application on the migrated legacy database (all admin pages, booking) | ✅ |
| `create-admin.php`: creates hashed account, refuses duplicates, rejects short passwords | ✅ |
| URL generation when served from an XAMPP subfolder (`/tennis-court-reservation-system/public/`) | ✅ (unit-level check of `url()` / `asset_url()`) |
| PHP error log empty after the full test run | ✅ |

## Not yet tested

| Check | Reason |
|---|---|
| Running under XAMPP Apache (including the `.htaccess` deny rules) | Apache was not started in the isolated environment; only the PHP built-in server was used |
| Visual check in real browsers and on mobile widths | No browser automation yet; planned for the UI phase |
| HTTPS (`Secure` cookie flag) | Local HTTP only |
