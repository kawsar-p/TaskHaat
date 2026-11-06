# TaskHaat — Master Implementation Roadmap

**Product:** TaskHaat crowd-sourced microtask marketplace  
**Stack:** PHP + MySQL, HTML, CSS, JavaScript  
**Visual source of truth:** `TaskHaat_Preview(20261105-185255).html`  
**Business model:** One publisher campaign can be completed by multiple unique workers. Rewards are credited only after proof approval.

## Working rules

1. The uploaded reference HTML is the visual and content baseline. Keep its final palette, typography, square-corner geometry, page hierarchy, copy tone and section order unless a deliberate improvement is documented.
2. Preserve the existing PHP/MySQL implementation and real database workflows. Do not replace working server actions with browser-only demo behavior.
3. Do not claim that payments, email, AI support or external integrations work until they are connected and tested.
4. Deliver incremental patches with a file list and run PHP/JavaScript syntax checks. Database changes must be separate, additive migrations unless a clean reset is explicitly requested.
5. Work section-by-section. For every section, check visual parity, data source, form actions, role permissions, empty/error states, responsive layout and dark mode.

## Reference-to-PHP audit

| Area | Reference contents | Existing PHP implementation at audit | Remaining work |
|---|---|---|---|
| Global design system | Teal `#00CEC8`, cream `#FCEFC3`, orange `#FF9C5F`, accent red `#EB4203`, ink `#202B2B`; DM Sans + Manrope; square geometry; light/dark themes | Final palette is present in `reference-theme.css`; auth split-screen exists and an auth heading contrast override was added | Consolidate duplicated/older CSS overrides, verify every route in browser at desktop and mobile, check dark-mode contrast on each component |
| Header/navigation | Topbar, brand, Find Tasks, How It Works, Workspace, Fees, FAQ, search, theme, login/get started, mobile navigation | PHP header has real route links, theme toggle, task search and auth-aware actions | **Implemented in Phase 5:** active states, clear-search action, mobile menu close/escape/outside-click, Help Center and Contact links; check visual behavior in XAMPP browser |
| Homepage | Hero + search, four live metrics, category grid, features, worker/publisher workflow, fresh task list, mobile showcase, community stories, guides, trust/safety, FAQ, CTA, footer | All major reference sections exist. Metrics, categories and task list use MySQL. Testimonial cards are explicitly labeled illustrative; guides/mobile mockup are static content | Verify exact design parity and mobile spacing; replace placeholder/demo stories only when verified user reviews exist; make guides actionable or keep them clearly informational |
| Browse/task discovery | Search, category/reward/sort controls, task list and empty state | MySQL search/filter/sort exists; prior result was capped at 60 without pagination/result count | **Implemented in this phase:** paginated query, result range, active filter chips, reset action, closest-deadline sort, responsive filter layout |
| Task detail | Full campaign description, reward, time, deadline, slots, proof type/instructions, claim flow, proof submit, related work | Claim/proof upload/download/status flow exists | **Implemented in this phase:** related tasks, cover image presentation, progress steps, fee/net reward clarity, owner/admin visibility rules, safer owner/claim state handling |
| Fees & rewards | Publisher estimator, worker net reward example, Bangladesh payment methods, payment limitation note | Estimator existed but payment workflow card replaced the reference's full payment-method and worker example content | **Implemented in this phase:** full estimator, worker net example, bKash/Nagad/Rocket/bank method cards, settlement workflow, clear manual payout disclosure |
| Worker dashboard | Overview, find tasks, my tasks, submissions, wallet/withdrawals, notifications, profile | Real MySQL queries and POST actions exist | **Implemented in Phase 2:** accurate KPI/reward summary, recommendations, task/submission status filters and pagination, full paginated wallet/withdrawal history, unread/per-item notifications, extended profile persistence, claimed-task access when campaigns pause/complete. Local XAMPP workflow testing remains required |
| Publisher dashboard | Overview, campaign list, create campaign, live budget estimate, proof review, deposit wallet, notifications, profile | Real campaign creation/reservation, pause/resume/cancel, proof review and wallet functions exist | **Implemented in Phase 3:** searchable/status-filtered campaign management, campaign detail and worker activity, safe edit/resubmit for pending/rejected campaigns without claims, budget reserve reconciliation, filtered review history with pagination, paginated wallet ledger, complete profile editor and notification read controls. Live DB workflow verification remains required |
| Admin workspace | Overview, moderation, submissions, users, categories, finance/withdrawals, reports, settings, audit | Existing modules were present but several lists were capped or pending-only; category editing and status lifecycle needed improvement | **Implemented in Phase 4:** SQL-backed overview KPIs, searchable/filterable/paginated moderation and proof queues, user role/status search and pagination, safe suspend/reactivate with publisher campaigns paused on suspension, category edit/activate/deactivate, withdrawal processing/paid/rejected lifecycle with payment reference guard, duplicate-safe publisher deposit credits, date-filtered reports and CSV exports, searchable/paginated audit history |
| Auth/profile | Full-screen split login/signup, personal info, TaskHaat profile, consent, forgot password link | Registration stores extended profile fields after migration; login and role handling exist | Verify all lower signup fields and persistence; implement full profile editing; implement real email verification and password reset only with mail provider/configuration |
| Support/notifications | Floating local help assistant, notifications, toasts | Built-in deterministic support answers and database notifications exist | **Implemented in Phase 5:** clearer built-in helper disclosure, quick prompt chips, searchable Help Center/FAQ, DB-backed Contact Support form and Admin Support Inbox. No external email or AI service is connected. |
| Finance/integrations | BDT wallet, publisher funding, worker withdrawals | Internal ledger, manual publisher wallet credits and admin-paid/rejected withdrawal actions exist | External bKash/Nagad/Rocket/bank integration is **not connected**; choose provider and obtain credentials before implementing real deposits/payouts |
| Security/quality | Clear role-specific workflows and prohibited-task rules | CSRF, prepared statements, role checks, file type/size validation, protected download endpoint and audit helper exist | Review all inputs/outputs, upload paths, concurrency, business invariants, authorization edge cases, responsive and dark-mode visual QA |

