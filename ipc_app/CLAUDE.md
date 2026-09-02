# CLAUDE.md — ipc_app

This is **IPC** (In Process Control), a Laravel + Inertia.js (React) app scaffolded from `laravel/react-starter-kit` — the same pattern as `../new_trial_validation_app/`, but a **fully separate project**. It shares no code, no database, and no auth/session with `../new_trial_validation_app/` or the legacy app at the repo root; the only thing tying it to those apps is that all three run on the same machine, on the same MySQL instance (as separate databases), and are reachable from the shared `../portal/` chooser.

Its predecessor is a Microsoft Power Apps/Dataverse app (a production quality-inspection tool: Startup Check → Filling → Packing → Finished Good → Approval → Print), not a MySQL app. That means **there is no legacy schema to stay compatible with** — unlike `new_trial_validation_app/`, which must match `trial_validation_system`'s existing `users` table (`password_hash`, no `email_verified_at`, etc.), this app's migrations are stock Laravel, unmodified. Do not port `Schema::hasTable()` shared-DB-skip guards or custom `users` columns from `new_trial_validation_app/` here — they exist there for reasons that don't apply to this app's own, exclusively-owned database.

## Status

**Schema + models done, no controllers/UI yet** (as of 2026-09-02): scaffold, own database (`ipc_system`), stock `users` table, reachable via the portal chooser, **plus the full domain schema below is now real migrations + Eloquent models**, applied to the local `ipc_system` DB and covered by one Pest-shaped-but-plain-PHPUnit smoke test (`tests/Feature/IpcSchemaSmokeTest.php`) exercising the whole relation chain end-to-end (all 27 tests in the suite pass). **No controllers, routes, or Inertia pages exist yet for any IPC workflow screen** — Startup Check, Filling Check, Packing Check, Finished Check/AQL sampling, Approval, Print, Master Line admin, and Recycle Bin are all still unbuilt as actual features. Don't assume any of those screens work; only the underlying tables/models are real.

**Implementation notes from this pass (2026-09-02):**
- `pdftoppm`/poppler isn't installed in this environment, so the source PDF's screenshots couldn't be re-rendered this session (only `pdftotext` worked, and the PDF is almost entirely images — it yielded just the workflow/table-name outline, no field-level detail). Every checklist-style status column below (`*_status` on `startup_checks`/`filling_checks`/`packing_checks`, `StartupInspectionItem::PARAMETER_KEYS`, `FinishedCheckSample::PARAMETER_KEYS`) is implemented as a **plain nullable string, not a DB enum**, specifically so exact wording can still be corrected later without a migration. Before building any stage's actual form UI, get poppler installed (or otherwise get eyes on the PDF screenshots) and verify these against the real screens — don't trust the current column/constant names as final.
- Two deliberate refinements beyond the original design text (documented here since CLAUDE.md is the source of truth): (1) `startup_inspections`' checklist parameters (bulk color/texture, bulk odor, appearance after filling, leakage test, functional test, primer/sekunder/tersier, attribute, appearance) were normalized into a new `startup_inspection_items` child table (`parameter_key`/`status`/`remark` per row) rather than ~20 flat columns, for consistency with how `finished_check_samples` already normalizes its AQL groups — the original design text didn't specify flat-vs-child for this one. (2) `filling_checks`' 10 weight-sample readings got their own `filling_check_samples` child table (`sample_no`/`weight_value`) rather than 10 flat columns, same reasoning, mirroring `startup_bottle_weights`.
- MySQL's 64-char identifier limit was hit on three auto-named composite unique indexes (`startup_inspection_samples`, `startup_inspection_items`, `startup_inspection_test_results` — their default `{table}_{col1}_{col2}_unique` names ran long); all three now pass an explicit short name as the migration's third `unique()` argument.
- `ipc_batches` carries the recycle-bin `deleted_at`/`deleted_by` pair (reusing the pattern from `new_trial_validation_app/`, per the design decision below); `master_lines`/`master_products`/`master_test_types` also got it for admin soft-delete, matching that app's Products/Parameters/Masters pattern. Per-stage tables (`startup_checks` etc.) don't have their own `deleted_at` — Recycle Bin is expected to operate at the batch level, matching legacy, and stage completion is tracked via each stage table's own nullable `completed_at` (the "end_yn-equivalent" the design doc mentions for the stage-lock rule) rather than a delete.
- `ipc_approvals` has a `unique(ipc_batch_id, stage)` — one approval row per batch per stage, no resubmission-round concept (unlike the trial-validation app's `review_round`). Revisit if the real workflow needs multiple approval rounds per stage.

**Next step:** Startup Check as the first actual workflow feature (controller + Inertia page + tests for `startup_checks`/`startup_bottle_weights`, then `startup_inspections`/its children as a following sub-item) — mirroring how `new_trial_validation_app/`'s Fase 3 built one stage at a time. Verify the checklist/enum wording against the PDF (see poppler note above) before or during that pass, not after.

## Domain schema design (decided 2026-09-02, not yet implemented)

Source: `IPC Power apps legacy.pdf` (repo root) — a screen-by-screen export of the predecessor Power Apps/Dataverse app, including a raw Dataverse field-list dump ("Database architecture" section) for its underlying tables (Start, Filling, Bottle weight, Packing, Finished, Master Line, StartupInspection, Test startup, Recycle bin). That legacy schema is flat/denormalized in the way Power Apps solutions typically are (30 numbered sample columns per table, ~72 flattened `QST*(AC/CD/MD/mD)` AQL columns on Finished Check, cross-stage status flags all living on the Start record) — **the design below is a normalized reinterpretation for this app's own Laravel schema, not a port of that raw table list.** Re-read the PDF before implementing if anything below is ambiguous.

**Workflow shape observed in the PDF:** Home → Start Up → Filling → Packing → Finished Good → Approval → Print, plus standalone Master Line admin and Recycle Bin. Each stage after Startup is its own list, filtered to rows where the *previous* stage's completion flag is not yet Y. Approval and Print are each granular per stage — 3 separate actions (Startup / Filling & Packing / Finished), not one action covering the whole batch. Decision vocabulary differs: check-level decisions use Passed/Hold/Reject, the Finished-stage approval itself uses Accepted/Accepted With Remarks/Rejected.

**Confirmed product decisions for this app (differ from legacy on purpose):**
- New `master_products` table (fg_code, product_name, bulk_code, is_active) — form fields become dropdowns validated against this master, not free text like legacy.
- Stage-lock rule is kept as legacy has it: once a stage is marked complete (its `end_yn`-equivalent flips, or the next stage has started), the prior stage's data becomes read-only for everyone — no role-based override for now (legacy has none either; the source PDF has the user's own handwritten note confirming this: "Kalau Sudah Y, di finish check gk bisa ya").
- `ipc_id` (the inspector name field on every legacy form) becomes a real `user_id` FK to this app's own `users` table, auto-filled from the logged-in session — not a free-text name like legacy, which improves audit-trail accuracy.

