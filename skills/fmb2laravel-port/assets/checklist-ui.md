# UI checklist — `<MODULE>`

> Fill this from Forms Builder, JDAPI or screenshots. Do not guess. Use `?` for
> unknown and `—` for not applicable. The catalog only has item names and
> triggers; this file supplies the item properties and the full block/item list
> that the IR does not contain.

## Module and blocks

| Block | Type (DB/control/non-DB/view) | Table / view | Master block | Relation key | Multi-record | Canvas / tab | Order | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `<B_...>` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | |

## Items

| Block | Item | Column (Forms Column Name) | DB item? | Label / Prompt | Type | Required | Length | Precision / Scale | Mask | LOV / List | Default | Enabled | Visible | Hint / Tooltip | Order | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `<B_...>` | `<ITEM>` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | `?` | |

Column notes:

- **Column**: the Forms `Column Name` property. It is **not** always the item
  name; if unknown, leave `?` and mark the mapping pending.
- **DB item?**: Yes/No. Buttons, display items and control fields are No.
- **Type**: text, number, date, date-time, checkbox, radio, list, LOV, display,
  button, image, editor.
- **Required**: Yes/No/conditional (write the condition).
- **Length**: max characters or digits.
- **Precision / Scale**: for numbers.
- **Mask**: the Forms format mask (`999.99`, `DD/MM/YYYY`, …).
- **LOV / List**: LOV name or static list values; attach the LOV SQL if available.
- **Default**: literal, `SYSDATE`, sequence, global, etc.
- **Enabled / Visible**: Yes/No/conditional.
- **Hint / Tooltip**: exact text if available.
- **Order**: the visual/business order; used when the IR has no order.

## LOVs

| LOV name | Query (attach) | Displayed columns | Return item(s) | Filters | Validation | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `?` | `?` | `?` | `?` | `?` | `?` | |

## Relations

| Master block | Detail block | Master key | Detail FK | Delete rule | Coordination triggers | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| `?` | `?` | `?` | `?` | `?` | `?` | |

## Buttons and alerts

| Object | Label | Action | Confirmation | Message / alert | Notes |
| --- | --- | --- | --- | --- | --- |
| `?` | `?` | `?` | `?` | `?` | |

## Notes

- Source form language:
- Screenshots / Forms Builder version:
- Unknown items:
