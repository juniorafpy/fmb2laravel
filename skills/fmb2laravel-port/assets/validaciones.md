# Validation matrix — `<MODULE>`

> Illustrative template. Replace every `<...>` and every example message with the
> exact value from a trigger, DDL constraint or the UI checklist. Do not copy the
> example text as if it were the source.

| ID | Objeto | Regla | Fuente | Evidencia | Laravel/Filament | Mensaje | Test | Estado |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| V-001 | `<B_....>` | `<rule>` | `<trigger type>` | `<pid / DDL constraint / checklist row>` | `<rule/class>` | `<exact message>` | `<test name>` | PENDIENTE |
| V-002 | `<B_....>` | `<rule>` | `<DDL>` | `<constraint>` | `<rule/class>` | `<exact message>` | `<test name>` | PENDIENTE |
| V-003 | `<B_....>` | `<rule>` | `<checklist>` | `<row>` | `<rule/class>` | `<exact message>` | `<test name>` | PENDIENTE |

## Sources

| Source | Use for | Confidence |
| --- | --- | --- |
| Explicit user confirmation | Behavior-changing decisions | Alta |
| Trigger source | Business rules, cross-field checks, messages | Alta for behavior |
| DDL | Types, nullability, length, precision, unique, check, FK, defaults | Alta for schema |
| UI checklist | Required, mask, LOV, range, default, visibility, `Column Name` | Alta if human-filled |
| Item/column name | Nothing reliable | Baja |

## Notes

- A rule can appear in several layers (UI, rule, service, DB); implement the
  message once and protect the invariant everywhere.
- If trigger and DDL disagree, record the conflict and decide explicitly.
- If a rule is pending, write what input is missing and which test is blocked.
- Do not infer `required`, length, LOV or mask from a name.

## Pending rules

| Rule | Missing input | Blocks | Next step |
| --- | --- | --- | --- |
| | | | |
