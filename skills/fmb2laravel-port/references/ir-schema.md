# IR v1.0 contract (sanitized)

This is a sanitized copy of the fmb2laravel IR v1.0 contract for the skill. It
contains no module-specific names, messages or data. If the product repository's
`docs/ir-schema.md` is available and documents a newer version, that document wins
for its version; this file exists so the skill works when distributed alone.

## Top level

```jsonc
{
  "irVersion": "1.0",
  "generator": { "name": "fmb2laravel", "version": "0.1.0-alpha" },
  "source": { "file": "ORDERS.fmb", "sizeBytes": 1376256, "sha256": "..." },
  "module": { "name": "ORDERS" },
  "stats": {
    "records": 228,
    "triggers": 176,
    "programUnits": 1,
    "conflicts": 0,
    "blocks": 4,
    "items": 85
  },
  "blocks": [],
  "triggers": [],
  "conflicts": [],
  "tables": { "referenced": [], "written": [] },
  "messages": [],
  "warnings": []
}
```

- `generator` is an object (`name`, `version`), not a string.
- `source.file` may be the internal upload name, not the original `.fmb` name. Use
  `module.name` as the module identifier when it is present.
- File names in the catalog use the `.fmb` stem. `module.name` can differ (for
  example if it was overridden with `--module`). Ask which one to use for the
  target app.
- `stats.records` counts raw records including old versions; `stats.triggers`
  counts unique triggers. They are not the same number.
- `stats.programUnits` counts program units. In the current builder, program units
  also appear in `ir.triggers` with `type: null`; `triggers.json` contains triggers
  only. Use `program_units.json` as the program-unit source.
- `irVersion` must be `1.0`. If it differs, stop and obtain the matching schema.

## Blocks

```jsonc
{
  "name": "B_ORDERS",
  "candidateTable": "ORDERS",
  "triggers": [1, 2],
  "items": [
    { "name": "ORDER_ID", "triggers": [3] },
    { "name": "STATUS", "triggers": [] }
  ],
  "triggerCount": 2,
  "itemCount": 2
}
```

- `candidateTable` is a heuristic (`B_X` → `X`), adjustable with `--config`. It is
  not proof. Confirm with DDL, trigger SQL, relations or the user. `null` means the
  reader could not infer a table.
- `triggers` are trigger ids scoped to the block; `items[].triggers` are ids
  scoped to the item.
- Items only carry `name` and trigger ids. The IR does not include datatype,
  required, length, mask, LOV, label, default, visible, enabled or the Forms
  `Column Name` property.

**Coverage limitation:** blocks and items are derived from trigger scopes. A block
with no triggers, or an item with no triggers, may be absent from the IR, and
`stats.items` counts trigger-scoped items only. The IR is authoritative for the
extracted trigger analysis, **not a complete form inventory**. Use a Forms Builder
export, JDAPI or the L3 checklist to get the full block/item list, and add the
missing objects as `PENDIENTE`.

## Triggers

```jsonc
{
  "id": 3,
  "name": "WHEN-VALIDATE-ITEM (B_ORDERS.STATUS)",
  "type": "WHEN-VALIDATE-ITEM",
  "scope": "B_ORDERS.STATUS",
  "block": "B_ORDERS",
  "item": "STATUS",
  "modified": "17-NOV-2011 07:24:32",
  "pid": "P19_17_NOV_201107_24_32",
  "versions": 1,
  "lines": 12,
  "classification": "logica",
  "readsTables": ["orders", "status"],
  "writesTables": ["ORDERS"],
  "messages": ["..."],
  "itemsReferenced": ["B_ORDERS.START_DATE"],
  "calls": ["..."],
  "source": "BEGIN\n...\nEND;"
}
```

- `block` and `item` are `null` for form-level triggers.
- `type` is the Forms trigger name; `scope` is a readable location.
- `versions` is the number of versions found; the source is the kept (newest)
  version. See `conflicts`.
- `classification` is `logica`, `boilerplate_lov` or `boilerplate_nav`. It is
  guidance, not proof; read the source before skipping.
- `readsTables` / `writesTables` are extracted from SQL; they are hints, not a
  complete data-flow analysis.
- `messages` is a **partial** extraction of literal message arguments. It does not
  reliably capture `RAISE_APPLICATION_ERROR`, alert definitions, concatenations or
  `SQLERRM`. Scan every trigger source and alert.
