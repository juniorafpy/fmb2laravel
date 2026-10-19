# Port checklist — `<MODULE>`

> One row per object **present in the catalog or in the provided UI checklist**.
> Do not delete rows. `Portado` needs a destination and a test; `No aplica` needs a
> reason; `PENDIENTE` needs a reason and a next step. Keep the original object
> names. The IR only sees blocks/items that appear in trigger scopes; add missing
> objects from L3/Forms Builder as `PENDIENTE`.

- Module: `<MODULE>`
- Input level: `L1 | L2 | L3`
- Catalog: `<ir.json>` / date:
- Target: `<Laravel/Filament versions>`
- Author:

## Objects

| ID | Tipo | Objeto | Fuente | Destino | Estado | Motivo / notas | Test |
| --- | --- | --- | --- | --- | --- | --- | --- |
| B-001 | Bloque | `<B_...>` | IR block / L3 | `App\Models\...` / resource | PENDIENTE | | |
| I-001 | Ítem | `<B_....>` | IR item / L3 | `...Form` | PENDIENTE | | |
| T-001 | Trigger | `<TRIGGER (B_...)>` | trigger `<pid>` | `...` | PENDIENTE | | |
| F-001 | Trigger formulario | `<TRIGGER>` | IR (`block = null`) | `...` | PENDIENTE | | |
| P-001 | Unidad | `<PROGRAM_UNIT>` | program unit `<pid>` | `...` | PENDIENTE | | |
| L-001 | LOV | `<LOV name>` | UI checklist / user | `Select`/relationship | PENDIENTE | Query/return item missing? | |
| R-001 | Relación | `<master → detail>` | UI checklist / DDL / SQL | relation manager | PENDIENTE | | |
| C-001 | Conflicto | `<object>` | conflicts | kept `<pid>` | PENDIENTE | | |
| M-001 | Mensaje | `<text or code>` | IR / trigger / alert | `...` | PENDIENTE | | |
| W-001 | Warning | `<code>` | IR warnings | `confianza.md` | PENDIENTE | | |

Status legend:

- `Portado` — behavior implemented and tested.
- `No aplica` — Forms-only or already provided by the web UI; reason required.
- `PENDIENTE` — missing input/decision; reason + next step required.

## Summary

| Tipo | Total | Portado | No aplica | PENDIENTE |
| --- | ---: | ---: | ---: | ---: |
| Bloques | 0 | 0 | 0 | 0 |
| Ítems | 0 | 0 | 0 | 0 |
| Triggers | 0 | 0 | 0 | 0 |
| Triggers formulario | 0 | 0 | 0 | 0 |
| Unidades | 0 | 0 | 0 | 0 |
| LOVs | 0 | 0 | 0 | 0 |
| Relaciones | 0 | 0 | 0 | 0 |
| Conflictos | 0 | 0 | 0 | 0 |
| Mensajes | 0 | 0 | 0 | 0 |
| Warnings | 0 | 0 | 0 | 0 |

## Pending items

| Item | Reason | Next step | Owner |
| --- | --- | --- | --- |
| | | | |

## Notes

- Catalog warnings:
- Conflicts:
- Missing inputs:
- Out of scope:
- IR coverage limitation (blocks/items without triggers):
