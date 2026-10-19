# Coverage and tests

The port is complete when every object present in the catalog or the UI checklist
has a decision and every ported behavior has a test. This reference defines the
checklist, the coverage report and the test plan.

## Checklist

Create `docs/porting/<module>/checklist.md` from `assets/checklist-port.md`.
One row per object **present in the catalog or in the provided UI checklist**:

- block (IR or L3);
- item (IR or L3; the IR only lists trigger-scoped items);
- trigger (block, item and form level);
- LOV (query, displayed columns, return items);
- relation (master-detail and keys);
- program unit;
- conflict version;
- message;
- warning.

The IR is not a full form inventory: blocks/items with no triggers can be absent.
Add them from L3/Forms Builder as `PENDIENTE`; do not claim 100% of the form based
on the IR alone.

Columns:

| Column | Content |
| --- | --- |
| `ID` | Stable id (`B-001`, `I-014`, `T-032`, `F-001`, `L-001`, `R-001`, `P-003`, `M-007`, `W-002`) |
| `Tipo` | Bloque, Ítem, Trigger, Trigger formulario, LOV, Relación, Unidad, Conflicto, Mensaje, Warning |
| `Objeto` | Exact catalog name / scope |
| `Fuente` | IR, trigger PID, DDL, UI checklist, user |
| `Destino` | File/class/rule/action, or `—` for N/A |
| `Estado` | `Portado`, `No aplica`, `PENDIENTE` |
| `Motivo` | Required for N/A and PENDIENTE |
| `Test` | Test name or `—` |

Rules:

- Every known object has a row. Missing rows are bugs.
- `Portado` requires a destination and a test (or a documented reason why a test
  is not meaningful, e.g. a label).
- `No aplica` requires a justification (Forms navigation, window, boilerplate LOV
  with no logic).
- `PENDIENTE` requires a reason and a next step (what input or decision is
  missing).
- Do not use `Portado` for a partial translation. Split the row or mark pending.
- Keep the original names; do not rename objects in the checklist.

## Coverage report

At the end of the checklist, add:

```markdown
## Resumen

| Tipo | Total | Portado | No aplica | PENDIENTE |
| --- | ---: | ---: | ---: | ---: |
| Bloques | 4 | 3 | 1 | 0 |
| Ítems | 85 | 80 | 5 | 0 |
| Triggers | 176 | 120 | 40 | 16 |
| Triggers formulario | 3 | 2 | 1 | 0 |
| LOVs | 6 | 4 | 0 | 2 |
| Relaciones | 2 | 1 | 0 | 1 |
| Unidades | 1 | 1 | 0 | 0 |
| Conflictos | 0 | 0 | 0 | 0 |
| Mensajes | 40 | 35 | 0 | 5 |
| Warnings | 2 | 2 | 0 | 0 |

- Nivel de entrada: L2
- Confianza global: Media
- Pendientes principales: ...
```

The numbers must match the rows. Do not round up.

## Confidence report

`confianza.md`:

```markdown
# Confidence — <MODULE>

- Input level: L2 (catalog + DDL; no screen checklist)
- Module: <module>
- Catalog warnings: HEURISTIC-LIMITS, NO-FORMS-VERSION
- Conflicts: 0

| Area | Confidence | Evidence | Gaps |
| --- | --- | --- | --- |
| Blocks/tables | Alta | DDL | — |
| Triggers | Alta | IR sources | 2 calls to missing units |
| Field properties | Baja | no UI checklist | required, masks, LOVs, labels, column names |
| Messages | Media | trigger strings | 5 codes without text |
| UI | Media | names + triggers | labels, groupings |
```

## Test plan

### One test per rule/validation

For each row in `validaciones.md`:

- a passing case;
- a failing case;
- the exact message;
- boundary/empty/null cases the trigger handles.

### Observer/service tests

- `PRE-INSERT` defaults and validation;
- `POST-INSERT` side effects;
- `PRE-UPDATE` invariants and audit;
- `POST-UPDATE` side effects;
- `PRE-DELETE` guards;
- `POST-DELETE` cleanup;
- transaction rollback on failure;
- concurrency/lock behavior when relevant.

### Authorization tests

If the form has roles/permissions:

- can view/create/update/delete;
- cannot access another tenant/company's records;
- hidden UI is not the only protection.

### Resource smoke tests

For each Filament resource:

- list page renders;
- create page renders;
- edit page renders;
- view page renders (if it exists);
- table shows the expected records;
- the main action works.

### Message tests

- every user-facing message is asserted at least once;
- codes are preserved in logs/context where applicable;
- no raw SQL/Oracle error leaks to the user.

### Conflict regression tests

