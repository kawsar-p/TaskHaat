# TaskHaat Phase 9.5 — Reference-Aligned UI/UX Redesign Plan

## Design decision

- **Direction:** Preserve and polish the supplied original TaskHaat HTML reference.
- **Palette:** Keep the original TaskHaat palette exactly; do not introduce a new brand palette.
- **Backend boundary:** Visual redesign must not change authentication, role permissions, campaign rules, wallet calculations, manual payment verification, database records, or routes unless a separate functional defect is explicitly identified.
- **Delivery method:** Execute section by section. Each section receives its own change notes, patch ZIP, and syntax/structure validation. Do not overwrite the whole project for a visual-only section.

## 1. Source of truth and design tokens

The uploaded `TaskHaat_Preview(20261105-185255).html` is the visual and content source of truth. Its final palette and component language take precedence over earlier CSS declarations in the PHP project.

| Token | Value | Primary use |
|---|---|---|
| TaskHaat Teal | `#00CEC8` | Primary action, brand mark, progress |
| Deep Teal | `#006D69` | Strong brand surfaces, navigation/footer |
| Teal Soft | `#E5FAF8` | Selected, hover and informational surfaces |
| Cream | `#FCEFC3` | Warm section, feature and highlight surfaces |
| Orange | `#FF9C5F` | Secondary accent and focus indication |
| Accent Red | `#EB4203` | Urgent/primary conversion CTA and destructive emphasis |
| Ink | `#202B2B` | Main text and headings |
| Body | `#596765` | Paragraphs and supporting text |
| Muted | `#7F8B89` | Metadata and secondary labels |
| Border | `#E4E9E6` | Standard component borders |

- Typography: Manrope for headings and important figures; DM Sans for interface and body text, matching the reference's font pairing.
- Geometry: square corners and restrained borders; do not introduce rounded-card styling absent from the reference.
- Surfaces: white content canvas, warm cream/teal section alternation, restrained shadows and clear spacing.
- Interaction states: default, hover, active, focus-visible, disabled, loading, empty, validation error and success.
- Theme: preserve the same brand hierarchy in light and dark modes; dark mode changes surfaces and text contrast, not brand identity.

## 2. Audit findings from the Phase 9 project

1. The PHP homepage already contains the reference's main content sequence: hero/search, metrics, categories, features, worker/publisher workflow, task feed, mobile showcase, community stories, guides, trust and safety, FAQ and CTA.
2. The public shell is split across `views/partials/header.php`, `views/partials/footer.php`, and `views/layout.php`.
3. The stylesheet cascade contains legacy rules and later overrides across `taskhaat.css`, `reference-theme.css`, `section-pages.css`, workspace stylesheets and `site-experience.css`. This makes final appearance dependent on stylesheet order and raises the risk of one page family overriding another.
4. Authentication uses real PHP views (`views/auth/login.php` and `views/auth/register.php`), not the static demo forms in the original HTML. The redesign must preserve their real POST fields, CSRF tokens and backend behavior while matching the reference appearance.
5. Worker, Publisher, Admin, manual-deposit and support screens contain functional server-rendered forms and status states. Their markup and behavior must remain intact while styling is applied.

## 3. Complete section inventory and execution sequence

Each item is a separate visual work package. A section is not marked complete merely because its CSS parses; the matching PHP route must also be checked in the user's XAMPP browser.

### Section 01 — Shared foundation, public header/footer and Homepage [IMPLEMENTED — XAMPP visual review pending]

**Files:** `views/layout.php`, `views/partials/header.php`, `views/partials/footer.php`, `views/home.php`, new final reference stylesheet.

**Reference areas:** top utility bar, sticky navigation, logo, nav search, theme toggle, login/get-started actions, hero with task-board visual, metrics strip, categories, feature cards, two-sided workflow, task rows, mobile showcase, community stories, resource cards, trust/safety, FAQ search/accordion, CTA, footer and floating quick-help panel.

**Acceptance criteria:**
- Exact reference palette and type hierarchy; no residual navy/blue/green alternative brand colors in the public shell.
- Same homepage content and section order as the reference.
- Search, category links, dashboard/workspace links, theme toggle, mobile menu, FAQ controls and help widget keep their real routes/actions.
- Responsive checks at 390px, 768px, 1024px and 1440px; no horizontal overflow.
- Focus-visible, readable contrast, clear button hierarchy and reduced-motion support.
- No database migration.

### Section 02 — Public marketplace pages

**Files:** `views/browse.php`, `views/task.php`, `views/fees.php`, related section stylesheet.

**Areas:** search/filter toolbar, result count, selected filter chips, sort controls, pagination, task rows/cards, task details, reward breakdown, proof instructions, claim states, related campaigns, publisher fee estimator, worker net-reward example, manual Bangladesh payment information.

**Acceptance criteria:** URL/filter state remains functional; campaign status and permission states remain visible; fee math is unchanged; mobile filters and task detail panels remain usable.

### Section 03 — Authentication

**Files:** `views/auth/login.php`, `views/auth/register.php`, `views/layout.php`, authentication stylesheet.

**Areas:** split-screen brand panel, login form, signup sections, password visibility, password confirmation, form validation, consent, back navigation and mobile stacked layout.

