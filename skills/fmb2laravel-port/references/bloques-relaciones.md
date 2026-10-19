# Blocks and relations

Oracle Forms organizes a module into blocks. A block can be a database block
(backed by a table or view), a control block (buttons, filters, display items) or a
non-database block used as scratch state. The catalog gives you names, items and
triggers, but not the block type, the relation definitions or the item properties.
It also only lists blocks/items that appear in trigger scopes: get the full list
from L3/Forms Builder and add the missing blocks as `PENDIENTE`.

## Block types

| Type | Evidence | Laravel mapping |
| --- | --- | --- |
| Database block | `candidateTable`, SQL in triggers, DDL | Model + migration + resource |
| Control block | No table, buttons/filters only, no DML | Actions, filters, widgets, form state |
| Non-database data block | Items populated by code, no table | DTO/view model; do not create a table |
| Detail block | Master-detail triggers, FK in DDL | Relation manager / nested resource |
| View-backed block | DDL `CREATE VIEW`, read-only triggers | Read-only model/resource |
| Query-only block | `PRE-QUERY` only, no DML | Filter/report, not an editable resource |

`candidateTable` is a heuristic (`B_X` → `X`). It can be wrong or `null`. Confirm
with:

- a `CREATE TABLE` in the DDL;
- a `FROM`/`INSERT`/`UPDATE`/`DELETE` in the block triggers;
- a foreign key or relation;
- the user.

Never create a model for a control block. Never merge two blocks into one table
without documenting it (two blocks can edit different columns of the same table,
or one block can be a read-only lookup).

An item name is not necessarily a column name. Confirm the Forms `Column Name`
from the UI checklist, the DDL or the trigger SQL before writing a field. A table
can also have columns that the block does not expose: include them in the
migration and document who fills them.

### Form-level pseudo-block

`reglas.json` groups form-level triggers and program units under a pseudo-block
named after the form/module (or a form-level placeholder). It is **not** a database
block. Detect form-level triggers in the IR with `block === null`; never create a
model, table or resource for the pseudo-block.

## Master-detail

Forms relations are often defined in the form metadata, which the catalog does not
include. Infer them from:

- `:MASTER.KEY` references in detail triggers;
- `WHEN-NEW-RECORD-INSTANCE` / `WHEN-CREATE-RECORD` setting the FK from the master;
- `PRE-QUERY` / `POST-QUERY` coordination;
- foreign keys in the DDL;
- `:SYSTEM.COORDINATION_OPERATION`;
- the user.

Mapping options:

| Forms behavior | Filament/Laravel |
| --- | --- |
| Master block + detail block on one form | Relation manager on the master resource |
| Detail in a modal | Relation manager modal / nested form |
| Separate windows | Separate page/resource with a filter |
| Coordinated query (`EXECUTE_QUERY` on detail) | Relation query / filter by FK |
| Cascade delete | DB `ON DELETE CASCADE` or explicit service transaction |
| Detail totals | Recompute on the server; accessor/observer |
| Copy FK from master on create | `mutateFormDataBeforeCreate` / service argument |

Do not rely on the client to send the master id. Resolve it on the server and
validate that the child belongs to the master (ownership check).

### Composite keys

Laravel does not support composite primary keys natively. Options:

1. Add a surrogate key (changes the schema; confirm).
2. Use a package that supports composite keys (document the dependency).
3. Use explicit query methods and avoid `find()` on the composite key.

Pick one and test it. Do not pretend a composite key is a single `id`.

### Sequences and identity

- Oracle `seq.NEXTVAL` in a `BEFORE INSERT` trigger → auto-increment, identity,
  UUID or an explicit sequence table.
- `SYS_GUID()` → UUID (confirm the storage type).
- Natural keys → do not add a surrogate key unless the app needs it.
- Preserve the key values in tests.

### Audit columns

Common patterns:

- `CREATED_BY` / `CREATED_AT` / `UPDATED_BY` / `UPDATED_AT`;
- triggers filling them from `USER` or `:GLOBAL`;
- `SYSDATE` defaults.

Map to Laravel timestamps and observers, or to explicit columns if the schema
names differ. Decide whether the values are the DB user, the app user or a
service account; do not guess.

## Control blocks and buttons

A toolbar block is common in Forms (new, save, delete, query, LOV, exit). It is not
a table. Map each button to a Filament action or page action:

- `NUEVO` → Create page/action;
- `GRABAR` → Save (transaction);
- `BORRAR` → Delete with confirmation;
- `BUSCAR` / `CONSULTA` → table filters/search;
- `LOV` → Select/relationship;
- `SALIR` → redirect/logout;
- `ACERCADE` → static modal/page;
- `AYUDA` → documentation/help page.

If a toolbar trigger contains business logic (for example a custom save that
validates and posts), it belongs in a service, not in the toolbar mapping.

## Multiple blocks and navigation

- One resource per confirmed entity is the default. If a form edits a header and
  lines, use one resource with a relation manager.
- If a form edits unrelated entities in tabs, use separate resources or a custom
  page that composes them.
- `GO_BLOCK`/`GO_ITEM` navigation is `No aplica`; do not reproduce focus jumps.
- If a block is only a filter (query block), map it to a filter form, not a model.
- If a block is a display-only summary, map it to an infolist/widget/accessor.
- If two blocks edit the same table, decide whether they become one resource with
  scopes or two resources; document the choice.

## LOVs and record groups

The catalog does not include LOV definitions. For each LOV you need:

- the SQL query (tables, filters, ordering);
- the displayed columns;
- the return item(s);
- validation (must the value exist? can it be null?);
- whether the LOV is static or dynamic;
- the behavior when no rows match.

Map to:

- `Select::relationship()` for a simple FK;
- `Select` with options for a static list;
- a searchable relation for a large lookup;
- an `exists` rule on the server;
- a custom action/modal for complex multi-column LOVs.

If the LOV is missing, mark the field `PENDIENTE` and do not replace it with free
text.

## Views, synonyms and read-only data

- `CREATE VIEW` → read-only model, no migrations, no writes. Add a resource only
  if the form edits or displays it.
- Synonym → resolve the underlying object; if unknown, mark pending.
- Materialized view → read-only; refresh is a separate concern.
- External tables / DB links → mark pending; the data source is outside the port.

## Delete rules

Forms deletion can be:

- a physical delete (`DELETE_RECORD`);
- a logical delete (a status/flag column);
- a cascade defined in the DB;
- a guard in `PRE-DELETE` (cannot delete if children exist);
- a soft delete with audit.

Read the trigger and the DDL. Do not assume soft deletes. If the form physically
deletes rows, a Laravel `SoftDeletes` model changes behavior; confirm it.

## UI mapping

| Forms layout | Modern UI |
| --- | --- |
| Single-record block | Form page/resource |
| Multi-record block (grid) | Table with pagination |
| Tab canvas | Tabs/sections |
| Stacked canvas | Sections/columns |
| Window/modal | Modal/page |
| Toolbar | Header actions |
| Display item | Infolist/placeholder |
| Checkbox/radio | Toggle/radio |
| List item | Select |
| Editor | Textarea/rich text |
| Image item | File upload/avatar |
| Chart item | Chart widget (if data source is known) |

Do not copy coordinates. Build a responsive layout that fits the app. If the
screen checklist includes grouping, use it; otherwise use sections that match the
business meaning.

## Relations checklist

For every relation:

- [ ] Master table and detail table confirmed.
- [ ] FK columns confirmed (DDL or trigger SQL).
- [ ] Ownership/tenant scope defined.
- [ ] Create/update flow sets the FK on the server.
- [ ] Delete behavior (cascade/guard/soft delete) confirmed.
- [ ] Totals/derived values are recomputed on the server.
- [ ] Relation query has tests (including empty and unauthorized cases).
