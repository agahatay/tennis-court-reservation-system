# Testing Checklist

Results recorded on **2026-11-25** against the final code.

**Environment** (local, isolated test environment; no production data):

| Component | Version |
|---|---|
| PHP | 8.2.12 (CLI, built-in server, Apache module) |
| Database | MariaDB 10.4.32, separate test instance and database |
| Web servers | PHP built-in server; XAMPP Apache 2.4.58 |
| Browsers | Google Chrome 157 and Microsoft Edge 158 (headless, driven by Puppeteer) |
| OS | Windows 11 |

## Summary

| Suite | Result |
|---|---|
| Unit tests (`tests/unit-tests.php`) | **60 passed, 0 failed** |
| HTTP functional tests on the PHP built-in server | **110 passed, 0 failed** |
| HTTP functional tests on XAMPP Apache, including `.htaccess` checks | **126 passed, 0 failed** |
| Browser checks, Chrome 157 (desktop 1440×900 and mobile 390×844) | **0 problems** |
| Browser checks, Edge 158 (desktop and mobile) | **0 problems** |
| PHP error log after all runs | empty |

## Unit tests (60)

| Area | What is checked |
|---|---|
| Phone normalization | 11 notations of the same Turkish number give `+905321234567`; landline and international numbers; 10 kinds of invalid input rejected |
| Phone search | partial numbers in any format become comparable digits; names are not treated as phone numbers |
| Names | whitespace normalization, empty name, 50 multibyte characters accepted, 51 rejected |
| Dates and times | strict date parsing (rejects `2026-02-30`, `2026-2-3`), time parsing, "slot has started" logic around the current time |
| Pricing | 60, 90 and 30-minute slots; zero-length slot rejected |
| Redirect whitelist | valid admin page accepted; external URLs, `//`, `../` and header injection rejected |
| Login throttling | username hashing is case-insensitive SHA-256 |

## HTTP functional tests (110 on the built-in server, 126 on Apache)

| Area | Checks | Result |
|---|---|---|
| Booking page | page loads, 7 courts and 7 date shortcuts, free hours per court, slots read from the database, no price input, invalid and past dates fall back to today, booked slots disabled, CSP header, no inline styles or handlers | ✅ |
| Reservation creation | valid booking; **price stored as 3000.00 although the browser sent `price=1`**; double booking rejected; past date, missing name, invalid phone, unknown court, impossible date and unknown slot rejected; GET → 405; missing CSRF token → 400 | ✅ |
| Phone numbers and customers | `+90 …` and `…` without leading 0 reuse the existing customer when the name matches (case-insensitive); a different name on the same number creates a separate customer; phone stored as entered plus normalized; no records merged; incomplete Turkish number rejected; admin search finds a customer by phone in another format | ✅ |
| XSS | `<script>` in a customer name is stored as text and escaped on the public and admin pages | ✅ |
| Admin authentication | all admin pages and actions redirect when signed out; same message for wrong password and unknown user; successful login; session id changes on login; `HttpOnly; SameSite=Lax` cookie; `Cache-Control: no-store` | ✅ |
| Login rate limiting | 5 failures recorded with hashed usernames; 6th attempt refused with **429** even with the correct password; lockout message; refused attempts not counted; other usernames not affected by the per-user limit; lock expires after 15 minutes; success clears failures; per-IP limit refuses the 21st failure | ✅ |
| Dashboard | today, total, most booked court, paid and unpaid income match independently computed SQL; next-5 table | ✅ |
| Reservation management | filters (date, name, phone, paid, unpaid); `%` searched literally; SQL injection string harmless; mark paid / unpaid; filters kept after an action; open redirect blocked; delete; GET → 405; missing CSRF → 400; injected id ignored | ✅ |
| Daily schedule | one row per slot; paid, unpaid and available cell counts match the database; invalid date handled | ✅ |
| Payments | totals match the database; status tabs; no delete button | ✅ |
| Time slots | overlap and end-before-start rejected; 90-minute slot priced at 4,500.00 TL and bookable; slot with reservations cannot be deleted; unused slot deleted | ✅ |
| Logout | GET → 405; POST logs out; dashboard inaccessible afterwards | ✅ |
| File exposure (built-in server) | `.env`, `src/`, `database/` not reachable; removed `success.php` → 404 | ✅ |
| **`.htaccess` under Apache** (project folder served as a whole) | project root, `.env`, `.env.example`, `.htaccess`, `src/`, `database/`, `scripts/`, `storage/logs/`, `templates/`, `tests/`, `.git/`, `private/`, `docs/` → **403**; directory listing disabled for `public/assets/`, `public/assets/css/`, `public/admin/actions/` | ✅ |

## Browser checks (Chrome 157, Edge 158)

Run against XAMPP Apache with the application in a sub-folder
(`/tcrs/public/`), at desktop (1440×900) and mobile (390×844) sizes.

| Check | Result |
|---|---|
| No JavaScript errors, console errors, CSP violations or failed requests on any page | ✅ |
| No horizontal page overflow at mobile width on any page (wide tables scroll inside their own container) | ✅ |
| Selecting a time slot updates the booking summary | ✅ |
| Server-side validation error shown with the user's input kept | ✅ |
| Confirmation dialog appears before changing a payment status, and cancelling it does not submit | ✅ |
| Link crawl: 61 pages visited, every link returned HTTP 200 | ✅ |
| All 9 form actions exist (3 GET filter forms, 6 POST handlers) | ✅ |
| Screenshots reviewed for layout at both sizes | ✅ (see `docs/screenshots/`) |

## Database and migration checks

| Check | Result |
|---|---|
| `schema.sql` + `seed.sql` import into an empty database | ✅ |
| Constraints reject duplicate slot, unknown customer, negative price, end-before-start slot, deleting a slot in use | ✅ |
| 20 concurrent booking requests for the same court, date and slot | ✅ exactly 1 reservation stored |
| Migrations 000 → 001 on the original dump, with and without `price` / `is_paid` columns | ✅ all rows preserved and converted |
| Migration 002 after 001: columns identical to a fresh `schema.sql` install | ✅ |
| Phone normalization in SQL (migration 001) and in PHP give identical results on 25 sample numbers | ✅ |
| Application on a migrated legacy database | ✅ |

## Not tested

| Item | Reason |
|---|---|
| HTTPS / `Secure` cookie flag | Only local HTTP was available |
| Any hosted deployment | The application has not been deployed |
| MySQL 8 | Only MariaDB 10.4 was used |
| Firefox and Safari | Not installed on the test machine |
| Real mobile devices | Mobile layouts were checked with Chrome and Edge device emulation only |
| Rate limiting behind a reverse proxy | The limiter uses the connection's IP address (see README) |
