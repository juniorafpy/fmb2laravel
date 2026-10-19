# Oracle schema → Laravel

The catalog does not contain the schema. Use the Oracle DDL (or a dictionary
export) as the source of truth for tables, columns, types, keys, constraints,
defaults and indexes. Without DDL, you can only produce an outline (L1), not a
faithful schema.

## DDL sources

Accept any of:

- `CREATE TABLE` / `ALTER TABLE` scripts;
- an export from a database tool (SQL Developer, PL/SQL Developer, Toad);
- dictionary queries (`USER_TABLES`, `USER_TAB_COLUMNS`, `USER_CONSTRAINTS`,
  `USER_CONS_COLUMNS`, `USER_INDEXES`, `USER_IND_COLUMNS`, `USER_TAB_COMMENTS`);
- a schema dump with comments and grants.

Ask for the DDL of every table the module reads or writes, plus sequences,
triggers and views. If only some tables are available, port those and mark the
rest pending.

## Type mapping

| Oracle | Laravel migration | Eloquent cast | Notes |
| --- | --- | --- | --- |
| `VARCHAR2(n)` | `string('col', n)` | `string` | `n BYTE` vs `n CHAR` affects multibyte length |
| `NVARCHAR2(n)` | `string('col', n)` | `string` | Unicode |
| `CHAR(n)` | `char('col', n)` | `string` | Oracle pads with spaces; trim deliberately |
| `NUMBER(p,s)` | `decimal('col', p, s)` | `decimal:s` | Money/quantities; confirm rounding |
| `NUMBER(p)` | `integer`/`bigInteger` or `decimal(p,0)` | `integer`/`decimal:0` | Depends on p |
| `NUMBER` | `decimal('col', 38, 10)` (or string) | `decimal` | Do not assume integer |
| `INTEGER` | `integer`/`bigInteger` | `integer` | |
| `FLOAT` / `BINARY_FLOAT` | `float`/`double` | `float` | Precision loss; confirm |
| `DATE` | `dateTime('col')` | `datetime` | **Oracle DATE includes time** |
| `TIMESTAMP` | `timestamp('col')` | `datetime` | |
| `TIMESTAMP WITH TIME ZONE` | `timestampTz` (driver-dependent) | `datetime` | Store UTC and document |
| `CLOB` / `LONG` | `longText('col')` | `string` | Size/search implications |
| `BLOB` / `RAW` | `binary('col')` or file storage | `string`/custom | Prefer disk for large files |
| `BOOLEAN` (23c) | `boolean('col')` | `boolean` | Older Oracle uses `CHAR(1)` |
| `XMLTYPE` | `json`/`longText` | `array`/`json` | Semantic loss; flag |
| `ROWID` | none | none | Do not model; pending |
| `INTERVAL` | none | none | Flag |
| `SDO_GEOMETRY` | none/JSON | custom | Spatial; pending |

Always check the target database. If production is Oracle and tests are SQLite,
the migration must run on both or be gated by driver. Do not use SQLite-only
features in a shared migration.

## Identifiers and case

Oracle unquoted identifiers are stored uppercase and are case-insensitive.
Laravel commonly uses lowercase `snake_case`.

Choose one strategy:

1. **Preserve**: table/column names exactly as in Oracle (uppercase), quoted when
   needed. Best for an existing Oracle database and for traceability. Be careful
   with SQLite/MySQL tests and with Laravel's assumptions.
2. **Map**: lowercase `snake_case` names with an explicit mapping. Best for a new
   schema or a Laravel-native app. Document every renamed table/column.

Do not mix strategies. If a table is `ORDERS` and the model uses `orders`, make
sure the connection's case handling is correct. Add a test that queries the real
table.

Oracle reserved words (`ORDER`, `DATE`, `NUMBER`, `COMMENT`, `LEVEL`, `SIZE`,
`ACCESS`, `AUDIT`, `RESOURCE`, `SESSION`, `USER`, `GROUP`, `START`, `END`, …) may
need quoting. Prefer mapping to a safe name if the schema allows it; otherwise
quote consistently and test.

Identifier length: 30 bytes before Oracle 12.2, 128 after. If a name must be
shortened, record the mapping.

## Keys and sequences

- Single-column PK → `$table->id()` only if the column is an auto-increment
  integer; otherwise `primary('col')` with the right type.
- Natural PK (string/number) → `primary('col')`; do not add a surrogate key unless
  the app needs it.
- Composite PK → no native Eloquent support. Choose a surrogate, a package or
  explicit query methods. Document the choice.
- Oracle sequence + `BEFORE INSERT` trigger → auto-increment/identity, UUID or an
  explicit sequence table. If other systems share the sequence, preserve it.
- `SYS_GUID()` → UUID (`uuid` column/`HasUuids`), if the storage matches.
- Foreign keys → `foreignId(...)->constrained(...)` only when the column type and
  table names match; otherwise `foreign(...)->references(...)->on(...)`.

## Constraints

