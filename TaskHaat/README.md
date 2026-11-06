# TaskHaat — PHP + MySQL

TaskHaat is a Bangladesh-focused, multi-worker microtask marketplace. A publisher creates one campaign with multiple worker slots; workers claim individual slots and submit proof; publishers or Admin review evidence; approved rewards are settled through internal wallet ledgers.

**Stack:** PHP 8.1+, MySQL 8.0.16+ / MariaDB 10.5+, PDO, HTML, CSS and JavaScript.  
**Visual reference:** `TaskHaat_Preview(20261105-185255).html`  
**Design baseline:** teal `#00CEC8`, cream `#FCEFC3`, orange `#FF9C5F`, accent red `#EB4203`, ink `#202B2B`, DM Sans + Manrope, square-corner visual system.

## 1. XAMPP installation

1. Extract the project into a folder such as `D:\Projects\TaskHaat`.
2. Open XAMPP Control Panel and start **MySQL**. The PHP built-in server can serve the site, so Apache is optional for this setup.
3. Open phpMyAdmin at your local address, for example `http://localhost:8080/phpmyadmin`.
4. Import **`database/schema.sql`**. This is the complete clean-install database and demo dataset. It creates the `taskhaat` database, all 15 tables, default categories/settings, the Admin account, demo accounts, campaigns, claims, submissions, wallet records, withdrawals, notifications, audit history and support tickets.
5. Do **not** run `database/seed.php` after this import. The Admin and demo accounts are already in the SQL file.
6. In a CMD terminal, from the project root, start PHP:

   ```cmd
   C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
   ```

7. Open `http://127.0.0.1:8000/`.

### Database connection

`config/config.php` defaults to:

- Host: `127.0.0.1`
- MySQL port: `3306`
- Database: `taskhaat`
- User: `root`
- Password: blank (standard local XAMPP default)

**Important:** `localhost:8080` is the phpMyAdmin web address in this setup. It is not the MySQL port. Keep `DB_PORT` at `3306` unless XAMPP's MySQL service is configured differently.

Enable these PHP extensions in the XAMPP `php.ini` if they are disabled: `pdo_mysql`, `openssl`, `fileinfo`, `mbstring` and `session`. For uploads, set `upload_max_filesize=6M` and `post_max_size=8M`, then restart the PHP/Apache process.

## 2. Demo accounts

All demo passwords are plaintext credentials for local testing only; the database stores PHP-compatible password hashes.

| Role | Email | Password |
|---|---|---|
| Admin | `admin@taskhaat.local` | `Admin@12345` |
| Publisher | `publisher@taskhaat.local` | `Publisher@12345` |
| Publisher 2 | `publisher2@taskhaat.local` | `Publisher2@12345` |
| Worker | `worker@taskhaat.local` | `Worker@12345` |
| Worker 2 | `worker2@taskhaat.local` | `Worker2@12345` |
| Worker 3 | `worker3@taskhaat.local` | `Worker3@12345` |
| Worker 4 | `worker4@taskhaat.local` | `Worker4@12345` |

Change or remove demo accounts before using a deployed instance. The initial demo data uses clearly fictional account references and example.com evidence URLs; no real payment is made.

## 3. Reset / import warning

`database/schema.sql` is a **destructive clean-reset script**. It drops and recreates the known TaskHaat tables. Importing it deletes current TaskHaat accounts, campaigns, claims, submissions, wallet balances, withdrawals, notifications, support tickets and audit history. Export your current database first if any records must be kept.

The schema includes the full profile fields, support inbox, login-attempt log, payout-reference encryption columns and wallet idempotency key. Do not import the older profile migration after importing this schema. No additional migration is needed for this Phase 6 build.

## 4. Configure the encryption key

New withdrawal payout references are encrypted at rest using AES-256-GCM. `APP_KEY` is used for payout encryption and privacy-preserving login-rate-limit identifiers.

The project has a fixed local-development fallback in `config/config.php`. For any shared/staging/production environment, generate a unique key:

```cmd
C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
```

Set it as an environment variable named `APP_KEY`, or replace the local fallback in `config/config.php` for a single-machine local test. Keep the same key for the lifetime of encrypted payout records. **Changing `APP_KEY` after encrypted withdrawal references exist makes those records undecryptable.** Production mode refuses to start while the default development key is in use.

On Windows CMD, an environment variable can be set for the current terminal session before starting the server:

```cmd
set APP_KEY=PASTE_YOUR_64_CHARACTER_RANDOM_KEY_HERE
C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public
```

## 5. Verify the database after import

After MySQL is running and the schema has been imported, run:

```cmd
C:\xampp\php\php.exe database\verify.php
```

This checks the 15-table schema, wallet-ledger balances, publisher reserve versus campaign balances, campaign claim counts, withdrawal holds and wallet creation for every user. Any `[FAIL]` should be resolved before testing financial workflows.

Run the standalone payout encryption/masking helper tests with:

```cmd
C:\xampp\php\php.exe tests\security_helpers_test.php
```

## 6. Main application workflows

### Worker

- Register/login and edit personal profile.
- Search/filter the public marketplace, open campaign details and claim one unique slot per campaign.
- Submit text notes, an HTTP/HTTPS evidence URL and optional JPG/PNG/WEBP/PDF/TXT evidence.
- View pending/approved/rejected submissions and feedback.
- See available, pending-estimate and on-hold amounts, wallet ledger and withdrawal history.
- Request withdrawals; payout references are encrypted in storage and masked in the Worker history.
- Read notifications individually or in bulk.

### Publisher

