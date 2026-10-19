# Target profile

Before writing code, establish the target profile. The port must fit the receiving
application, not the other way around. Inspect the existing app first; only ask
what you cannot discover.

## 1. Application

- Existing Laravel app or new scaffold?
- Laravel version, PHP version, Filament version, test runner (PHPUnit/Pest),
  formatter (Pint), static analysis (PHPStan/Larastan).
- Does the app already have models, migrations, resources or services for the
  module's tables? Reuse them; do not create duplicates.
- Directory conventions: `app/Models`, `app/Filament/Resources`, domain modules,
  `app/Services`, `app/Actions`, `app/Rules`, `app/Observers`.
- Git status: do not overwrite user work. If the target has uncommitted changes,
  work in a branch/worktree or ask.

## 2. Database and schema

- Production database: Oracle, PostgreSQL, MySQL/MariaDB, SQL Server, SQLite?
- Development/test database: is it the same engine? Migrations must run on the
  configured connection.
- Schema strategy:
  - **Preserve Oracle names** exactly (tables, columns, constraints, sequences),
    quoting if necessary; or
  - **Map to Laravel conventions** (plural snake_case tables, snake_case columns)
    with a documented mapping.
- Identifier case: Oracle unquoted identifiers are uppercase; Laravel commonly
  uses lowercase. Pick one strategy and apply it everywhere.
- Primary keys: single/compound, surrogate/natural, sequence/identity/trigger.
- Audit columns: created/updated by/at; do they exist, and who fills them?
- Views/synonyms: read-only models, no migrations, resolve the base table.
- Unsupported types: CLOB/BLOB/RAW/XMLTYPE/ROWID/INTERVAL — decide the storage and
  document it.
- Item→column mapping: confirm the Forms `Column Name`; an item name is not
  necessarily a column name. Record the mapping in `decisiones.md`.
- Audit columns/timestamps: only if the DDL or the target app has them; do not
  invent them.
- No data migration unless the user explicitly asks. The port creates schema and
  behavior, not data.

## 3. Language and labels

- UI language: default to the **source form language** only when it is evidenced
  (UI checklist labels, form messages). If it is not evidenced, ask the user; do
  not infer it from item names. If the user wants another language, confirm.
- Do not translate stored messages, codes or data. Translate only UI chrome
  (navigation, buttons you add, empty states) if the profile asks for it.
- Labels: from the UI checklist; otherwise humanize the item name and record the
  assumption. Do not claim a label is original when it was derived.
- Field order: from the UI checklist/Forms Builder; otherwise use a business-
  sensible order and record the assumption. The IR has no field order.
- Date/number formats: follow the app locale and the source mask.

## 4. Authentication and roles

- Does the form implement users, roles or permissions (`USER`, `:GLOBAL` role
  variables, permission/role tables — ask for their names)? If yes, map them to
  Laravel auth, gates and policies.
- If the target has no auth yet, do not invent a user model. Mark role-based
  behavior `PENDIENTE` and implement the rest.
- Record who can create/edit/delete each entity. If the form does not say, ask;
  do not assume "everyone".

## 5. UI and navigation

- Filament resources vs custom Blade/Livewire vs API only.
- Navigation: one resource per entity; group name; icon; sort order.
- Master-detail: relation manager, nested form, modal or separate page.
- Table behavior: searchable/sortable columns, filters, pagination, bulk actions.
- Forms: sections/tabs, columns, read-only fields, conditional visibility.
- Buttons: what each one does; which need confirmation; which are destructive.
- Reports/exports: PDF/Excel/CSV, queue or synchronous; template source.
- Files: disk, visibility, size limits, BLOB/RAW columns.
- Accessibility and responsive behavior: follow the app's existing patterns.
- Do not emulate Forms windows, canvases, coordinates, toolbars or key bindings.

## 6. Runtime behavior

- Transactions: which operations must be atomic? `COMMIT_FORM` is not a per-field
  commit; map it to a transaction boundary.
- Locks: `SELECT ... FOR UPDATE` → `lockForUpdate()` inside a transaction.
- Timers: `WHEN-TIMER-EXPIRED` → scheduler, queued job or live polling. Choose
  based on the purpose, not mechanically.
- Batches/reports: queue, command or synchronous action.
- External systems (`HOST`, `TOOL_ENV`, `RUN_PRODUCT`, interfaces): mark pending
  unless the integration is specified.
- Audit/logging: what must be traceable? Keep the original message code in logs.

## 7. Testing and delivery

- Test database and factories. If the app uses SQLite in memory, migrations must
  be SQLite-compatible or gated by driver.
- One test per rule/validation, plus observer/service and resource smoke tests.
- Test naming and language: follow the app's convention.
- Formatter/linter commands and CI expectations.
- Branch/PR conventions, commit style, changelog.

## Default profile (only if the user does not specify)

Use these defaults, but state them in `perfil.md`:

| Item | Default |
| --- | --- |
| Stack | Laravel 13, Filament 5, PHP 8.3+, PHPUnit |
| UI | One Filament resource per confirmed entity |
| Language | Source form language |
| Schema | Preserve Oracle table/column names; snake_case only if the app already does |
| Auth | Reuse the app's auth; do not invent roles |
| Data migration | Out of scope |
| Reports/interfaces | Out of scope unless specified |
| Tests | One per rule/validation + observer/service + resource smoke |
| Formatter | Pint (or the app's formatter) |

## Profile template

Write `docs/porting/<module>/perfil.md`:

```markdown
# Port profile — <MODULE>

## Application
- Target:
- Laravel / PHP / Filament / test runner:
- Existing models/resources to reuse:
- Branch/worktree:

## Database
- Production engine:
- Dev/test engine:
- Schema strategy (preserve/map):
- Identifier case:
- Keys/sequences:
- Audit columns:
- Views/synonyms:

## UI
- Language:
- Resources/pages:
- Master-detail:
- Navigation group:
- Exports/files:

## Auth and roles
- Auth source:
- Roles/policies:
- Out of scope:

## Runtime
- Transactions/locks:
- Timers/jobs:
- External systems:

## Testing
- Command:
- Database:
- Naming:

## Out of scope
-
```

## Decisions that must be confirmed

Ask before coding if any of these is unresolved and behavior-changing:

- table/block mapping that is not confirmed by DDL or SQL;
- preserving vs mapping schema names (it affects every query and test);
- UI language (it affects every label and message);
- roles/auth mapping (it affects access control);
- date/number storage for Oracle `DATE`/`NUMBER` (it affects precision);
- destructive actions and their confirmation;
- what to do with an untranslatable trigger (preserve + pending vs replace with a
  documented approximation).

Everything else can proceed with an explicit assumption.