- `itemsReferenced` currently captures items assigned by the trigger (for example
  `:BLOCK.ITEM := ...`), not every item read in conditions. Read the source for
  cross-field rules.
- `calls` is a best-effort extraction based on a fixed pattern list; it can miss
  package/procedure names. Follow the source and `program_units.json`.
- `source` always contains the full PL/SQL, even when it could not be parsed.

## Conflicts

```jsonc
{
  "name": "WHEN-BUTTON-PRESSED (B_ORDERS.BTN_OK)",
  "versions": 2,
  "kept": "02-FEB-2024 09:15:10",
  "kept_pid": "P11_02_FEB_202409_15_10",
  "all": [
    { "pid": "P10_01_JAN_202408_00_00", "modified": "01-JAN-2024 08:00:00", "lines": 3 },
    { "pid": "P11_02_FEB_202409_15_10", "modified": "02-FEB-2024 09:15:10", "lines": 5 }
  ]
}
```

Use `kept` / `kept_pid`. Record every version in `all` in `decisiones.md`. Never
merge versions silently.

## Tables, messages and warnings

`tables.referenced` / `tables.written` are regex-derived inventories. They can
include aliases, variables (`SELECT ... INTO :var`), package names, dummy tables
or fragments. Use them to find candidates, then confirm with DDL and SQL.

`messages` can mix user texts, numeric codes and quoted SQL fragments. It is
partial. Resolve each message from the trigger source, the alert definition or the
user; if a code has no text, keep the code and mark it `PENDIENTE`.

`warnings` is a list of `{ code, message }`:

| Code | Meaning | Action |
| --- | --- | --- |
| `VERSION-CONFLICT` | Divergent versions; the newest was kept | Review `conflicts`, document discarded versions |
| `HEURISTIC-LIMITS` | Known binary-reader limit (for example a trigger not starting with `BEGIN`/`DECLARE`) | Inspect the source; mark gaps `PENDIENTE` |
| `NO-FORMS-VERSION` | Exact Forms version could not be determined; the catalog uses the 6i-validated format | Do not claim 9i+ properties; verify with the user |

The v1.0 builder emits `HEURISTIC-LIMITS` and `NO-FORMS-VERSION` for every form, so
their presence alone does not mean the catalog is defective. Read their text and
decide whether they affect this module.

## Derived rules file

`reglas.json` is a convenience view, not authoritative:

```jsonc
{
  "form": "ORDERS",
  "bloques": {
    "B_ORDERS": {
      "tabla": null,
      "items": {
        "STATUS": [
          {
            "nombre": "WHEN-VALIDATE-ITEM (B_ORDERS.STATUS)",
            "tipo": "WHEN-VALIDATE-ITEM",
            "modificado": "17-NOV-2011 07:24:32",
            "tablas": ["status"],
            "escribe": [],
            "mensajes": ["..."],
            "llamadas": ["..."],
            "items_afectados": ["B_ORDERS.START_DATE"],
            "codigo": "BEGIN ... END;"
          }
        ]
      },
      "triggers_bloque": []
    }
  },
  "boilerplate": [
    { "name": "KEY-LISTVAL (B_ORDERS.STATUS)", "clase": "boilerplate_lov" }
  ],
  "tablas_globales": ["orders", "status"]
}
```

- `bloques` is an object keyed by block name; `items` is an object keyed by item
  name, or `[]` when empty.
- `tabla` may be `null` even for a database block. Do not use it as the mapping.
- Form-level triggers and program units are grouped under a pseudo-block named
  after the form/module (or a form-level placeholder). **Do not map that pseudo-
  block to a table or model.** Detect form-level triggers in the IR with
  `block === null`, not from `reglas.json.bloques`.

## Objects not present in the IR

The IR does not contain:

- item properties (datatype, required, length, precision, mask, LOV, label,
  default, visible, enabled, hint, `Column Name`);
- LOV definitions (query, columns, return items, filters);
- block relations / master-detail metadata;
- alert definitions and button behavior;
- menus, windows, canvases and coordinates;
- database constraints and indexes (unless you provide DDL);
- data.

These are L3/DDL inputs. If they are missing, mark the affected objects `PENDIENTE`
instead of inventing them.

## Compatibility

- Forms 6i is the validated target.
- Newer versions are best-effort; do not claim properties or triggers that the
  catalog does not contain.
- The `.fmb` format is undocumented; catalog warnings describe the limits.