If a conflict version was kept, add a test for the behavior of the kept version
and reference the discarded PID in `decisiones.md`.

## Test examples

The examples are PHPUnit methods; place them in the relevant test class. If the
target uses Pest, adapt the syntax. The messages are illustrative: assert the
exact text from the trigger, not the examples below.

### Custom rule

```php
use App\Rules\EndDateAfterStart;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

final class EndDateAfterStartTest extends TestCase
{
    public function test_it_rejects_an_end_date_before_the_start_date(): void
    {
        $rule = new EndDateAfterStart('2026-01-10');

        $validator = Validator::make(
            ['end_date' => '2026-01-09'],
            ['end_date' => [$rule]],
        );

        $this->assertTrue($validator->fails());
        $this->assertSame(
            'La fecha fin no puede ser anterior a la fecha inicio.',
            $validator->errors()->first('end_date'),
        );
    }
}
```

### Livewire form

```php
use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Models\User;
use Livewire\Livewire;

public function test_it_requires_a_reason_when_the_status_is_closed(): void
{
    $this->actingAs(User::factory()->create());

    Livewire::test(CreateOrder::class)
        ->fillForm([
            'status' => 'C',
            'reason' => null,
        ])
        ->call('create')
        ->assertHasFormErrors(['reason']);

    $this->assertDatabaseMissing('orders', ['status' => 'C']);
}
```

To assert the exact message, use the form component's rendered errors or test the
rule directly; do not assert only the field key.

### Observer

```php
public function test_it_sets_the_audit_user_when_creating_an_order(): void
{
    $user = User::factory()->create();
    $this->actingAs($user);

    $order = Order::factory()->create();

    $this->assertSame($user->id, $order->created_by);
}
```

### Database constraint

```php
use Illuminate\Database\QueryException;

public function test_it_does_not_allow_duplicate_codes(): void
{
    Order::factory()->create(['code' => 'A-001']);

    $this->expectException(QueryException::class);

    Order::factory()->create(['code' => 'A-001']);
}
```

Then add the user-facing handling test (the form shows the canonical message).

### Authorization

```php
public function test_it_forbids_a_user_from_another_company(): void
{
    $user = User::factory()->create(['company_id' => 2]);
    $order = Order::factory()->create(['company_id' => 1]);

    $this->actingAs($user)
        ->get(OrderResource::getUrl('edit', ['record' => $order]))
        ->assertForbidden();
}
```

## Commands

Run the project's commands; common ones:

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

On Windows, use the PHP binary available to the project and the correct path
separators. Do not pipe Composer into a pager.

Record the exact command and result in the final report. If a test cannot run
(missing database driver, missing extension), say so and mark the affected items
pending; do not claim green.

## Coverage rules

- A `Portado` trigger with business logic must have at least one test.
- A `Portado` validation must have a failing test with the exact message.
- A `Portado` observer/service behavior must have a test.
- A `Portado` LOV/relation must have a test or a documented reason why a test is
  not meaningful (for example a static list with no behavior).
- `No aplica` needs no test, but needs a reason.
- `PENDIENTE` needs no test yet, but needs a next step and the blocked test name.
- Messages without tests are acceptable only for static labels or duplicates
  already covered.

## Definition of done

- [ ] Checklist has one row per object present in the catalog or UI checklist, and the summary matches.
- [ ] The IR coverage limitation is stated if no L3 inventory was provided.
- [ ] `confianza.md` states the level, warnings and gaps.
- [ ] `supuestos.md` lists every inference and its impact.
- [ ] `decisiones.md` lists conflicts, N/A decisions and schema/UI mappings.
- [ ] `validaciones.md` has evidence and a test per rule.
- [ ] All tests pass; formatter is clean.
- [ ] No object is silently missing.
- [ ] The final report is honest about pending work.

## Final report template

```markdown
# Port report — <MODULE>

- Input level: L2
- Module: <module>
- Tables: <n> confirmed, <m> pending
- Resources: <n>
- Tests: <n> passed, <m> skipped (reason)

## Ported
- ...

## Pending
| Item | Reason | Next step |
| --- | --- | --- |
| ... | ... | ... |

## Assumptions
- ...

## Decisions
- ...

## Commands
- `php artisan test --compact` → ...
- `vendor/bin/pint --dirty` → ...

## Files
- ...
```

## Common gaps

- Items without triggers are forgotten.
- Form-level triggers are forgotten because they have no block.
- Program units are ported only where called, not as reusable services.
- Messages extracted from SQL are treated as user messages.
- `No aplica` is used to hide work instead of documenting a decision.
- The checklist summary does not match the rows.
- Tests assert the field key but not the message.
- The schema is generated from `candidateTable` without DDL.
- The UI is generated before the table mapping is confirmed.
