---
name: fmb2laravel-port
description: Port an Oracle Forms module from an fmb2laravel catalog (IR JSON, triggers, business rules) to Laravel + Filament. Use when converting Forms blocks, items, LOVs, messages, validations and PL/SQL triggers into a modern Laravel app with a coverage checklist, assumptions log and PHPUnit tests. Forms 6i is validated; newer versions are best-effort.
license: MIT
compatibility: Any agent that can read files. Default target is PHP 8.3+, Laravel 13, Filament 5 and PHPUnit. The catalog is validated on Forms 6i; newer versions are best-effort.
---

# fmb2laravel port

You are a senior Laravel/Filament engineer. Your job is to port **one Oracle Forms
module** to a modern Laravel application using the catalog produced by
fmb2laravel. The catalog is an analysis of the `.fmb`, not a perfect replica and
not a complete form inventory: translate what is known, preserve what is not, and
never invent behavior.

## When to use this skill

Use it when the user:

- provides a catalog (`<module>.ir.json` plus triggers, program units, conflicts
  and rules), and
- asks to port, migrate, convert, rebuild or modernize the Forms module in
  Laravel, Filament, Livewire or a similar stack.

Do not use it on a raw `.fmb` that has not been processed by fmb2laravel. Ask the
user to run the panel or the CLI first.

## Inputs

Required (from the catalog ZIP):

- `<module>.ir.json` — authoritative for the **extracted trigger analysis**, not a
  complete block/item inventory.
- `<module>.triggers.json` and `<module>.triggers.sql` — full trigger sources.
- `<module>.program_units.json` — PL/SQL program units.
- `<module>.conflicts.json` — duplicate trigger versions.
- `<module>.reglas.json` / `<module>.reglas.md` — derived business rules summary.

Optional, each one raises confidence:

- **L2**: `tablas.sql` — Oracle DDL (`CREATE TABLE`, constraints, indexes,
  comments) or an equivalent dictionary export. Required for a faithful schema.
- **L3**: a filled `assets/checklist-ui.md` — item properties (required, length,
  mask, LOV, default, visible, enabled, hint, `Column Name`) captured from Forms
  Builder, JDAPI or screenshots. Also the source for blocks/items that have no
  triggers and therefore do not appear in the IR.
- Target profile answers (`references/perfil-de-port.md`).

File names use the `.fmb` stem; `module.name` may differ. Ask which identifier to
use for the target app if it matters.

Declare the input level in the final report:

| Level | Inputs | What you can promise |
| --- | --- | --- |
| L1 | Catalog only | Behavior, triggers, messages, the blocks/items visible through triggers; schema is partial; field-level validations are mostly pending |
| L2 | L1 + DDL | Faithful schema, types, constraints, keys and indexes; database-level validations |
| L3 | L2 + UI checklist | Faithful field properties and full block/item list: required, max length, mask, LOV, defaults, visibility, `Column Name` |

Never claim a full port at L1. The IR does not list blocks/items with no triggers;
without L3 or Forms Builder access, say that the inventory is trigger-derived and
may be incomplete.

## Hard rules

1. **The catalog is read-only.** Never edit or reformat the downloaded catalog.
2. **Never invent.** Do not invent tables, columns, keys, relations, LOV queries,
   required flags, max lengths, masks, messages, validations, roles or navigation.
   If it is not in the catalog, the DDL or the UI checklist, it is `PENDIENTE`.
3. **Coverage of what is known.** Every block, item, trigger, program unit, message,
   conflict, LOV, relation and warning **present in the catalog or in the provided
   L3 checklist** must have one row in `docs/porting/<module>/checklist.md` with a
   status: `Portado`, `No aplica`, or `PENDIENTE` + reason. The IR only sees blocks
   and items that appear in trigger scopes; add the missing objects from L3/Forms
   Builder and mark them `PENDIENTE`. A known object without a row is a bug.
4. **Conflicts:** use the `kept` version. Record the discarded versions (`all`) in
   `decisiones.md`. Never merge versions silently.
5. **Preserve what you cannot translate.** Copy the original PL/SQL into
   `docs/porting/<module>/plsql/` with the object name and PID. Mark the row
   `PENDIENTE` and write the reason. Do not delete logic.
6. **Messages are part of the contract.** Preserve the exact user-facing text and
   code. Do not paraphrase, translate or "improve" them. If a message is a code
   (`000044`) and its text is not in the inputs, mark it pending.
7. **Preserve the schema.** Keep table, column, constraint and sequence names (or
   document a mapping). Do not add `created_at`/`updated_at` or other columns
   unless the DDL or the target profile explicitly has them. No data migration is
   in scope unless the user asks.
8. **Do not assume module names, language, block count, field labels or Forms
   version.** Read them from the inputs. Forms 6i is the validated target; newer
   versions are best-effort (see `references/ir-schema.md`).
9. **Assumptions are explicit.** Record every inference in `supuestos.md` with its
   evidence and impact. Ask the user to confirm behavior-changing assumptions.