| Oracle | Laravel |
| --- | --- |
| `NOT NULL` | `->nullable(false)` (default) / validation `required` |
| `PRIMARY KEY` | `$table->primary(...)` |
| `UNIQUE` | `$table->unique(...)` |
| `FOREIGN KEY` | `$table->foreign(...)->references(...)->on(...)` |
| `CHECK` | Laravel has no generic builder method; use a raw statement when the driver supports it, or enforce in a rule/service |
| `DEFAULT` | `->default(...)`; `SYSDATE` → `useCurrent()` (timezone caveat) |
| `ON DELETE CASCADE` | `->cascadeOnDelete()` |
| `ON DELETE SET NULL` | `->nullOnDelete()` |

A DB constraint is not a replacement for a good message, and a validation rule is
not a replacement for integrity. Use both when the source has both.

## Defaults and audit columns

| Oracle default | Laravel |
| --- | --- |
| Literal | `->default('...')` |
| `SYSDATE` / `CURRENT_DATE` | `->useCurrent()` or app `now()`; decide timezone |
| `USER` | `auth()->id()` in an observer, or a DB user mapping; do not fake it |
| `SYS_GUID()` | UUID |
| Sequence `.NEXTVAL` | auto-increment/UUID/sequence service |

Audit columns (`CREATED_BY`, `UPDATED_BY`, `CREATED_AT`, `UPDATED_AT`) must be
filled consistently. If Forms used `USER` (the Oracle user) and the web app uses an
application user, the values are not equivalent. Document the mapping.

An item name is not necessarily a column name. Confirm the Forms `Column Name`
from the UI checklist, the DDL or the trigger SQL before writing a field. A table
can also have columns that the block does not expose: include them from the DDL
and document who fills them (default, trigger, service or external process). Do
not add `created_at`/`updated_at` or other audit columns unless the DDL or the
target profile has them.

## Views, synonyms and read-only objects

- A view has no migration. Create a model with `$table = 'VIEW_NAME'`, but do not
  expose create/edit/delete actions; enforce read-only in the resource and policy.
  `$guarded` is not a read-only guard.
- A synonym points to another object. Resolve it; if the target is outside the
  schema, mark pending.
- Materialized views and external tables are read-only and may need refresh jobs.
- Do not create a table when the source is a view.

## Migrations

A migration should be:

- reversible (`down()` drops what `up()` creates, in the right order);
- driver-aware when types differ;
- explicit about keys, indexes and defaults;
- free of data migration unless requested;
- tested on the target connection (or at least on the test connection).

Example:

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->string('code', 10)->unique();
    $table->foreignId('customer_id')->constrained('customers');
    $table->decimal('total', 12, 2)->default(0);
    $table->dateTime('ordered_at');
    $table->string('status', 1)->default('N');
    // $table->timestamps(); // only if the DDL/app convention has them

    $table->index(['status', 'ordered_at']);
});
```

If you preserve Oracle names:

```php
Schema::create('ORDERS', function (Blueprint $table) {
    $table->decimal('ORDER_ID', 10, 0)->primary();
    $table->string('CODE', 10)->unique();
    // ...
});
```

Do not mix the two styles in the same module.

## Models

- Set `$table`, `$primaryKey`, `$keyType`, `$incrementing` when they differ from
  Laravel defaults.
- Cast dates, decimals, booleans, arrays/JSON and enums explicitly.
- Define relations with the correct foreign/local keys.
- Use `$fillable` deliberately; avoid `$guarded = []` on models with sensitive
  columns.
- Add scopes for recurring filters (`PRE-QUERY` behavior).
- Keep accessors for derived/display values; keep persistence logic in services
  and observers.

## Oracle-specific traps

- **Empty string is NULL.** Oracle treats `''` as `NULL`. A Laravel empty string
  may become `NULL` on Oracle. Decide the semantics for optional text fields and
  test it.
- **DATE has time.** A "date" field may carry `00:00:00` or a real time. Do not
  truncate without checking.
- **CHAR pads.** `CHAR(10)` values come back padded; trim only when the source
  does.
- **NUMBER precision.** `NUMBER` without precision can hold huge values; mapping
  to float loses precision. Use decimal/string.
- **Case sensitivity.** Oracle comparisons are case-sensitive; `UPPER()` in a
  trigger is behavior. Reproduce it or normalize deliberately.
- **Sequences are not auto-increment.** If the sequence is shared, an
  auto-increment column changes the contract.
- **Triggers.** Oracle `BEFORE INSERT`/`AFTER UPDATE` triggers may contain logic
  that is not in the Forms catalog. Ask for the DDL triggers too.
- **Virtual columns.** Map to generated columns or accessors; do not insert into
  them.
- **Invisible columns.** `SELECT *` does not return them; check the DDL.
- **Partitioning, VPD/RLS, DB links.** Out of scope unless specified; mark pending.

## Schema checklist

- [ ] Every confirmed table has a migration and a model.
- [ ] Every item→column mapping is confirmed or explicitly pending.
- [ ] Types, lengths, precision, nullability and defaults match the DDL.
- [ ] PK/FK/unique/check/indexes match the DDL (or the difference is documented).
- [ ] Sequences/identity/UUID behavior is preserved.
- [ ] Audit columns are filled consistently; none are invented.
- [ ] Columns not exposed by the block are included and documented.
- [ ] Views are read-only and have no migrations.
- [ ] Names/case strategy is applied everywhere.
- [ ] Migrations run on the target/test connection.
- [ ] No data migration unless requested.
