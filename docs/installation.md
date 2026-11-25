# Installation and Configuration Guide

## 1. Requirements

- PHP 8.1 or newer with the `mysqli` (mysqlnd) and `mbstring` extensions
- MariaDB 10.4 or newer
- Apache 2.4 with `AllowOverride` enabled, or PHP's built-in server for development

[XAMPP](https://www.apachefriends.org/) includes all of these. The project was
tested with XAMPP's PHP 8.2.12, MariaDB 10.4.32 and Apache 2.4.58.

## 2. Database

Create an empty database, then load the schema and (optionally) the fictional
sample data. With XAMPP, use `C:\xampp\mysql\bin\mysql.exe` or phpMyAdmin's
*Import* tab.

```bash
mysql -u root -p -e "CREATE DATABASE tennis_court_reservation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql -u root -p tennis_court_reservation < database/schema.sql
mysql -u root -p tennis_court_reservation < database/seed.sql     # optional demo data
```

`schema.sql` only creates tables. It never drops existing ones, so importing it
into the wrong database fails instead of deleting data.

The sample data adds 7 courts, 13 one-hour slots from 08:00 to 21:00, and
fictional customers and reservations dated around the day of the import.

## 3. Configuration

Copy `.env.example` to `.env` in the project root (next to `README.md`, not
inside `public/`) and set the database connection:

```ini
APP_ENV=production
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=tennis_court_reservation
DB_USER=root
DB_PASSWORD=
HOURLY_RATE=3000
```

All available settings are listed in the README. Environment variables with
the same names take precedence over `.env`.

Make sure the web server can write to `storage/logs/`. Errors are logged there
and never shown to visitors unless `APP_ENV=development`.

## 4. Administrator account

There is no default administrator. Create one from the project folder:

```bash
php scripts/create-admin.php admin
```

You will be asked for a password of at least 10 characters. The password is
visible while you type it. To avoid that, provide it through the
`ADMIN_PASSWORD` environment variable instead. Only a `password_hash()` hash
is stored. To change a password later:

```bash
php scripts/create-admin.php admin --reset
```

With XAMPP on Windows, use `C:\xampp\php\php.exe` if `php` is not on your PATH.

## 5. Web server

### Option A: PHP built-in server (development)

```bash
php -S 127.0.0.1:8000 -t public
```

Booking page: `http://127.0.0.1:8000/` · Admin panel: `http://127.0.0.1:8000/admin/`

### Option B: XAMPP Apache, project folder inside `htdocs`

Copy the whole project folder to `C:\xampp\htdocs\tennis-court-reservation-system\`
and open:

- `http://localhost/tennis-court-reservation-system/public/`
- `http://localhost/tennis-court-reservation-system/public/admin/`

The `.htaccess` file in the project root denies access to everything except
`public/` (including `.env`, `src/`, `database/` and `storage/logs/`), and
`public/.htaccess` disables directory listings. This requires
`AllowOverride All`, which is XAMPP's default for `htdocs`. Links work in a
sub-folder without any extra configuration, because the application works out
its base URL automatically.

### Option C: Apache virtual host with `public/` as the document root (recommended)

```apache
<VirtualHost *:80>
    ServerName tennis.local
    DocumentRoot "C:/path/to/tennis-court-reservation-system/public"
    <Directory "C:/path/to/tennis-court-reservation-system/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

In this setup, configuration and source files are outside the document root
and cannot be requested at all.

### HTTPS

Serve the application over HTTPS in any real deployment. The session cookie
automatically gets the `Secure` flag when the request is HTTPS. HTTPS
deployment has **not** been tested as part of this project.

## 6. Upgrading a database from the original version

Databases created by the original version of the project (with a `time`
column in `reservations` and `slot_label` in `time_slots`) can be upgraded in
place. The scripts are MariaDB-specific.

1. **Back up the database**
   ```bash
   mysqldump -u root -p --routines tennisdb > tennisdb-backup.sql
   ```
2. **Run the read-only checks** and resolve every `[BLOCKER]` result:
   ```bash
   mysql -u root -p tennisdb < database/migrations/000-preflight-checks.sql
   ```
   `[INFO]` results (for example, customers sharing a phone number) do not
   block the upgrade.
3. **Upgrade** (run once):
   ```bash
   mysql -u root -p tennisdb < database/migrations/001-upgrade-legacy-schema.sql
   ```
   This step is non-destructive. Replaced columns are kept, and the script
   prints every reservation with its converted time slot for checking.
4. **Optional cleanup**, after verifying the application: run
   `002-drop-legacy-columns.sql`. It permanently removes the old columns, so
   take a new backup first.

Then set `DB_NAME=tennisdb` in `.env` and create an administrator account
(step 4 above).

## 7. Running the tests

```bash
php tests/unit-tests.php
```

The HTTP suite needs a running server and a **disposable** database loaded
from `schema.sql` and `seed.sql`, plus an admin created with
`create-admin.php`:

```bash
BASE_URL=http://127.0.0.1:8000 \
MYSQL="mysql -h127.0.0.1 -uroot -p<password>" \
DB_NAME=tennis_court_reservation \
ADMIN_USER=admin ADMIN_PASSWORD='<admin password>' \
bash tests/http-functional-test.sh
```

Add `PROJECT_ROOT_URL=http://localhost/tennis-court-reservation-system` (and
set `BASE_URL` to the matching `/public` URL) to also check the `.htaccess`
deny rules under Apache.