- Create campaigns with category, reward per worker, slots, deadline, instructions, proof format and optional cover image.
- Campaigns reserve the full reward pool plus publisher fees at submission and wait for Admin moderation.
- Search/filter campaigns, view slot progress, pause/resume/cancel where allowed, and edit/resubmit pending or rejected campaigns that have no claims.
- Review worker submissions with evidence, approve or reject with feedback, and inspect campaign/wallet activity.
- The budget estimator rounds the publisher fee per worker to two decimals before multiplying by slots, matching the PHP settlement rules.

### Admin

- Moderate campaigns and proof submissions.
- Search/manage user accounts; suspend/reactivate non-admin users.
- Create/edit/deactivate categories.
- Review withdrawal requests, move to processing, mark paid only with an external payment reference, or reject and return the held balance.
- Manually credit publisher deposit wallets after checking an external payment reference; duplicate references are protected by a unique idempotency key.
- View reports/CSV exports, audit records, notifications and the Support Inbox.
- Payout account references are decrypted for authorized Admin finance review; Worker views only show a masked reference.

## 7. Financial and payment boundaries

The reference workflow uses an 8% publisher fee added to campaign funding and an 8% worker fee deducted from gross reward by default. Admin can configure rates from 0–30%; each campaign stores the rates that were active when it was created.

All balance changes use database transactions, row locks and wallet-ledger records. Database checks reject negative wallet balances, invalid campaign budget/slot values and invalid transaction amounts. Wallet credits/debits remain internal TaskHaat records.

**No live bKash, Nagad, Rocket, card or bank API is connected.** Admin deposit credits and paid-withdrawal actions only record manually verified external transfers. They do not move money automatically. Email verification and password-reset email delivery also require an email provider and are not connected in this build. The help widget is deterministic built-in content, not a live AI agent.

## 8. Security baseline in this update

- PDO prepared statements and strict database error handling.
- Password hashing, session ID regeneration, strict cookie-only sessions, HttpOnly and SameSite cookies.
- CSRF validation on state-changing POST requests; CSRF token rotates after authentication.
- Login throttling with HMAC-hashed email/IP identifiers (raw IP/email are not written to the login-attempt table).
- Role, ownership and active-account checks on key campaign, proof, finance and profile operations.
- Transactions/row locks and balance checks for claims, proof settlement, withdrawals, campaign refunds and publisher deposits.
- AES-256-GCM encryption for new payout references; last four characters are indexed separately.
- MIME, upload error, size, image signature and image-dimension validation; evidence is stored outside the public web root and downloaded through an authorization-checked endpoint.
- Security response headers and a Content Security Policy.
- Upload folder denies script-like file extensions and directory listing.
- Audit records for important operations.

This is an application hardening baseline, not a security certification. Run the live test checklist and commission an independent security review before handling real funds or sensitive production data.

## 9. Validation and known limitations

The current build has been checked with PHP syntax lint across all PHP files, JavaScript syntax parsing, and six standalone payout encryption/masking helper tests. This environment has no PDO MySQL driver, so it cannot execute the full application against MySQL. Use `database/verify.php` and the XAMPP workflow below for live checks.

Recommended local end-to-end test:

1. Log in as the seeded Publisher, create a campaign, and verify that deposit decreases and reserved balance increases.
2. Log in as Admin, approve the campaign.
3. Log in as a separate Worker, claim a slot, submit proof and upload one test file.
4. Review as Publisher; approve one proof and reject another. Verify Worker earnings, platform fee, publisher reserve and claim status.
5. Request a Worker withdrawal; confirm that Admin sees the full payout reference while the Worker sees only the masked reference.
6. Move a withdrawal through processing/paid, then create another and reject it. Verify the hold balance and ledger.
7. Try submitting an invalid/oversized file and verify the request is rejected without leaving an orphan file.
8. Try accessing another user's evidence URL and another publisher's campaign edit URL; access must be denied.
9. Try repeated incorrect logins until throttled; the message should tell you to wait.
10. Run `database/verify.php` again; all reconciliation checks should pass.

## Phase 7 — End-to-end QA and verification

Run the source-level and helper tests from the project root:

```cmd
C:\xampp\php\php.exe tests\qa_static_test.php
C:\xampp\php\php.exe tests\security_helpers_test.php
C:\xampp\php\php.exe database\verify.php
```

`qa_static_test.php` checks source wiring and security controls without connecting to MySQL. `database/verify.php` checks live wallet, campaign, submission, withdrawal and commission invariants. You can also run `database/qa_checks.sql` in phpMyAdmin; it contains read-only SELECT statements. Follow `PHASE_7_QA_TEST_PLAN.md` for the browser acceptance run.

The current environment can lint PHP/JavaScript/CSS and run the standalone tests, but it cannot connect to a live MySQL server. Live transaction/browser testing must therefore be completed in XAMPP; do not treat the static checks as a completed end-to-end run.

**Integration choices:** no online payment gateway is being integrated. Publisher deposit credits and Worker withdrawals are manual Admin workflows. SMTP/email delivery remains deferred.

## 10. Project structure

```text
TaskHaat/
├── app/                 # Bootstrap, helpers, authentication and POST actions
├── config/              # Environment/database configuration
├── database/            # Full schema/demo data, optional seeder and DB verifier
├── public/              # Web root, CSS, JavaScript and public campaign images
├── storage/             # Private proof files and logs (not web-accessible)
├── tests/               # Standalone helper tests
└── views/               # Public pages and Worker/Publisher/Admin workspaces
```

See `PROJECT_ROADMAP.md`, `PROJECT_STATUS.md` and `PHASE_6_PRODUCTION_HARDENING_NOTES.md` for the implementation summary and remaining QA items.
