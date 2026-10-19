# Triggers and program units

Forms triggers are event handlers. Your job is to preserve their **behavior**, not
their syntax. Map each trigger to the Laravel layer that owns that behavior, and
record every trigger in the checklist even when it is not ported.

## Principles

1. Read the trigger source before classifying it. `classification` is a hint.
2. Identify the event, the items/tables it touches, the messages it raises and the
   program units it calls.
3. Preserve the order of operations and the failure semantics.
4. Keep the original PL/SQL in `plsql/` and link it from the checklist.
5. If a dependency is missing, port the rest and mark the dependency `PENDIENTE`.
6. Never leave a trigger unmentioned.

## Destinations

| Destination | Use for |
| --- | --- |
| Filament field rule / custom `Rule` | Field and cross-field validation |
| Form `after()` hook | Record-level validation that needs the whole form state |
| Service action | Button logic, multi-step operations, transactions |
| Observer (`creating`, `created`, `updating`, `updated`, `deleting`, `deleted`) | Persistence side effects and invariants |
| Query scope / filter | `PRE-QUERY` behavior |
| Accessor / computed field | `POST-QUERY` derived values |
| Enum + transition map | Status changes |
| Notification / modal | `MESSAGE` / `ALERT` |
| Scheduler / queued job | `WHEN-TIMER-EXPIRED`, batch work |
| `No aplica` | Forms navigation, windows, boilerplate with no business effect |

## Trigger map

The list is not exhaustive; if a trigger is not here, classify it by its event and
record the decision. Forms versions differ: only port what is present.

### Form-level

| Trigger | Typical purpose | Destination | Notes |
| --- | --- | --- | --- |
| `PRE-FORM` | Session/bootstrap before the form | Middleware/service provider; often `No aplica` | May set globals or open a connection |
| `POST-FORM` | Cleanup on exit | `No aplica` / terminate handler | Do not emulate Forms exit |
| `WHEN-NEW-FORM-INSTANCE` | Initial defaults, navigation, first query | Resource `mount()`, page init, service | Keep side effects idempotent; do not run queries on every render |
| `WHEN-FORM-NAVIGATE` | Validate before leaving the form | Unsaved-changes guard / form validation | |
| `KEY-CLRFRM` | Clear the form | Reset action | |
| `KEY-EXIT` | Exit | Logout/redirect or `No aplica` | |
| `KEY-COMMIT` | Commit | Transaction boundary | Prefer explicit save actions |
| `KEY-EXEQRY` / `KEY-ENTQRY` | Execute/enter query | Table search/filter | |
| `WHEN-TIMER-EXPIRED` | Periodic work | Scheduler, queued job, polling | Choose by purpose; do not fake a timer in the request |
| `ON-ERROR` / `ON-MESSAGE` | Error/message interception | Exception handler, logging | Preserve user-safe text |
| `WHEN-WINDOW-*` | Window events | `No aplica` | Forms chrome |

### Block-level

