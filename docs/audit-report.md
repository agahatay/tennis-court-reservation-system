# Source Code Audit (before refactoring)

Audit of the original application (December 2025 version, preserved in the
first commit of this repository) carried out before any changes were made.
Each finding links to the change that resolved it.

## Scope

- 12 PHP/CSS files of the latest version, plus two older local copies of the
  code (an earlier snapshot and a copy in a local web-server folder) that were
  found to diverge from it
- The phpMyAdmin dump of the `tennisdb` database
- The features and security measures listed in the original project documentation

## Feature verification

| Feature | Original state | Resolution |
|---|---|---|
| Court / date selection, available slots | Implemented; slot list hardcoded twice and the `time_slots` table ignored | Slots are read from the database everywhere |
| Reservation creation | Implemented; the form asked the **customer** for a price that the server then ignored (fixed 3000) | Price field removed; price calculated on the server from `HOURLY_RATE` and the slot length |
| Conflict prevention | Check-then-insert without a constraint (race condition) | `UNIQUE (court_id, reservation_date, time_slot_id)` + duplicate-key handling; verified with 20 concurrent requests |
| Dashboard: counts, top court, next 5 | Implemented | Kept; "next 5" now excludes reservations that have already finished |
| Dashboard: paid / unpaid income | **Missing** (only on the payments page) | Added |
| Reservation filters | Date and name/phone; payment status only as "unpaid only" | Date, name/phone and All/Paid/Unpaid |
| Mark paid / unpaid, delete | Implemented via GET links | POST forms with CSRF tokens and confirmation dialogs |
| Daily schedule | **Missing**: linked from three pages, file did not exist | Implemented (courts × time slots grid, colour-coded) |
| Payment management | Implemented | Kept, sharing code with the reservations page |
| Time-slot management | Existed but was unreachable and unused | Linked in the navigation and used by the booking page |
| Foreign keys | Listed in the documentation, **not present** in the database | Added |

## Security findings

| # | Severity | Finding | Resolution |
|---|---|---|---|
| S1 | Critical | Admin password hardcoded in plain text (`$password == "..."`), single shared secret, no session regeneration, unlimited login attempts | `admins` table with `password_hash()` / `password_verify()`, CLI account creation, `session_regenerate_id()` on login, idle timeout, login rate limiting (5 failures per username and 20 per IP in 15 minutes) |
| S2 | High | SQL injection in the delete endpoint (`$_GET['id']` concatenated into SQL) | All queries use prepared statements via `src/database.php` |
| S3 | High | No CSRF protection; delete / payment / slot changes were GET links | POST-only actions with per-session CSRF tokens; logout is POST too |
| S4 | High | Public `success.php?id=N` showed any customer's name and phone (enumerable) | Page removed; confirmation is a session flash message shown only to the person who booked |
| S5 | Medium | `display_errors` enabled and `die(mysqli_error())` on the payments page | Errors are logged to `storage/logs/`; visitors see a generic page (details only when `APP_ENV=development`) |
| S6 | Medium | Database credentials hardcoded in a file inside the web root | `.env` outside the public web root; `public/` is the only served folder |
| S7 | Low | Inconsistent use of prepared statements (escaping + string concatenation elsewhere) | Single data-access layer; LIKE wildcards escaped |
| S8 | Low | No server-side check for past dates / started slots; impossible dates (e.g. 02-31) accepted by regex | Strict date parsing and time checks in `book_reservation()` |
| S9 | Info | Output escaping was already applied consistently | Kept and centralised in `e()`; added a Content-Security-Policy |

The older copies of the code contained SQL injection on public pages and are
not part of this repository.

## Database findings

- The dump did not match the code: `reservations` had no `price` / `is_paid`
  columns, so a fresh install from it could not work.
- No foreign keys, every column nullable, no unique constraints.
- Duplicate customers exist for the same phone number, including numbers that
  differ only by formatting (with and without a leading 0). Resolution: phone
  numbers are now normalized to E.164 (`phone_normalized`), and a booking
  reuses a customer only when the normalized number **and** the name match.
  Records are not merged automatically and phone numbers are not unique,
  because different people can share a number. `000-preflight-checks.sql`
  lists existing duplicates for manual review.
- The dump contained real-looking personal data, so it is not included in the
  repository. `database/seed.sql` uses fictional data.

## Code-quality findings

- Admin authentication check duplicated on every page → `require_admin()`
- Toggle-paid logic duplicated on two pages → one action endpoint
- SQL, business logic and HTML mixed in each file → repositories, a booking
  service and templates
- A separate `<style>` block per page, conflicting global CSS, no viewport
  meta tag → one stylesheet, responsive layout
- Inline `onclick` / `onchange` handlers → `public/assets/js/app.js`
- Debug leftovers (version badge, "turn off later" comments) → removed
- Inconsistent file names (`admin_dashboard.php`, `list.php`, `time_slots_admin.php`) → consistent kebab-case names
