# Architecture of the port

The port must be maintainable after the agent finishes. Keep a clear separation
between persistence, domain behavior and UI. Adapt the structure to the target
app; do not impose a new architecture on an existing codebase.

## Layers

```text
Filament resource / page / action
        │  collects input, presents results
        ▼
Rule / Form validation
        │  field and cross-field shape
        ▼
Service / Action
        │  use case, transaction, domain invariant
        ▼
Model / Observer / Enum
        │  persistence, state, side effects
        ▼
Database (constraints, keys, indexes)
```

| Layer | Responsibility | Must not |
| --- | --- | --- |
| Resource/page | UI, labels, fields, table columns, actions | Contain business rules |
| Rule | Reusable validation with the exact message | Query the DB without need |
| Service/action | Use case, transaction, orchestration | Know about Filament |
| Observer | Persistence invariants, audit, side effects | Hide failures or commit |
| Model | Table, casts, relations, scopes, accessors | Contain multi-step use cases |
| Enum | Codes, labels, transitions | Depend on the UI |
| Policy | Authorization | Be replaced by hidden buttons |
| Database | Integrity and concurrency safety | Be the only validation |

## Services and actions

Use one class per use case when the behavior is non-trivial:

```php
namespace App\Services\Orders;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ApproveOrder
{
    public function handle(Order $order): Order
    {
        return DB::transaction(function () use ($order) {
            $order = Order::query()->lockForUpdate()->findOrFail($order->id);

            if ($order->status !== 'P') {
                throw ValidationException::withMessages([
                    'status' => 'El pedido no está pendiente de aprobación.',
                ]);
            }

            $order->update([
                'status' => 'A',
                'approved_at' => now(),
            ]);

            // Side effects after commit when appropriate.

            return $order;
        });
    }
}
```

Guidelines:

- Inject dependencies through the constructor; do not use facades for everything.
- Use `DB::transaction` for multi-step writes.
- Use `lockForUpdate()` for rows that the Forms code locked.
- Use `afterCommit`/events for side effects that must not run on rollback.
- Throw domain/validation exceptions; do not return `false` and hope the caller
  notices.
- Keep methods small enough to test directly.

If the app already uses actions (`app/Actions`), follow that convention. Do not
create both a service and an action for the same use case.

## Observers

Use observers for invariants and side effects tied to a model event:

```php
namespace App\Observers;

use App\Models\Order;
use Illuminate\Validation\ValidationException;

final class OrderObserver
{
    public function saving(Order $order): void
    {
        if ($order->status === 'C' && $order->closed_at === null) {
            throw ValidationException::withMessages([
                'status' => 'La fecha de cierre es obligatoria.',
            ]);
        }
    }

    public function created(Order $order): void
    {
        // Queue notifications, audit rows, etc.
    }
}
```

Rules:

- `saving` runs before insert/update; `created`/`updated` after.
- Do not send emails or call external services inside a transaction without a
  queue/afterCommit strategy.
- Do not swallow exceptions.
- Do not use observers for UI-only behavior.
- Register observers in the app's service provider or via the `ObservedBy`
  attribute, following the app convention.

Map Forms events precisely:

| Forms | Eloquent |
| --- | --- |
| `PRE-INSERT` | `creating` / `saving` |
| `POST-INSERT` | `created` |
| `PRE-UPDATE` | `updating` / `saving` |
| `POST-UPDATE` | `updated` |
| `PRE-DELETE` | `deleting` |
| `POST-DELETE` | `deleted` |

## Rules and enums

- Complex validation → a `ValidationRule` class with the exact message.
- Status codes → a backed enum with `label()` and `allowedTransitions()`.
- Static lists → enum or config, not magic strings scattered in the UI.
- Do not use an enum when the source codes are dynamic data from a table; use a
  lookup model instead.

Example:

```php
enum OrderStatus: string
{
    case New = 'N';
    case Approved = 'A';
    case Pending = 'P';
    case Closed = 'C';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Nuevo',
            self::Approved => 'Aprobado',
            self::Pending => 'Pendiente',
            self::Closed => 'Cerrado',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::New => [self::Approved, self::Closed],
            self::Approved => [self::Pending, self::Closed],
            self::Pending => [self::Closed],
            self::Closed => [],
        };
    }
}
```

## Program units and packages

- Procedure/function → service method or invokable action.
- Package → service class; the spec is the public API.
- Package variables → request state, service instance, cache or DB; document it.
- Pure functions → static helpers or a small class with tests.
- Keep the original source in `plsql/` and link it from the checklist.

If a program unit is called from several triggers, port it once and reuse it. Do
not duplicate the logic in each resource.

## Error handling

- Validation → `ValidationException` with field messages.
- Domain rule → a domain exception mapped to a user message at the edge.
- Unexpected → log with context (module, trigger, record id, message code) and show
  a safe generic message.
- Never catch and ignore. Never convert a failure into a success notification.
- Preserve message codes in logs/context.

```php
try {
    app(ApproveOrder::class)->handle($order);
} catch (ValidationException $e) {
    throw $e;
} catch (Throwable $e) {
    report($e);
    Notification::make()
        ->title('No se pudo completar la operación.')
        ->danger()
        ->send();
}
```

## Transactions, locks and idempotency

- A Forms `COMMIT_FORM` is a transaction boundary for the logical operation. Do not
  commit per field.
- If the trigger locks a row, use `lockForUpdate()` in a transaction.
- If an operation can be retried (button double-click, queue retry), make it
  idempotent or guard it with a unique constraint/state check.
- Use database constraints for uniqueness; handle the race.
- Test rollback behavior.

## Audit and logging

- Fill audit columns from the authenticated user, not from a client field.
- Keep the original Forms message code in logs when available.
- Log the trigger/program-unit name and the module for traceability.
- Do not log secrets, passwords or full personal data.

## Jobs, events and schedulers

- Timers → scheduler/queued job.
- Report generation → queued job if slow.
- External integrations → job with retries and failure handling.
- Notifications → after commit or queued.
- Long-running operations → progress/notification, not a blocked request.

## Naming and structure

- Follow the app's existing namespaces and naming.
- One model per table; one resource per entity the user manages.
- Services named after the use case (`ApproveOrder`, `RecalculateTotals`).
- Rules named after the rule (`EndDateAfterStart`, `ValidStatusTransition`).
- Tests named after the behavior, not the implementation.
- Do not create repositories/abstractions the app does not use.

## Testing seams

- Services are directly testable; inject them.
- Rules are unit-testable.
- Observers are testable through model events.
- Resources are testable through Livewire.
- Avoid static calls that make tests order-dependent.
- Use factories for data; do not rely on production data.

## What not to do

- A model for a control block.
- Business logic in a Filament action closure.
- Validation only in the UI.
- A repository layer for a small app.
- A new architecture that ignores the existing app.
- Silent catch blocks.
- Copying PL/SQL into PHP line by line without understanding the intent.
- Deleting the original PL/SQL after translating it.

## Definition of a good port

A new developer can:

1. find every Forms object and its destination in the checklist;
2. understand every assumption and pending item;
3. run the tests and see the behavior protected;
4. change a rule in one place and have the UI and API follow;
5. trace a user message back to its Forms origin.