## Execution phases

### Phase 0 — Audit and baseline (completed)
- Read the full reference HTML and current PHP routes, templates, CSS and JavaScript.
- Establish reference palette and content sections as the baseline.
- Record which features are database-backed, static, demo-only or not integrated.

### Phase 1 — Public marketplace experience (implemented in this update)
- Browse: pagination, result counts, active filters, reset, deadline sort, mobile filter layout.
- Task details: cover image, reward breakdown, slot progress, proof instructions, claim/proof states, related campaigns, owner/admin visibility.
- Fees: publisher calculator, worker example, Bangladesh payout methods, settlement flow and manual-payment disclosure.
- Validate PHP and JavaScript syntax and keep DB schema unchanged.

### Phase 2 — Worker experience (implemented; local XAMPP QA pending)
- Overview: accurate status totals, pending net reward estimate, lifetime approved earnings, withdrawals marked paid, recent activity and unclaimed task recommendations.
- My Tasks: status filter counts, keyword search, pagination, deadline context and direct task links.
- Submissions: pending/approved/rejected filters, independent counts, feedback and protected evidence download links.
- Wallet: available/pending/on-hold balances, lifetime earnings, paginated ledger and withdrawal history, Admin notes, server-side min/max/balance validation.
- Notifications: unread badge/count, all/unread views, per-item mark-read and mark-all-read.
- Profile: persist names, phone, country, district, city, occupation, skills, organization and website with server-side validation.
- Task access: an existing claimant can continue to view a paused/completed campaign; unpublished campaigns remain hidden from non-owners/non-claimants.

