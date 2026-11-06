# TaskHaat — Phase 7 End-to-End QA Plan

## Scope and exclusions

This phase validates the current PHP + MySQL workflows and adds read-only diagnostic tools. It does not add SMTP, email delivery, payment gateways, or automatic payouts. Publisher deposits are credited manually by an Admin after checking the external payment; withdrawals are paid manually and then marked paid in TaskHaat.

## Automated checks

Run these commands from the TaskHaat project root:

```cmd
C:\xampp\php\php.exe tests\qa_static_test.php
C:\xampp\php\php.exe tests\security_helpers_test.php
C:\xampp\php\php.exe database\verify.php
```

- `qa_static_test.php` checks the source tree, form-to-handler mapping, CSRF coverage, role guards, schema structure, and deferred integrations. It does not connect to MySQL.
- `security_helpers_test.php` tests payout-reference encryption/decryption and masking helpers.
- `database/verify.php` connects to MySQL and runs read-only financial and workflow consistency checks.

You can also open phpMyAdmin, select `taskhaat`, open the SQL tab, and run `database/qa_checks.sql`. It contains SELECT statements only and does not modify records.

## Demo accounts

| Role | Email | Password |
|---|---|---|
| Admin | `admin@taskhaat.local` | `Admin@12345` |
| Publisher | `publisher@taskhaat.local` | `Publisher@12345` |
| Publisher 2 | `publisher2@taskhaat.local` | `Publisher2@12345` |
| Worker | `worker@taskhaat.local` | `Worker@12345` |
| Worker 2 | `worker2@taskhaat.local` | `Worker2@12345` |
| Worker 3 | `worker3@taskhaat.local` | `Worker3@12345` |
| Worker 4 | `worker4@taskhaat.local` | `Worker4@12345` |

## Browser acceptance tests

Use the imported demo database. Record the observed result for each case. Do not rely only on the toast message; confirm the database/dashboard state after each action.

### A. Authentication and permissions

1. Sign in as each demo role and confirm the correct workspace opens.
2. Sign out and confirm protected dashboard pages redirect to login.
3. Try a Worker POST action while signed in as a Publisher; it must be rejected.
4. Try an Admin-only action as a Publisher; it must be rejected.
5. Try to review a submission from a Publisher account that does not own its campaign; it must be rejected.
6. Submit a POST form without a valid CSRF token; it must return HTTP 419 without changing records.

### B. Publisher → Admin → Worker campaign lifecycle

1. Sign in as `publisher@taskhaat.local`.
2. Create a campaign with reward ৳100, 2 worker slots, and the default 8% publisher fee. Expected total reserve: ৳216.00.
3. Confirm the Publisher deposit decreases by ৳216.00 and reserved balance increases by ৳216.00.
4. Sign in as Admin and approve the pending campaign.
5. Sign in as a Worker and claim one slot. The campaign's claimed slot count must increase by exactly one.
6. Submit proof with notes; optionally attach a permitted JPG, PNG, WEBP, PDF, or TXT file.
7. Sign in as the owning Publisher and approve the proof. Expected worker net reward: ৳92.00 (8% worker fee); platform commission: ৳16.00; Publisher reserved balance decreases by ৳108.00.
8. Use a second Worker to claim the other slot and submit proof. Reject it with a clear reason. Expected ৳108.00 returns from reserved to Publisher deposit; no Worker reward is credited.
9. Confirm campaign state, slots, submission history, Worker wallet, Publisher wallet, and notifications.

### C. Manual Publisher deposit

1. Sign in as Admin and open Finance.
2. Credit a Publisher account with a test amount and a unique external transaction reference.
3. Confirm the Publisher deposit wallet and wallet ledger increase by exactly that amount.
4. Submit the same external reference again. The second credit must be rejected as a duplicate.
5. Confirm that no payment API or automatic transfer is called; this is only a manual ledger credit after out-of-band verification.

### D. Manual Worker withdrawal

1. Sign in as a Worker with an available balance at or above the configured minimum.
2. Submit a withdrawal request using a test payout destination.
3. Confirm available balance decreases and withdrawal hold increases by the same amount.
4. Sign in as Admin, move the request to Processing, then mark it Paid with an external payment reference.
5. Confirm the hold decreases, the amount is not credited back to available balance, and the request shows Paid.
6. Repeat with a separate request and reject it. Confirm the held amount returns to available balance.
7. Confirm the Admin sees only a masked payout reference in ordinary list views; the stored value for new requests is encrypted.

### E. Admin and support

1. Search for a demo user and filter by role/status.
2. Suspend a non-admin demo account. Confirm login is blocked and any approved campaigns owned by a suspended Publisher are paused.
3. Reactivate the account. Confirm paused campaigns remain paused until the Publisher explicitly resumes them.
4. Edit and deactivate a task category, then confirm it is unavailable for new campaign creation while existing campaigns remain intact.
5. Submit a public support request. Confirm it appears in Admin Support Inbox and creates an Admin notification.
6. Change the support ticket status and add an internal note. Confirm the sender receives an in-app notification, and no email is sent.
7. Export a campaign/user/finance CSV as Admin and verify date filters and spreadsheet formula escaping.

### F. Responsive and accessibility checks

1. Test Homepage, Browse, Task Details, Login, Signup, Worker, Publisher, and Admin at 360px, 768px, 1280px, and 1440px widths.
2. Check horizontal overflow, clipped dialogs, form labels, buttons, data tables, menu, and footer.
3. Switch Light/Dark mode on each main page and verify readable contrast.
4. Navigate forms and menus using keyboard Tab, Shift+Tab, Enter, Space, and Escape.
5. Check browser console for missing assets, JavaScript errors, and CSP violations.

## Financial invariants

After the browser tests, run:

```cmd
C:\xampp\php\php.exe database\verify.php
```

All checks should report PASS. Pay particular attention to:

- wallet balances = signed wallet ledger totals for all four buckets;
- Publisher reserved balances = sum of campaign `budget_remaining`;
- campaign `claimed_slots` = number of claim rows;
- claim status = matching submission status;
- approved reward snapshots = saved campaign fee terms;
- Worker reward credits = approved submission net rewards;
- platform commission = publisher fee + worker fee on approved submissions;
- withdrawal hold = pending + approved + processing withdrawals.

## Test boundary

The source-level and helper tests can run without a database. The database verifier and browser acceptance tests must run on the user's XAMPP/MySQL environment. Passing static checks is not proof that a live MySQL transaction, real upload, or all screen sizes have been tested. SMTP and payment gateway tests are intentionally out of scope.
