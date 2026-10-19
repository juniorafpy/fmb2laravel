# Validations and messages

A Forms module validates in three places: item properties, trigger code and the
database. A faithful port combines all three. The catalog gives you the triggers;
the DDL gives you the schema; the screen checklist gives you the item properties.
Missing inputs become `PENDIENTE`, never guesses.

## Sources and confidence

| Source | What it gives | Confidence | Caution |
| --- | --- | --- | --- |
| Trigger source (`WHEN-VALIDATE-ITEM`, `WHEN-VALIDATE-RECORD`, `PRE-*`) | Business rules, cross-field checks, messages | Alta for behavior | May depend on `:SYSTEM`, `:GLOBAL`, alerts or missing program units |
| Oracle DDL | `NOT NULL`, length, precision, `UNIQUE`, `CHECK`, foreign keys, defaults | Alta for schema | A DB constraint is not always a form requirement, and vice versa |
| Screen checklist | Required, type, length, mask, LOV, range, default, visible, hint | Alta if filled by a human | Incomplete checklists are normal; mark unknown cells |
| Item/column name | Nothing reliable | Baja | Never infer required, length, LOV or mask from a name |
| `candidateTable` | Possible table for a block | Baja | Confirm with DDL or SQL |

Order of precedence when sources conflict:

1. Explicit user confirmation.
2. Trigger behavior (the form is the behavioral source).
3. DDL (the data is the persistence source).
4. Screen checklist (the UI source).
5. Naming inference (never sufficient alone).

If trigger and DDL conflict (for example the form allows null but the column is
`NOT NULL`), do not pick one silently. Record the conflict, implement the stricter
behavior only if the data proves it, and add a test. The safest default is to keep
both layers: UI validation for the form rule, DB constraint for integrity, and a
documented note when they differ.

## The validation matrix

Create `docs/porting/<module>/validaciones.md` with one row per rule. The example
below is illustrative: replace every value and every message with the exact
source data. Do not mark a row `Portado` until it is tested.

```markdown
| ID | Objeto | Regla | Fuente | Evidencia | Laravel/Filament | Mensaje | Test | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| V-001 | `<B_....>` | Required when `<condition>` | `<trigger type>` | `<pid>` | `required_if` + `in` | `<exact message>` | `<test name>` | PENDIENTE |
| V-002 | `<B_....>` | `<cross-field rule>` | `<trigger type>` | `<pid>` | custom Rule | `<exact message>` | `<test name>` | PENDIENTE |
| V-003 | `<B_....>` | Unique | DDL `<constraint>` | `tablas.sql` | `unique` + DB unique | `<exact message>` | `<test name>` | PENDIENTE |
| V-004 | `<B_....>` | Must exist | LOV `<name>` | `<query>` | `exists` | `<exact message>` | `<test name>` | PENDIENTE |
```

`Fuente` must name the exact source (trigger PID, DDL constraint, checklist row,
LOV definition or user confirmation). A row without evidence is not a rule.

All rows assume the item's **column** is confirmed. An item name is not
necessarily a column name; use the Forms `Column Name` from the UI checklist, the
DDL or the trigger SQL. If it is unknown, mark the mapping `PENDIENTE` before
writing the rule.

## Forms item properties → Laravel/Filament

The property names are the usual Forms Builder names. Match them to the
checklist; if the checklist uses different names, keep the meaning.

| Forms property / behavior | Laravel / Filament | Notes |
| --- | --- | --- |
| Required = Yes | `->required()` | Conditional required needs `required_if` or a custom rule |
| Required = No | no `required` | Do not add `nullable()` blindly; decide empty-string vs null |
| Datatype `CHAR(n)` | `string` + `max:n` | Trim or preserve spaces? Check the trigger |
| `VARCHAR2(n)` | `string` + `max:n` | `n CHAR` vs `n BYTE` matters for multibyte data |
| `NUMBER(p,s)` | Laravel rule `decimal:s`/`decimal:0,s` + `numeric`; migration `$table->decimal('col', p, s)` | Money and quantities need explicit precision |
| `NUMBER(p)` | `integer`/`bigInteger` + `numeric` | Confirm scale 0 |
| `NUMBER` without precision | `decimal` (high precision) | Do not assume integer |
| `INTEGER` | `integer` | |
| `DATE` (Oracle) | `dateTime`/`datetime` | Oracle `DATE` includes time; do not map to date-only |
| `TIMESTAMP` | `timestamp`/`datetime` | Timezone policy must be in the profile |
| `BOOLEAN` | `boolean` | Forms checkboxes are often `CHAR(1)` `S`/`N`; inspect |
| `CLOB` / `LONG` | `longText` | Size limits and search behavior |
| `BLOB` / `RAW` | `binary`/file storage | Decide disk vs DB; usually a file upload |
| `XMLTYPE` | `json`/`longText` | Flag: no native equivalent |
| `ROWID` | none | Do not model; mark pending |
| LOV | `Select` + `relationship`/options + `exists` | The catalog does not include LOV queries |
| Static list | `Select` + `in:` or enum | Keep the stored codes, not the labels |
| Range Low/High | `between:min,max` / `min:`/`max:` | Dates use `after_or_equal`/`before_or_equal` |
| Max length | `max:n` | Comes from DDL or checklist, not from the name |
| Format mask | `date_format:`/`regex:`/`numeric` | A mask is input shape, not business validation |
| Initial value | `->default(...)` | `SYSDATE` → `now()`; literals as-is |
| Visible = No | `->hidden()` or omit | If it must still submit, use `->hidden()` carefully |
| Enabled = No | `->disabled()` / `->readOnly()` | Disabled fields are not dehydrated by default; decide |
| Hint / Tooltip | `->helperText(...)` | Preserve the source text if available |
| Prompt / Label | `->label(...)` | Checklist label wins; otherwise humanize + assumption |
| Multi-line | `Textarea` | |
| Display-only item | `Placeholder`/infolist/accessor | Do not create a column for it |
| Button | `Action` | Business logic goes to a service |
| Checkbox | `Toggle`/`Checkbox` | Map the stored values explicitly (`S/N`, `1/0`, `Y/N`) |
| Radio group | `Radio` | |
| List item | `Select` | |