### Phase 3 — Publisher experience (implemented; local XAMPP QA pending)
- Overview: campaign counts by moderation state, pending proofs, worker slot fill rate and reserved wallet balance.
- Campaigns: title/description search, status filters, pagination, campaign details, worker/claim activity, progress metrics and pause/resume/cancel controls.
- Edit/resubmit: pending or rejected campaigns with no worker claims can be edited. Existing fee rates are preserved. Budget differences are reconciled transactionally between deposit and reserved balances; edits return to Admin moderation. Campaigns with worker activity and live campaigns cannot be edited.
- Create: required descriptions/proof, category, slots, reward, deadline, image, live fee calculator and insufficient-balance state.
- Review: pending/approved/rejected/all history filters, pagination, evidence preview/download and approve/reject with reason. Settlement remains in existing transactional ledger actions.
- Wallet/profile/notifications: paginated deposit ledger, visible reserved balance, complete profile editor, all/unread notifications and per-item/mark-all read controls.
- No schema migration is required. Run XAMPP/MySQL tests for campaign edit/resubmit budget increases/decreases, rejection refunds, concurrent reserve protection, review approvals/rejections, pause/resume and notifications.

### Phase 4 — Admin operations (implemented; local XAMPP QA pending)
- Overview KPIs use live SQL aggregates for users, campaigns, reviews, withdrawals, commission and worker payouts.
- Campaign moderation supports status filters, publisher/title search, pagination, expandable task/proof detail and required rejection reasons.
- Submission review supports pending/approved/rejected/all history, text/worker search and pagination.
- User directory supports search, role/status filters, pagination and wallet/location context. Suspending a publisher pauses its approved campaigns; reactivation does not automatically republish them. Admin accounts cannot be changed through this control.
- Category management supports create, edit and activate/deactivate; inactive categories cannot be used for new campaign approval. Existing campaigns remain linked.
- Withdrawal queue supports status filters, search and pagination; Admin can move pending requests to processing, mark paid only with an external payment reference, or reject with a reason and return held funds.
- Publisher deposits require an external reference, lock the wallet row and prevent duplicate identical references for the same publisher. The credit is ledgered and audited; no external payment is processed.
- Reports support date ranges, status/category/account-type/financial summaries and Admin-only CSV exports.
- Audit log supports actor/action/entity/metadata search, entity and date filters, and pagination.
- Settings remain validated and existing campaigns retain their saved fee rates.
- Local XAMPP/MySQL tests remain required for category updates, moderation transitions, suspension/reactivation, withdrawal lifecycle, duplicate deposit prevention, CSV export and audit rows.

### Phase 5 — Homepage, navigation and content polish (implemented; local XAMPP QA pending)
- Updated topbar/header active states, search clear behavior, FAQ link, mobile navigation state and close behavior.
- Footer now has working Discover, Help & Safety, Account and policy links.
- Made Worker, Publisher and Safety guide cards navigable dedicated pages.
- Added searchable FAQ on the Homepage and a dedicated topic-organized Help Center.
- Added dedicated Worker/Publisher guides, Trust & Safety, Contact Support, Privacy Notice and Terms of Use draft pages.
- Contact requests are CSRF-protected, validated, rate-limited and stored in the new `support_messages` table; active Admin accounts receive in-app notifications.
- Added Admin Support Inbox with search, status filters, pagination, internal notes and status transitions. Internal notes are not emailed.
- Improved the local help widget with quick prompts and explicit disclosure that responses are built-in, not AI or live-agent support.
- Added responsive and dark-mode styles for new public content and the support inbox.
- Updated the consolidated full-install schema from 13 to 14 tables. It is a destructive reset schema; after import, run `database/seed.php` to recreate the Admin account. Do not also run the older profile migration.
- Live MySQL/browser checks remain for contact submission, Admin inbox status transitions, search, theme and mobile navigation.