**Proposed entities:**

Master data:
- `master_lines` — category, area, code, name (the Line dropdown: e.g. category "Packing", area "Make Up", code "MU 01", name "Make Up 01")
- `master_products` — fg_code, product_name, bulk_code, is_active (see decision above)
- `master_test_types` — name, category (Leakage/Functional/Attribute), is_active — normalized replacement for legacy's flat "Test startup" table (VACCUM/TORSI/SPRAY/... as boolean columns); this is config, not per-inspection data, driving which test buttons render on Startup Inspection

Core:
- `ipc_batches` — fg_code/product/bulk_code (via `master_products`), no_batch, line_id, created_by (user), current_stage, timestamps. Takes over the "parent record carries whole-batch status" role legacy put on its Start table.

Per stage (1:1 with `ipc_batches` unless noted):
- `startup_checks` — the Available/Not Available + Conform/Not Conform checklist (product standard, sample challenge test, WI/IM match, PM/BOM match, bulk status, machine checks: vision/weigher/roller/load cell/balance, validation report, identity line board), filling range min/max, density, heating, line leader/operator names, remarks, photo fields (IM number, color, coding, temperature setting)
  - `startup_bottle_weights` (child, one row per sample 1-30, not 30 columns)
- `startup_inspections` — OK/Partial OK/Not OK checklist per parameter (bulk color/texture, bulk odor, appearance after filling, leakage test, functional test, primer/sekunder/tersier, attribute, appearance) with a remark per item
  - `startup_inspection_samples` (child: volume/weight and weight-master-box readings, one row per sample 1-30)
  - `startup_inspection_test_results` (child: one row per `master_test_types` entry with its pass/fail result, replacing legacy's flat boolean-per-test-name columns)
- `filling_checks` — weight_sample readings 1-10 + computed result, sample bulk/odor conform check, sample leakage test conform check, color, name_line_leader, remarks, decision (Passed/Hold/Reject)
- `packing_checks` — primary/secondary/tersier appearance, coding, attribute (Conform/Not Conform) per tier, coding_machine, photos (palletisasi, color, coding batch/exp, tersier coding/shipper), decision (Passed/Hold/Reject)
- `finished_checks` — wi_number, exp_date, quantity_wi, masterbox, no_pallet_qty, quantity_sampling_aql, quantity_special_inspection, disposition (Accepted/Accepted With Remarks/Rejected)
  - `finished_check_samples` (child: one row per AQL parameter — the ~18 legacy groups: Tersier Identity/Appearance/Coding Batch/Coding NA/Shipper Label, Secondary Identity/Appearance/Coding Batch/Coding NA/Attribute, Primary Packaging/Capping-Sealing/Coding/Coding NA/Attribute, Functional Test, Special Test Bulk/Color/Odor — each with ac/cd/md/mnd defect counts, replacing legacy's ~72 flattened `QST*`/`QSS*`/`QSP*`/`QSF*` columns)

Cross-cutting (span all stages, not duplicated per stage table like legacy does):
- `ipc_approvals` — batch_id, stage (startup/filling_packing/finished), decision, approver_user_id, remarks, approved_at
- `ipc_print_logs` — batch_id, stage, printed_by_user_id, printed_at
- `ipc_attachments` — polymorphic (batch_id, stage, field_label, file_path) for every camera/photo field scattered across the forms (IM number, color, coding, WI number, exp date, palletisasi, etc.) instead of a separate image column per field
- Recycle Bin — no separate physical table; reuse the `deleted_at`/`deleted_by` soft-delete pattern already established in `new_trial_validation_app/` rather than a dedicated Dataverse-style recycle list

**Open/deferred, not yet decided:** exact enum values for every dropdown (transcribe from the PDF's screenshots per field when building each stage's form/migration, don't guess — **still not done, see the "Implementation notes" caveat under Status above: poppler isn't installed in this environment so this hasn't been verified yet**), whether `master_lines`/`master_products` need admin CRUD screens before or alongside the first workflow stage, workflow-specific RBAC (who can do Startup Check vs. approve — still "all authenticated users equivalent" per the Non-goals section below until decided otherwise).

**✅ Migrations + Eloquent models done, 2026-09-02** — see "Implementation notes" under Status above for what shipped and where it deviated from/refined this design text. **Next step:** Startup Check as the first actual workflow feature (controller + Inertia page + tests), mirroring how `new_trial_validation_app/`'s Fase 3 built Trial form → Validation → Weighing → Attachments → Review → Approval → Reports one stage at a time.

## Commands

```powershell
# Backend dev server (from ipc_app/)
php artisan serve --port=8002

# Frontend build/dev (separate terminal)
npm run dev      # Vite HMR
npm run build     # production build

# Tests / quality gate
php artisan test
vendor/bin/pint
vendor/bin/phpstan analyse   # if configured — check composer.json scripts
npm run lint
npx tsc --noEmit
```

Run alongside the other two apps for local dev:
```powershell
php -S localhost:8000 -t public                 # legacy, from repo root
cd new_trial_validation_app; composer run dev    # :8001
cd ipc_app; php artisan serve --port=8002        # :8002
php -S localhost:8003 -t portal                  # portal chooser, from repo root
```

## Database

Own database, `ipc_system`, on the same local MySQL instance (`127.0.0.1:3306`) as `trial_validation_system` — but a completely separate schema with zero shared tables.

One-time local setup:
```powershell
mysql -u root -e "CREATE DATABASE ipc_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
cd ipc_app
php artisan migrate
```

**`migrate:fresh` / `migrate:refresh` / `db:wipe` are safe to run here** — `ipc_system` is exclusively owned by this app, nothing else reads or writes it. This is the opposite situation from `new_trial_validation_app/`, where those same commands once wiped the *shared* `trial_validation_system` DB by accident (see that project's `CLAUDE.md` and the `shared_db_wiped_2026_08_24` memory) — that incident doesn't apply here, but the general habit of double-checking `.env`/`DB_DATABASE` before a destructive migration command is still good practice.

## Auth

Stock Fortify, stock `users` table (`id`, `name`, `email`, `email_verified_at`, `password`, `remember_token`, timestamps). Registration is enabled by default — there's no legacy user base to seed against. No SSO, no shared session with the other two apps in this repo; a user needing access to more than one app logs into each separately.

## Non-goals right now

Not yet built — do not assume any of this exists:
- IPC workflow screens/controllers/routes (Startup Check, Filling Check, Packing Check, Finished Check/AQL sampling, Approval list, Print list). The underlying tables and Eloquent models **do** exist now (see "Domain schema design" above) — only the controller/UI layer is missing.
- Master Line / Master Product / Master Test Type admin CRUD screens, Recycle Bin UI.
- Workflow-specific RBAC (who can do Startup Check vs. approve) — for now, all authenticated users are equivalent.
- Any data migration from Power Apps/Dataverse/SharePoint Lists — when the real port happens, it's very likely a manual export/import, not an automated script.