| Trigger | Typical purpose | Destination | Notes |
| --- | --- | --- | --- |
| `PRE-QUERY` | Add filters / restrict rows | Query scope / Filament filter | Reproduce the exact conditions |
| `POST-QUERY` | Derive display values from fetched rows | Accessor / `after` fetch | Do not write to the DB here |
| `PRE-INSERT` | Defaults, validation, key generation | Observer `creating` / service | Runs before the insert |
| `POST-INSERT` | Side effects after insert | Observer `created` / `afterCommit` | Avoid side effects before commit |
| `PRE-UPDATE` | Validation, audit fields | Observer `updating` / service | |
| `POST-UPDATE` | Side effects after update | Observer `updated` | |
| `PRE-DELETE` | Deletion guards | Observer `deleting` / policy | Preserve the exact block message |
| `POST-DELETE` | Cleanup / cascade | Observer `deleted` / DB cascade | Confirm cascade behavior |
| `ON-INSERT` | Custom insert; replaces the default DML | Service/observer; often `PENDIENTE` | Reproduce the exact DML, key generation and failure semantics |
| `ON-UPDATE` | Custom update | Service/observer; often `PENDIENTE` | Do not map blindly to `updating` |
| `ON-DELETE` | Custom delete | Service/observer; often `PENDIENTE` | Preserve cascade/guard behavior |
| `ON-LOCK` | Custom row locking | `lockForUpdate()` in a transaction | Preserve the concurrency contract |
| `ON-CHECK-DELETE-MASTER` | Master-detail delete guard | Observer/policy/DB FK | 9i+; confirm the delete rule |
| `ON-POPULATE-DETAILS` | Populate a detail block | Relation manager/query | 9i+; preserve filters and coordination |
| `ON-CLEAR-DETAILS` | Clear a detail block | Relation manager/reset | 9i+; preserve coordination |
| `WHEN-NEW-BLOCK-INSTANCE` | Block initialization | Page mount / table setup | |
| `WHEN-NEW-RECORD-INSTANCE` | Defaults for a new record | Create action defaults / form defaults | |
| `WHEN-CREATE-RECORD` | Create defaults | Create action | |
| `WHEN-CLEAR-BLOCK` | Reset | Reset action | |
| `WHEN-VALIDATE-RECORD` | Record-level validation | Form `after()` / service | Often the most important trigger |
| `WHEN-DATABASE-RECORD` | Fetched record adjustments | Accessor / resource hydration | |
| `KEY-CREREC` | Create record | Create action | |
| `KEY-DELREC` | Delete record | Delete action + confirmation | |
| `KEY-DUPREC` | Duplicate record | Replicate action | Copy only intended fields |
| `KEY-COMMIT` | Commit | Save action + transaction | |
| `KEY-EXEQRY` / `KEY-ENTQRY` / `KEY-CQUERY` / `KEY-COUNT-QUERY` | Query control | Table search/filter/count | |
| `KEY-NXTBLK` / `KEY-PRVBLK` / `KEY-NXTREC` / `KEY-PRVREC` / `KEY-NXTSET` / `KEY-PRVSET` | Navigation | `No aplica` | Verify no hidden business logic |
| `KEY-LISTVAL` | LOV | `Select`/relationship | See LOV notes |
| `KEY-UPDREC` | Update record | Edit action | |
| `KEY-OTHERS` | Catch-all key handling | `No aplica` unless it contains logic | |

`ON-INSERT`, `ON-UPDATE` and `ON-DELETE` replace the default DML; they are not
equivalent to an empty observer. If present, read the source, preserve the exact
SQL, key generation, transaction and failure behavior, and test it. If the intent
cannot be reproduced, keep the original PL/SQL and mark it `PENDIENTE`.

### Item-level

| Trigger | Typical purpose | Destination | Notes |
| --- | --- | --- | --- |
| `WHEN-VALIDATE-ITEM` | Field/business validation | Custom `Rule` / field rules | Preserve exact messages |
| `WHEN-NEW-ITEM-INSTANCE` | Focus/default behavior | `afterStateUpdated`/mount | Often navigation; verify |
| `WHEN-BUTTON-PRESSED` | Button action | Filament `Action` → service | Confirm destructive actions |
| `WHEN-CHECKBOX-CHANGED` | Toggle logic | `live()` + `afterStateUpdated` | |
| `WHEN-RADIO-CHANGED` | Option logic | `live()` + `afterStateUpdated` | |
| `WHEN-LIST-CHANGED` | List selection logic | `live()` + `afterStateUpdated` | |
| `KEY-LISTVAL` | LOV | `Select`/relationship | |
| `KEY-NEXT-ITEM` / `KEY-PREV-ITEM` | Navigation | `No aplica` | Can hide defaults/validation; read the source |
| `WHEN-MOUSE-*` | Mouse UI | `No aplica` / hover/focus | |
| `WHEN-CUSTOM-ITEM-EVENT` | Java bean/custom event | `PENDIENTE` | Requires the bean contract |

## Patterns

### Field validation

```php
TextInput::make('status')
    ->required()
    ->rules([new ValidStatus()])
    ->validationMessages([
        'required' => 'El estado es obligatorio.',
    ]);
```

If the trigger sets other items (for example a description from a lookup), use
`afterStateUpdated` for the UI and re-resolve the value in the service before
persisting. Do not trust the client-side lookup.

### Record validation in a service/observer

```php
public function saving(Order $order): void
{
    if ($order->status === 'C' && $order->closed_at === null) {
        throw ValidationException::withMessages([
            'status' => 'La fecha de cierre es obligatoria.',
        ]);
    }
}
```

Use a domain exception if the caller is not a form; map it to a user message at
the edge.

### Button action

```php
Action::make('approve')
    ->requiresConfirmation()
    ->action(fn (Order $record) => app(ApproveOrder::class)->handle($record));
```

The service owns the transaction, validation and side effects. The resource only
collects input and presents the result.

### `PRE-QUERY`

```php
public function scopeVisibleTo(Builder $query, User $user): Builder
{
    return $query->where('company_id', $user->company_id);
}
```

If the trigger builds a dynamic `WHERE`, reproduce it with query builder
conditions, not raw SQL concatenation. Test the generated results, not the SQL
string.

### `POST-QUERY`