### Phase 6 — Integration and production hardening (implemented; live XAMPP QA pending)
- Sessions: strict mode, cookie-only, HttpOnly/SameSite, session ID regeneration and CSRF token rotation after authentication.
- Login protection: failed-login throttling using HMAC-hashed email/IP identifiers, bounded login_attempts cleanup and generic authentication failures.
- Authorization/concurrency: active-account rechecks inside financial/workflow transactions; publisher/campaign/claim row locks; prevent self-claiming; enforce deadline, slots and reserve; lock claim before proof upload/submission.
- Finance invariants: reconcile reserved balances before campaign cancellation/rejection/edit/settlement; same per-worker, 2-decimal fee formula in PHP/JS; unique idempotency key for manual publisher deposit credits; DB checks for nonnegative balances and valid budgets.
- Payout privacy: AES-256-GCM encryption for new withdrawal account references, last-four lookup index, full-reference decryption only in Admin finance workspace and masked Worker history. APP_KEY must remain stable after encrypted payout records are created.
- Upload/download: validate actual uploaded files, MIME/signature/size/dimensions, clean up uploads on DB rollback, private evidence store, MIME allowlist and no-store attachment downloads, public image upload folder denies script-like extensions.
- Security headers/CSP: response headers and CSP compatible with local assets/fonts/icons.
- Database: consolidate into 16-table clean-install schema with login_attempts, payout last-four and wallet idempotency columns; include demo users/records. User explicitly approved a new full database import, so the schema can be clean-reset. Import only `database/schema.sql`; do not also run the old profile migration or `seed.php`.
- Diagnostics: `database/verify.php` checks 16 tables, wallet ledger vs balances, publisher campaign reserves, campaign claim counts, withdrawal holds and wallet presence. `tests/security_helpers_test.php` validates payout encryption, masking and legacy demo compatibility.
- Local verification performed: all PHP syntax checks pass, JavaScript parses, six helper tests pass. Live MySQL integration cannot be run in this environment and remains pending in XAMPP.
- Still required before real production use: execute the browser and MySQL QA plan on XAMPP, review policy drafts, configure production APP_KEY/HTTPS/backups/monitoring and obtain an independent security review. Online payment gateways and SMTP are deliberately deferred; deposits/withdrawals remain manual.


### Phase 7 — End-to-end integration and QA tooling (implemented; live XAMPP execution pending)
- Added `tests/qa_static_test.php` with 21 source-level checks for rendered POST action-to-handler mapping, CSRF coverage, critical role guards, schema shape, security headers, protected storage, and the deliberate absence of payment gateway/SMTP integrations.
- Expanded `database/verify.php` to reconcile the 16-table schema, wallet ledgers, publisher reserves, claim counts, withdrawal holds, campaign budget formula, claim/submission states, approved reward snapshots, worker reward credits and platform commission.
- Added `database/qa_checks.sql`, a read-only set of equivalent checks for phpMyAdmin. It contains SELECT statements only.
- Hardened the public campaign-image upload directory against directory listing, CGI execution and script-like extensions.
- Added `PHASE_7_QA_TEST_PLAN.md` with explicit Admin → Publisher → Worker lifecycle tests, manual deposit credits, manual withdrawals, support/admin checks, permission tests and responsive browser checks.
- Validation in this environment: 29 PHP files lint cleanly; JavaScript syntax passes; all 9 CSS files parse; 21 source-level QA checks pass; 6 payout helper checks pass.
- No database schema change or migration is introduced in this phase.
- Live MySQL transactions and browser workflows still must be executed on the user's XAMPP setup. The local build environment has no PDO MySQL driver/server, so these checks are not claimed as passed here.

## Acceptance criteria for every phase

- Visuals use the supplied reference palette and component language.
- Every interactive control performs its labeled action.
- User-facing data comes from MySQL where the workflow requires persistence.
- All state-changing actions are protected by CSRF and server-side authorization.
- Empty, loading, invalid and success states are understandable.
- PHP syntax checks pass; JavaScript parses; affected SQL migrations are documented and non-destructive.
- No external payment, email or AI capability is represented as connected unless it is actually integrated.