## Masks

Masks are formatting/input constraints. Do not turn a mask into the only
validation; combine it with type, length and business rules.

| Oracle mask (examples) | Laravel approach | Caveat |
| --- | --- | --- |
| `999` | `integer`/`numeric` + digits rule | Leading zeros are lost unless stored as string |
| `999.99` | `numeric` + `decimal:0,2` | Decimal separator is locale-dependent |
| `999G999G999` | `numeric` (no group validation) | Group separators are display-only |
| `$999.99` | `numeric` + prefix/suffix | Do not store the currency symbol unless the schema does |
| `DD/MM/YYYY` | `date_format:d/m/Y` | Oracle `DATE` includes time |
| `DD-MON-YYYY` | `date_format:d-M-Y` | Month abbreviations are locale-dependent; prefer a format the app controls |
| `YYYY-MM-DD` | `date_format:Y-m-d` | |
| `HH24:MI` | `date_format:H:i` | |
| `A999` / `LL999` | `regex:/^[A-Za-z][0-9]{3}$/` (adapt) | Build the regex from the actual mask |
| `FM...` | trim/normalize | `FM` affects display, not the stored value |

When the mask is unknown, do not invent one. Use type + length from the DDL and
mark the mask `PENDIENTE` in the checklist.

## Layers: implement once, protect everywhere

A rule can appear in several layers. Avoid duplicating logic and messages:

1. **UI field** — shape and immediate feedback (type, max length, required,
   `in`, `exists`). This is the friendly layer.
2. **Custom `Rule` object** — cross-field or business rule shared by Filament,
   FormRequest and API. It carries the exact message.
3. **Service/observer** — invariant that must hold regardless of the entry point
   (imports, commands, API). Use transactions and `lockForUpdate` when needed.
4. **Database** — PK/FK/unique/check/not-null as the last line of defense. Use it
   when the schema supports it and the DDL has it.

The message lives in one place (the rule or the form), not copied into five
places. If the database raises a different message, map it to the canonical one
only when that is safe; otherwise log and show a generic error.

## Filament patterns

Field-level:

```php
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

TextInput::make('code')
    ->label('Código')
    ->required()
    ->maxLength(10)
    ->rules(['alpha_num'])
    ->validationMessages([
        'required' => 'El código es obligatorio.',
        'max' => 'El código no puede superar 10 caracteres.',
    ]);

Select::make('customer_id')
    ->label('Cliente')
    ->relationship('customer', 'name')
    ->searchable()
    ->preload()
    ->required()
    ->exists('customers', 'id');
```

Cross-field (the rule reads the whole form state):

```php
use Filament\Schemas\Components\Utilities\Get;
use Filament\Forms\Components\DatePicker;

DatePicker::make('end_date')
    ->required()
    ->rule(fn (Get $get) => new EndDateAfterStart($get('start_date')));
```

Dynamic required/visible:

```php
TextInput::make('reason')
    ->required(fn (Get $get): bool => $get('status') === 'C')
    ->visible(fn (Get $get): bool => $get('status') === 'C');
```

Display-only vs disabled:

```php
TextInput::make('total')
    ->disabled()
    ->dehydrated(false); // derived value, not user input
```

Use `dehydrated(false)` for anything the user must not submit, especially totals,
descriptions and read-only lookups. A disabled field is not a security boundary:
validate the authoritative value on the server.

## Custom rules

Use a dedicated class when the rule is reused, has a complex condition, or must
produce the exact Forms message.

