# Phase 9.5 — Section 01: Shared Foundation, Public Shell & Homepage

## Scope

Implemented the first section of the approved reference-aligned redesign plan. The supplied original HTML remains the source of truth and the existing palette has not changed.

## Updated files

- `views/layout.php`
  - Loads `public/assets/css/redesign-foundation.css` last, after all legacy and page-family stylesheets.
  - No route, PHP data, form, or database behavior changed.
- `public/assets/css/redesign-foundation.css` (new)
  - Consolidates the exact reference design tokens.
  - Reasserts typography, square geometry, buttons and keyboard focus.
  - Aligns topbar, sticky navigation, logo, search and actions.
  - Aligns Homepage hero, search/board illustration, metrics, categories, feature cards, worker/publisher workflow, task rows, mobile showcase, stories, resources, safety, FAQ, CTA and footer.
  - Aligns the floating Help widget with the same palette.
  - Adds public Homepage light/dark and responsive rules.
  - Explicitly neutralizes legacy light-theme overrides that were changing the reference hero, topbar, CTA, mobile/footer surfaces and task-image treatment.
- `PHASE_9_5_REDESIGN_PLAN.md`
  - Records the complete section-by-section redesign plan and acceptance criteria.

## Behavior and data safety

- No PHP view content or form action was changed.
- No MySQL schema, migration, seed, user, campaign, wallet, deposit, withdrawal or transaction data was changed.
- SMTP and online payment gateways remain out of scope.

## Static validation

- `views/layout.php`: PHP syntax passed.
- `views/home.php`: PHP syntax passed.
- `public/assets/js/app.js`: Node syntax passed (unchanged).
- `redesign-foundation.css`: parsed successfully with 0 CSS parse errors.
- Required source palette tokens present: `#00CEC8`, `#FCEFC3`, `#FF9C5F`, `#EB4203`, `#202B2B`.

## XAMPP browser review still required

After merge, check Homepage at 1440px, 1024px, 768px and 390px, in both light and dark mode. Verify the sticky header, search, mobile menu, theme toggle, FAQ, task links, CTA links and Quick Help widget. Static checks do not prove browser rendering or JavaScript behavior.

## Apply with CMD

```cmd
cd /d D:\Projects\TaskHaat
tar -xf "C:\Users\User\Downloads\TaskHaat_Redesign_Section_01_Public_Home_Update.zip" -C "D:\Projects\TaskHaat"
```

Then refresh the site with `Ctrl + F5`.