### Phase 8 — Manual deposit and withdrawal operations (implemented; live XAMPP QA pending)
- Publisher manual deposit requests capture payment method, amount, sender transaction reference and a private screenshot/PDF proof file. Requests are held pending; the deposit wallet is not credited on submission.
- Admin configures bKash, Nagad, Rocket and bank receiving details plus minimum/maximum deposit limits. Unconfigured methods are hidden from the Publisher wallet screen.
- Admin verifies external transfers outside TaskHaat, then approves or rejects each request. Approval uses row locks and one database transaction to credit the publisher deposit wallet, create a wallet ledger record and link the request to that credit; unique normalized reference fingerprints and wallet idempotency prevent duplicate crediting.
- Publisher may cancel only a pending request. Publisher and Admin can view proof only through an authenticated private download/view route. Proofs are MIME/size/image-dimension validated and stored outside the public web root.
- Worker withdrawals remain manual. Admin must provide a separate external payment reference before marking a withdrawal Paid; Admin notes are stored separately.
- Admin direct wallet credit is retained for exceptional adjustments, not as the normal deposit path.
- Full schema now defines 16 InnoDB tables and demo deposit requests (pending, approved, rejected); `database/migrations/20261106_phase8_manual_payments.sql` upgrades an existing Phase 7 database without resetting it. Import either the migration for an existing DB or `database/schema.sql` for a clean install—never both.
- No payment gateway or SMTP was added. Live MySQL deposit approvals, file uploads, private proof access, withdrawal payouts and browser workflows still require XAMPP testing.

### Phase 9 — Performance and deployment preparation (implemented; target-host verification pending)
- Added `.env` configuration with process environment precedence, ignored secret files and a documented local/production example.
- Production startup requires HTTPS, a stable non-placeholder APP_KEY, a non-root database user and a non-empty DB password; debug output is disabled unless explicitly enabled in a non-production environment.
- Added private error logging, configurable MySQL timezone, PDO connection timeout and strict session SQL mode.
- Added filemtime-based CSS/JS versioning and Apache compression/static caching rules. PHP responses remain no-store.
- Added Apache protection for project internals and a deployment guide requiring `public/` as document root.
- Added targeted indexes for public campaign ordering/category filters, Worker claim history, Admin audit filtering and login-attempt cleanup. Existing Phase 8 database migration is idempotent; the clean-install schema includes the same indexes.
- Added CLI-only preflight, consistent SQL/media backup and restore documentation. Backups include private evidence, deposit proofs and public campaign images, stored under protected `storage/backups/`.
- Static checks, PHP lint, JavaScript parsing, CSS parsing and existing helper/QA suites pass. Actual MySQL/Apache/TLS/restore and browser tests still require the deployment host.
- No payment gateway or SMTP was added.

## Phase 9.5 — Reference-aligned UI/UX redesign

The next visual work is executed section by section against the uploaded original TaskHaat HTML. Direction is confirmed as **reference-first**, with the existing Teal `#00CEC8`, Cream `#FCEFC3`, Orange `#FF9C5F`, Accent Red `#EB4203` and Ink `#202B2B` palette preserved. The full design inventory and acceptance criteria are documented in `PHASE_9_5_REDESIGN_PLAN.md`.

- Section 01 implemented: shared design tokens, public header/footer and Homepage (`redesign-foundation.css`). XAMPP visual review is pending.
- Section 02 queued: Browse Tasks, Task Details and Fees pages.
- Section 03 queued: Login, Signup and password recovery.
- Section 04 queued: Worker Workspace.
- Section 05 queued: Publisher Workspace.
- Section 06 queued: Admin Control Center.
- Section 07 queued: manual deposit and withdrawal screens.
- Section 08 queued: Help, Safety, Contact and legal pages.
- Section 09 queued: cross-site responsive, accessibility, interaction and dark-mode review.
- Section 10 queued: visual regression and Phase 10 Final Audit handoff.

Visual-only sections do not require database re-imports. SMTP and online payment gateways remain deferred; manual Admin-verified deposits and withdrawals remain the chosen payment workflow.