```php
protected function total(): Attribute
{
    return Attribute::get(fn () => $this->lines->sum('amount'));
}
```

If the value is stored by the form, decide whether it remains stored or becomes
derived. A derived value changes the schema contract; document it.

### Transactions and locks

```php
DB::transaction(function () use ($order) {
    $locked = Order::query()->lockForUpdate()->findOrFail($order->id);
    // ...
});
```

`COMMIT_FORM` is a transaction boundary, not a per-field save. Map the whole
logical operation.

### Messages and alerts

```php
Notification::make()
    ->title('El archivo fue generado.')
    ->success()
    ->send();
```

For alerts with choices, use a confirmation modal. For `FORM_TRIGGER_FAILURE`,
abort the operation; do not swallow the exception.

### Boilerplate

Standard `KEY-LISTVAL`, `KEY-NEXT-ITEM` and navigation triggers are usually
`No aplica` because the web UI provides the behavior. Before skipping:

- check for filters, defaults, validations or writes hidden in the trigger;
- check whether the LOV query is the only definition of the allowed values (then
  it is not boilerplate);
- record the reason in the checklist.

## PL/SQL constructs

| PL/SQL | Laravel/PHP | Notes |
| --- | --- | --- |
| `NVL(a, b)` | `$a ?? $b` / SQL `COALESCE` | NULL vs empty string semantics |
| `NVL2(a, b, c)` | ternary | |
| `DECODE(x, ...)` | `match` / map array | Preserve the first-match behavior |
| `SYSDATE` | `now()` | Timezone: Oracle server vs app timezone |
| `USER` | `auth()->id()` | Oracle `USER` is a DB user; map via profile |
| `:NEW.x` / `:OLD.x` | model attributes / `getOriginal()` | |
| `seq.NEXTVAL` | auto-increment / UUID / sequence table | Choose per schema |
| `DUAL` | remove | |
| `ROWNUM` | `limit(1)` / `first()` | Order matters |
| `CONNECT BY` | recursive CTE / adjacency traversal | Test the hierarchy |
| `MERGE` | `updateOrCreate` | Check race conditions and partial updates |
| `COMMIT` / `ROLLBACK` | `DB::transaction` | Do not commit inside a nested operation |
| `EXCEPTION WHEN OTHERS` | `try/catch` + log | Do not swallow; preserve the failure |
| `RAISE_APPLICATION_ERROR` | `ValidationException`/domain exception | Keep the code |
| `DBMS_OUTPUT` | log | Debug output is not a user message |
| `UTL_FILE` / `HOST` | storage / queue / integration | Usually `PENDIENTE` |
| `TO_DATE` / `TO_CHAR` | Carbon / `format()` | Explicit formats |
| `SUBSTR` / `INSTR` / `TRIM` / `UPPER` | `Str::` / `mb_*` | Multibyte behavior |
| `AUTONOMOUS_TRANSACTION` | separate connection/job | Flag; hard to preserve |
| `PIPE ROW` / table functions | collections / query | |
| `XMLTYPE` / `XMLAGG` | `json`/text | Flag the loss of XML semantics |

If a construct changes data semantics and cannot be preserved exactly, document
the approximation and add tests around it. Do not claim equivalence.

## Program units

- A procedure/function becomes a service method or a small action class.
- A package becomes a service class; the package spec is the public API, the body
  is the implementation.
- Package variables are not session state. Map them to request-scoped state, a
  service instance, cache or the database; document the choice.
- Overloaded procedures become distinct methods with clear names.
- Recursion is fine; watch for cycles and add a depth guard if the PL/SQL had one.
- `AUTHID`/privileges: map to policies/gates if the behavior depends on the
  caller.
- Preserve the source and add unit tests for pure functions.

## Conflict versions

Use the IR `kept` version. Record every version in `decisiones.md`:

```markdown
| Object | Kept PID | Kept date | Discarded | Reason |
| --- | --- | --- | --- | --- |
| WHEN-BUTTON-PRESSED (B_ORDERS.BTN_OK) | P11_... | 02-FEB-2024 | P10_... (3 lines) | Newest version per catalog |
```

Never merge versions silently. If the user asks for a merge, treat it as a new
requirement with its own tests.

## Trigger checklist

For every trigger, answer:

- What event does it handle?
- What does it read and write?
- Does it validate, default, derive, persist or notify?
- Does it abort the operation? With what message?
- Which program units/built-ins does it depend on?
- Is the behavior already covered by the web UI, or does it need an explicit
  destination?
- Is it `Portado`, `No aplica` or `PENDIENTE`? Why?
- Which test proves the ported behavior?
