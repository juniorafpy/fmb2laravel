# Inputs and confidence

The port is only as good as its inputs. This reference defines what you can
promise with each input level, how to label confidence, and what to ask for when
something is missing.

## Input levels

| Level | You have | You can promise | You cannot promise |
| --- | --- | --- | --- |
| **L1** | Catalog only (IR, triggers, program units, conflicts, rules) | Trigger behavior, messages, the blocks/items visible through triggers, a first UI, a data-model outline | A **complete** block/item inventory (blocks/items with no triggers are absent), columns, types, keys, DB constraints, required flags, lengths, masks, LOV queries, labels |
| **L2** | L1 + Oracle DDL or dictionary export | Faithful schema: tables, columns, types, defaults, nullability, PK/FK/unique/check, indexes, comments | Field-level UI properties not present in DDL (required at form level, masks, LOVs, visibility) |
| **L3** | L2 + filled UI checklist | Full block/item list and faithful field properties: required, max length, mask, LOV, default, enabled/visible, hint, label, `Column Name` | Anything outside the checklist, behavior the form does not expose, data migration |

State the level at the top of the final report. If the level is L1, say clearly
that the schema, the inventory and the field validations are partial. Do not use
the phrase "full port" at L1.

The IR derives blocks and items from trigger scopes: a block or item with no
triggers can be invisible. At L1, report the inventory as trigger-derived and ask
for a Forms Builder/JDAPI export or the UI checklist to complete it. Do not claim
100% of the form based on the IR alone.

## Confidence labels

Use these labels in `confianza.md` and, when useful, in the checklist:

| Label | Meaning | Examples |
| --- | --- | --- |
| **Alta** | Direct evidence in the inputs | Trigger source, DDL constraint, human-filled checklist |
| **Media** | Strong inference with a named source | Table confirmed from several trigger queries but no DDL; column type inferred from SQL usage |
| **Baja** | Weak or naming-only inference | `candidateTable` from `B_X`; label humanized from an item name |
| **Pendiente** | No evidence | Required flag, LOV query, message text for a code |

Rules:

- A **Baja** inference that changes behavior must be confirmed before coding, or
  implemented behind a clearly marked assumption with a test.
- A **Pendiente** item is never implemented "by intuition". It is left out and
  listed.
- Confidence is per item, not per module. You can have an Alta trigger and a Baja
  table mapping in the same block.

## Source-by-source confidence

### Catalog trigger source — Alta for behavior, Media for intent

The PL/SQL is complete, but its intent can be ambiguous:

- It may depend on `:SYSTEM`, `:GLOBAL`, `:PARAMETER` or session state.
- It may call a program unit that is missing from the catalog.
- It may contain dead code or commented-out logic.
- It may use Oracle-specific SQL that has no direct Laravel equivalent.

Read `references/forms-builtins.md` and classify every dependency. If a
dependency is missing, the trigger is **Pendiente** for the missing part, not
silently simplified.

### `candidateTable` — Baja until confirmed

The heuristic `B_X` → `X` is useful but not proof. Confirm with at least one of:

- a `CREATE TABLE` in the DDL;
- a `FROM`/`INSERT INTO`/`UPDATE` in a trigger that clearly uses the block;
- a foreign key or relation;
- the user.

If two sources disagree, stop and ask. Do not create two models for one table or
one model for two blocks without documenting it.

### DDL — Alta for schema

The DDL is authoritative for types, nullability, keys, defaults, checks and
indexes. It is not authoritative for form behavior:

- A column can be `NOT NULL` in the database but optional in the form because a
  trigger fills it.
- A form can require a field that the database allows null.
- A form can validate a value that the database does not constrain.

Combine DDL and triggers; do not replace one with the other.

### Screen checklist — Alta if filled by a human, Baja if guessed

The checklist supplies what the catalog does not: item type, required, length,
mask, LOV, default, visible, enabled, hint and label. It must come from Forms
Builder, JDAPI or screenshots. If it is incomplete, mark the missing cells `?`
and treat them as pending.

Never infer `required` from a column name, a `NOT NULL` constraint alone, or an
asterisk in a screenshot without confirming what the asterisk means. Never infer
a mask from a sample value.

### Messages — Alta for presence, Media for meaning

Extracted messages are real strings, but their role is not always clear:

- `MESSAGE('...')` is usually user-facing.
- `ALERT(...)` uses an alert name and a message; the text may be in the alert
  definition, not in the trigger.
- `RAISE_APPLICATION_ERROR(-20xxx, '...')` is user-facing and carries a code.
- A quoted string can be a SQL literal, a column value or a debug message.

Resolve the role from the surrounding code. If a code has no text, keep the code
and mark it pending.

### Classification — Media

`logica` / `boilerplate_lov` / `boilerplate_nav` is a heuristic. It helps triage,
not decide. Always read the source of a boilerplate trigger before skipping it.

## What to ask for

Ask once, in a single message, grouping the questions:

1. **DDL** for every table the module reads or writes. Ask for `CREATE TABLE`,
   constraints, indexes, sequences/triggers and comments. A dictionary export is
   also valid.
2. **Screen checklist** (or screenshots + Forms Builder access) for the fields
   the user cares about: required, length, mask, LOV, default, visible, enabled,
   hint, label.
3. **LOV definitions**: the SQL query, the displayed columns, the return item and
   any validation or filtering. The catalog does not include LOV queries.
4. **Alerts and message table**: the text for numeric message codes and the
   buttons/behavior of each alert.
5. **Roles and users**: how Forms users map to Laravel users, roles and policies,
   or whether auth is out of scope.
6. **Out of scope**: data migration, reports, interfaces, batch jobs, menus.

If the user cannot provide an item, say what it blocks and continue with the rest.
Do not stop the whole port for one missing LOV unless it is central.

## What "unknown" looks like in the deliverables

Unknowns must be visible, not hidden in code:

- `supuestos.md` — every inference, with evidence and impact.
- `checklist.md` — `PENDIENTE` rows with reason and next step.
- `confianza.md` — summary by object/source and the declared level.
- `validaciones.md` — rules with `Fuente` and confidence.
- Final report — a short "Pendiente" list the user can act on.

A port with 20 documented pending items is better than a port with 20 invented
ones. The first can be finished; the second cannot be trusted.