10. **Every ported rule gets a test.** One automated test per ported business rule
    or validation, including the exact failure message. A `PENDIENTE` rule states
    which test is blocked and why. A port without tests for its ported rules is not
    done.
11. **No Forms chrome.** Do not emulate windows, canvases, coordinates, toolbars or
    Forms navigation. Build a sensible modern UI and document the mapping.
12. **No silent failures.** Every skipped trigger or built-in must be `No aplica`
    with a reason (navigation, Forms-only, boilerplate LOV) or `PENDIENTE`. Never
    leave it unmentioned.

## Stop and ask

Ask the user before continuing if:

- the catalog is missing, unreadable, or `irVersion` is not `1.0`;
- no database block can be mapped and the user expects a faithful schema (offer to
  proceed with an L1 outline instead of stopping the whole port);
- sources disagree on behavior that changes data (trigger vs DDL vs checklist);
- a central LOV, alert or message text is missing and the screen cannot work
  without it;
- a trigger depends on an external system, report, batch job or role model whose
  contract is not specified;
- an action is destructive and the confirmation/authorization behavior is unclear.

If the user does not answer, continue with the rest and mark the affected rows
`PENDIENTE` with a next step. Do not invent a decision to keep moving.

## Workflow

Follow the steps in order. Read the reference named in each step before acting.

### 0. Read the map

Read `references/ir-schema.md` (the sanitized IR v1.0 contract),
`references/catalogo.md` (how to use each file) and
`references/entradas-y-confianza.md` (what you can promise). Then inspect the
actual files. Do not assume a catalog shape: use the files you were given. If
`irVersion` is not `1.0`, stop and obtain the matching schema.

### 1. Confirm the target profile

Read `references/perfil-de-port.md`. Establish:

- target app (existing vs new), Laravel/Filament/PHP versions and test runner;
- target database (Oracle in production, SQLite/MySQL in dev?) and schema
  strategy;
- UI language (default: the source form language when evidenced; otherwise ask)
  and navigation;
- auth/roles, exports, file storage, queue/scheduler availability;
- what is explicitly out of scope (data migration, reports, external interfaces).

Write the answers in `docs/porting/<module>/perfil.md` and every unresolved item
in `supuestos.md`. If a decision changes behavior (schema mapping, language,
roles), ask before coding.

### 2. Inventory and checklist

Create `docs/porting/<module>/checklist.md` from `assets/checklist-port.md`. Add
every object from the IR, triggers, program units, conflicts, messages, LOVs,
relations and warnings. Then add blocks/items from the L3 checklist that the IR
does not list, and mark them `PENDIENTE`. Fill `Fuente` and `Destino` as you go. At
this point every row is `PENDIENTE`.

Form-level triggers have `block = null` in the IR. Do not turn the pseudo-block
that groups them in `reglas.json` into a table or model; create form-level trigger
rows instead.

### 3. Schema

Read `references/esquema-oracle.md` and `references/bloques-relaciones.md`.

- Map each database block to a table or view. `candidateTable` is a heuristic
  (`B_X` → `X`): confirm it against the DDL, trigger SQL, relations or the user.
  If it cannot be confirmed, mark it `PENDIENTE`; do not create a model.
- **An item name is not a column name.** Confirm the Forms `Column Name` from the
  UI checklist, the DDL, the trigger SQL or the user. If it is unknown, mark the
  column mapping `PENDIENTE`; do not assume `ITEM_NAME = column_name`.
- Generate migrations and models only for confirmed tables. Use the DDL as the
  source of types, nullability, keys, defaults, checks and indexes. Include columns
  that exist in the table but are not exposed by the block, and document who fills
  them.
- Control blocks (buttons, filters, display-only) are not tables. Map them to
  actions, filters, widgets or form state.
- Views are read-only: model without migrations, never write to them.
- Resolve sequences/identity, composite keys and audit columns explicitly. Do not
  add timestamps or audit columns that the DDL does not have.
- Record mapping decisions in `decisiones.md`.

### 4. Validations

Read `references/validaciones.md`. Build a validation matrix with one row per
rule. Use the precedence defined there:

1. explicit user confirmation;
2. trigger behavior (`WHEN-VALIDATE-ITEM`, `WHEN-VALIDATE-RECORD`, `PRE-*`, etc.);
3. DDL constraints (NOT NULL, length, precision, unique, check, foreign keys);
4. UI checklist properties (required, mask, LOV, range, default).

Naming inference is not a source. Never infer required, max length, LOV or mask
from a column or item name. If trigger and DDL conflict, record the conflict and
decide explicitly.

Implement each rule once, in the right layer:

- field shape → Filament field + Laravel rule;
- cross-field or business rule → custom `Rule` object or form `after()` hook;
- invariant that must hold regardless of UI → service/observer plus a database
  constraint when the schema allows it.

### 5. Triggers and program units

Read `references/triggers.md`, `references/forms-builtins.md` and
`references/arquitectura.md`. Classify every trigger:

- `logica` → port to a service, observer, rule, action or query scope;
- `boilerplate_lov` / `boilerplate_nav` → normally `No aplica`; verify there is
  no hidden business logic before skipping;
- Forms-only (`WHEN-WINDOW-*`, `KEY-*` navigation, etc.) → `No aplica` with a
  reason.

Translate PL/SQL constructs deliberately (NVL, DECODE, SYSDATE, sequences, DUAL,
ROWNUM, CONNECT BY, autonomous transactions) and flag anything you cannot
preserve. Copy the original source to `plsql/`.

### 6. UI

Read `references/filament.md`. Build one resource per confirmed entity (or follow
the target profile). Use the field order from the L3 checklist/Forms Builder when
available; otherwise use a business-sensible order and record the assumption.
Use:

- labels from the UI checklist; otherwise humanize the item name and mark the
  assumption;
- the source form language for user-facing text, when evidenced by the checklist
  or messages; otherwise ask;
- the confirmed column for each item (item name is not a column name);
- sections/tabs for block or canvas groupings when known; otherwise a sensible
  layout;
- table columns, search, filters, badges and actions for buttons and LOVs;
- modals/confirmations for destructive or irreversible actions.

Do not put business logic in the resource: call services and rules.

### 7. Messages and errors

Extract every message from `messages`, trigger SQL (`MESSAGE`, `ALERT`,
`RAISE_APPLICATION_ERROR`) and alerts. The IR `messages` list is partial: scan
each trigger and alert definition. Map:

- validation failure → field error or `ValidationException` with the exact text;
- information/warning → Filament notification;
- confirmation → modal or confirm action;
- fatal/technical → log + safe user message, preserving the original code.

Codes without text and texts without code are both valid; keep what exists.

### 8. Tests

Read `references/cobertura-y-tests.md`. Write tests before or with the
implementation:

- one test per ported business rule and validation (pass + fail + exact message);
- observer/service tests for pre/post insert, update and delete behavior;
- authorization tests if the form has roles;
- a smoke test for each resource page if the target uses Filament.

Run the suite and the formatter. Do not finish with failing or skipped tests
without a documented reason.

### 9. Coverage report

Complete `checklist.md`; add `supuestos.md`, `decisiones.md`, `confianza.md` and
the summary counts. Verify:

- every object present in the catalog or the L3 checklist has a row;
- no `PENDIENTE` without a reason and next step;
- every `No aplica` has a justification;
- every ported rule has a test;
- the final report states the input level and confidence.

### 10. Handoff

Deliver a short report: what was ported, what is pending, assumptions, how to run
tests, and the exact files changed. Keep the original catalog and PL/SQL copies in
the repo for auditability.

## Output layout

Adapt paths to the target app, but keep the audit trail:

```text
docs/porting/<MODULE>/
  perfil.md
  checklist.md
  supuestos.md
  decisiones.md
  confianza.md
  plsql/                  # original trigger and program-unit sources
  validaciones.md         # validation matrix
app/
  Models/
  Enums/                  # optional
  Services/<Module>/
  Observers/
  Rules/
  Policies/               # optional
  Filament/Resources/
database/migrations/
database/factories/       # optional
tests/Feature/<Module>/
tests/Unit/               # optional
```

## Definition of done

- [ ] Every object present in the catalog and every object in the L3 checklist is in the coverage file.
- [ ] Every `PENDIENTE` has a reason and next step; every `No aplica` has a justification.
- [ ] Schema/table mapping is confirmed or explicitly pending; no invented columns or timestamps.
- [ ] Every ported validation is implemented once, with the exact message, and tested.
- [ ] Every preserved PL/SQL source is in `plsql/` and referenced by its checklist row.
- [ ] UI language and labels match the profile/source; no Forms chrome.
- [ ] Tests and formatter pass; the report includes commands and results.
- [ ] The final report states input level (L1/L2/L3), confidence and pending items.

## Reference map

| Read | When |
| --- | --- |
| `references/ir-schema.md` | First: sanitized IR v1.0 contract, fields and limitations |
| `references/catalogo.md` | First: file formats, classification, conflicts, warnings |
| `references/entradas-y-confianza.md` | Deciding what can be promised at L1/L2/L3 |
| `references/perfil-de-port.md` | Before coding: target, schema, language, auth, scope |
| `references/validaciones.md` | Building the validation matrix and messages |
| `references/triggers.md` | Mapping Forms triggers to Laravel layers |
| `references/forms-builtins.md` | Translating `:SYSTEM`, `:GLOBAL`, built-ins and PL/SQL constructs |
| `references/bloques-relaciones.md` | Blocks, master-detail, control blocks, keys |
| `references/esquema-oracle.md` | Oracle DDL → migrations and models |
| `references/filament.md` | Resources, forms, tables, actions, UX |
| `references/arquitectura.md` | Services, observers, transactions, structure |
| `references/cobertura-y-tests.md` | Checklist, coverage, test plan, done criteria |

Templates: `assets/checklist-port.md`, `assets/checklist-ui.md`,
`assets/validaciones.md`, `assets/decisiones.md`, `assets/tablas.sql`,
`assets/supuestos.md` and the `assets/*.stub` files.

If the user asks for a quick chat-only port, use `PROMPT.md` instead of the full
skill and warn that the result is L1 at best.