```php
namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

final class ValidStatusTransition implements ValidationRule
{
    public function __construct(
        private readonly ?string $from,
        private readonly string $to,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $allowed = [
            'N' => ['A', 'C'],
            'A' => ['P', 'C'],
            'P' => ['C'],
            'C' => [],
        ];

        if (! in_array($value, $allowed[$this->from] ?? [], true)) {
            $fail('Transición de estado no permitida.');
        }
    }
}
```

Add a unit test for the rule and a feature test for the field that uses it.

## Record-level and cross-field validation

`WHEN-VALIDATE-RECORD` and `WHEN-VALIDATE-ITEM` triggers that read several items
are usually one of these:

- date ordering (`start <= end`, `end >= sysdate`);
- conditional required (status A requires a reason);
- totals (sum of lines equals header total);
- consistency between a code and its description;
- state transitions;
- duplicate checks across a natural key.

Implement them as:

- a custom rule that receives the relevant values, used by the form; and/or
- a form `after()` validation hook; and/or
- a service method that re-checks before persisting.

For totals and derived fields, recompute on the server. Do not trust a hidden
input or a disabled field.

## Unique, foreign keys and concurrency

- **Unique**: use a DB unique constraint when the DDL has one, plus a validation
  rule with a clear message. Scope it to the same columns (and tenant, if any).
  Case sensitivity matters: Oracle comparisons are case-sensitive by default;
  decide whether to normalize.
- **Foreign keys**: `exists:table,column` plus a DB FK when the DDL has one. A
  `Select::relationship()` does not replace server-side validation.
- **Composite keys**: Laravel does not support composite primary keys natively.
  Choose a strategy (surrogate key, package, or explicit query methods), document
  it, and test it.
- **Concurrency**: Forms `SELECT ... FOR UPDATE`, `COMMIT_FORM` and optimistic
  checks must become transactions/locks. A last-write-wins update is a behavior
  change; document it.
- **Race conditions**: a unique rule can still race with another request. The DB
  constraint is the final authority; catch the violation and map it to the
  user-facing message.

## Messages

Messages are part of the user contract. Preserve the exact text and code.

### Sources

- `MESSAGE('...')` — usually an informational message.
- `ALERT(...)` / `SHOW_ALERT` — an alert with buttons; the text may be in the
  alert definition, not the trigger.
- `RAISE_APPLICATION_ERROR(-20xxx, '...')` — an error with a code.
- `RAISE FORM_TRIGGER_FAILURE` — abort the current operation after showing a
  message.
- IR `messages` — extracted strings; may include codes or fragments.

### Mapping

| Forms construct | Laravel/Filament |
| --- | --- |
| Field validation failure | `ValidationException` / field error with exact text |
| `MESSAGE` information | `Notification::make()->title(...)->success()/info()` |
| `MESSAGE` warning | notification `warning()` |
| `ALERT` with OK | notification or modal with the same text |
| `ALERT` with OK/Cancel | confirmation modal; only proceed on confirm |
| `RAISE_APPLICATION_ERROR` | `ValidationException` or domain exception; keep code in context/log |
| `FORM_TRIGGER_FAILURE` | abort the operation; do not catch and continue |
| Technical/SQL error | log the original code/message; show a safe generic message |

Rules:

- Do not translate or rewrite a stored message.
- Do not show raw SQL/Oracle errors to end users.
- If the message is dynamic (`'Error: ' || SQLERRM`), keep the stable prefix and
  log the technical detail; decide what the user sees.
- If only a code is available, keep the code and mark the text `PENDIENTE`.
- If the same message appears in several triggers, centralize it without changing
  the text.

### Codes

Keep codes in logs, exception context or a `code` attribute. They are useful for
support even when the UI shows only text. If the app has a message table, map
codes to texts there; if not, do not invent one.

## Anti-patterns

- `required` inferred only from `NOT NULL` (or the reverse).
- Max length copied from `NUMBER` precision or from a sample value.
- LOV replaced by a free-text input.
- Date mask used as the only date validation.
- Unique rule without a DB constraint (or the reverse) and no race handling.
- Validation only in Filament (the API/command path bypasses it).
- Disabled fields trusted as read-only.
- Messages paraphrased, translated or stripped of codes.
- `catch (Throwable $e) {}` after a trigger failure.
- Business rules in the resource instead of a shared rule/service.
- Inventing a table, column or relation to make the form "work".

## Tests

For every **ported** rule (and for a pending rule, state which input is missing
and which test is blocked):

- one passing case;
- one failing case;
- the exact message (assert it, not just the exception);
- edge cases the trigger handles (null, empty string, zero, boundary dates,
  case differences, duplicate on update).

For observers/services, test the side effects and the transaction rollback. For
database constraints, test that the violation is handled, not only that the
constraint exists.
