# Assumptions — `<MODULE>`

> Every inference that is not directly evidenced goes here. If an assumption
> changes behavior, ask the user before implementing it. Update the status when
> the user confirms or rejects it. The rows below are illustrative placeholders;
> replace every value with the module's real data.

| ID | Assumption | Evidence | Impact | Confirmed | Date |
| --- | --- | --- | --- | --- | --- |
| A-001 | `<B_...>` maps to table `<TABLE>` | `candidateTable` + trigger SQL | Model, resource, tests | No | |
| A-002 | UI labels are the humanized item names | Catalog has no labels | UI text | No | |
| A-003 | Status codes are `<code1>/<code2>/...` | Trigger conditions | Enum, validation, badges | No | |

Status values: `No`, `Pendiente`, `Sí`, `Rechazado`.

## Open questions

| ID | Question | Blocks | Asked on | Answer |
| --- | --- | --- | --- | --- |
| Q-001 | | | | |

## Rules

- An assumption with `Confirmed = No` that affects behavior must be visible in the
  final report.
- A rejected assumption must be removed from the code, not left behind.
- Do not use an assumption to replace a missing DDL column, LOV query or message
  text. Those are `PENDIENTE`, not assumptions.