**Acceptance criteria:** match the original teal/white split layout; auth-panel heading remains white; form labels, fields, errors, consent and buttons use the reference hierarchy; preserve real POST field names, CSRF, session and login/register behavior.

### Section 04 — Worker Workspace

**Files:** `views/dashboard.php`, `views/dashboard/worker.php`, `public/assets/css/worker-workspace.css`.

**Areas:** workspace shell, side navigation, overview, task discovery, My Tasks, submissions, evidence/feedback, wallet, withdrawals, notifications and profile.

**Acceptance criteria:** consistent KPI, panel, status, table, filter and empty-state components; no change to reward or withdrawal logic; all worker actions remain role-protected.

### Section 05 — Publisher Workspace

**Files:** `views/dashboard.php`, publisher view/controller sections, `publisher-workspace.css`.

**Areas:** overview, campaigns, campaign detail/edit, campaign creation, slot progress, submission review, budget estimator, deposit wallet, manual deposit requests/history, notifications and profile.

**Acceptance criteria:** campaign lifecycle and budget display remain consistent with server calculations; action buttons clearly distinguish primary, secondary and destructive actions; no change to reservation/refund behavior.

### Section 06 — Admin Control Center

**Files:** `views/dashboard.php`, `views/dashboard/admin.php`, `admin-workspace.css`.

**Areas:** overview, moderation, users, categories, submissions, finance, deposits, withdrawals, reports, settings, audit log, support inbox and notifications.

**Acceptance criteria:** data-dense screens remain readable; tables have clear hierarchy and responsive handling; dangerous actions are visually distinct and preserve confirmation/reason requirements; Admin-only permissions remain unchanged.

### Section 07 — Manual finance operations

**Files:** manual payment views, admin finance sections, `manual-payments.css`.

**Areas:** Publisher deposit request/proof upload, Admin deposit verification, Worker withdrawal request, Admin payout processing, transaction references and ledger history.

**Acceptance criteria:** pending/approved/rejected/paid/processing statuses are visually distinct; proof preview remains private; no online gateway is introduced; balance calculations remain unchanged.

### Section 08 — Help, safety, contact and legal pages

**Files:** `views/help.php`, `views/guide.php`, `views/safety.php`, `views/contact.php`, `views/legal.php`, `views/404.php`, `site-experience.css`.

**Areas:** help search, guide layouts, safety notices, contact form, privacy/terms content and not-found page.

**Acceptance criteria:** consistent reading width, heading hierarchy, callouts, links, forms and mobile spacing; clearly identify policy text as draft where applicable.

### Section 09 — Global responsive, accessibility and interaction pass

**Files:** final design stylesheet, `public/assets/js/app.js`, all page-family stylesheets as needed.

**Areas:** breakpoints, mobile navigation, tables, filter overflow, keyboard support, focus states, reduced motion, color contrast, dark mode, toast/modal/chat layering, loading/empty/error/success states.

**Acceptance criteria:** no horizontal page overflow at tested viewports; interactive controls have labels and keyboard access; dark mode retains Teal/Cream/Orange/Red identity; no broken hover/focus/disabled states.

### Section 10 — Visual regression and Phase 10 handoff

**Areas:** page-by-page comparison against the source HTML, screenshot review, final CSS cascade review, routes/forms smoke test, role-based workflows and final documentation.

**Acceptance criteria:** every listed page family has a desktop and mobile review; known differences from the static reference are documented where PHP data, role state or security requirements make them necessary; no unverified claim of production readiness.

## 4. Reusable component standards

- **Buttons:** primary teal, conversion/urgent red, warm orange secondary where the reference uses it, neutral outlined secondary, transparent ghost; consistent heights and focus rings.
- **Cards:** square geometry, light border, white surface, subtle hover change; avoid decorative shadows or radii not present in the reference.
- **Forms:** label above field, consistent control height, clear required marker, focus ring, helper/error/success text.
- **Status pills:** semantic color mapping using the reference's Teal, Cream/Orange and Red accent hierarchy.
- **Tables:** warm header surface, compact uppercase metadata only where needed, readable row borders, responsive overflow container.
- **Dashboard panels:** common panel header/body/footer spacing and a shared empty state.
- **Spacing:** consistent container width, section rhythm and component gaps; avoid page-specific one-off dimensions unless visible in the source reference.

## 5. Release and change-control rules

- Each section is delivered as an incremental patch ZIP plus notes. Do not require a full-project replacement for CSS-only work.
- No database imports for visual-only redesign work.
- No SMTP or payment gateway integration. Manual Admin-verified deposits and withdrawals remain the selected product behavior.
- Do not add, remove or rewrite page content silently. Preserve reference copy and existing functional explanations unless correcting a verified code defect.
- Run PHP lint, JavaScript syntax validation and CSS parsing after each section patch.
- Mark browser checks as pending until the user confirms the page in XAMPP; static checks are not a substitute for live browser review.

## 6. Current execution status

- Plan prepared from the supplied original HTML and Phase 9 PHP project.
- Section 01 implementation delivered: shared visual tokens, public header/footer and Homepage. XAMPP visual review is still pending.
- Sections 02–10 remain queued and will be delivered one at a time before Phase 10 Final Audit.
