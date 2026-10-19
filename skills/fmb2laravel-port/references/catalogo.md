# Catalog reference

The catalog is produced by fmb2laravel from one `.fmb`. It is the **source of
truth for the extracted trigger analysis**, not a complete replica of the form.
Treat every file as read-only input, never as something to edit or regenerate.
The field contract is in `references/ir-schema.md`; this file explains how to use
each artifact.

File names below use `<module>` as a placeholder. They are based on the `.fmb`
stem; `module.name` can differ (for example when it was overridden with
`--module`). Do not assume either matches the application name.

## Files and reading order

Read in this order:

1. `<module>.ir.json` — metadata, blocks, items, triggers, conflicts, tables,
   messages, warnings.
2. `<module>.triggers.json` — full trigger sources, one entry per unique trigger.
3. `<module>.program_units.json` — packages, procedures and functions.
4. `<module>.conflicts.json` — objects with multiple divergent versions.
5. `<module>.reglas.json` — derived business-rules summary.
6. `<module>.reglas.md` — human-readable version of the same summary.

`ir.json` is authoritative. `reglas.json` is a convenience view built from it; if
they disagree, trust the IR and the original trigger source. `reglas.md` is a
summary and may omit code: never implement from it alone.

## `ir.json`

Top-level keys:

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

Notes:

- `generator` is an object (`name`, `version`), not a string.
- `source.file` may be the internal file name used by the panel upload, not the
  original `.fmb` name. Use `module.name` as the module identifier.
- `stats.records` counts raw records including old versions; `stats.triggers`
  counts unique triggers. They are not the same number.
- `stats.programUnits` counts program units. In the current builder they also
  appear in `ir.triggers` with `type: null`; `triggers.json` contains triggers
  only. Use `program_units.json` as the program-unit source.
- `irVersion` must be `1.0`. If it is different, stop and obtain the matching
  schema; do not guess fields. See `references/ir-schema.md`.

### Blocks and items

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

- `candidateTable` is a **heuristic**: usually `B_X` → `X`, adjustable with
  `--config`. It is not proof. Confirm with the DDL, the trigger SQL or the user.
  `null` means the reader could not infer a table; the block may be a control
  block or a database block with a non-standard name.
- `triggers` are trigger ids scoped to the block. `items[].triggers` are ids
  scoped to the item.
- Items only carry `name` and trigger ids. The catalog does **not** contain item
  datatype, required flag, length, mask, LOV, label, default, visible, enabled or
  the Forms `Column Name`. Those come from L3 (UI checklist) or from the user.
  An item name is not necessarily a column name; confirm the mapping.
- **Coverage limitation:** blocks and items are built from trigger scopes. A block
  with no triggers, or an item with no triggers, may be absent, and `stats.items`
  counts trigger-scoped items only. The IR is authoritative for what it extracted,
  not a full form inventory. Get the complete block/item list from a Forms Builder
  export, JDAPI or the L3 checklist, and add missing objects as `PENDIENTE`.

### Triggers

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
  "messages": ["El estado es obligatorio."],
  "itemsReferenced": ["B_ORDERS.START_DATE"],
  "calls": ["alerta", "initialize_globals"],
  "source": "BEGIN\n...\nEND;"
}
```

- `block` and `item` are `null` for form-level triggers.
- `type` is the Forms trigger name (`WHEN-VALIDATE-ITEM`, `POST-QUERY`, …).
- `scope` is a readable location: block, `BLOCK.ITEM` or form.
- `versions` is the number of versions found; the source is the kept (newest)
  version. See `conflicts`.
- `classification` is guidance:
  - `logica` — business/data logic; port it.
  - `boilerplate_lov` — standard LOV/query boilerplate; usually `No aplica`, but
    verify before skipping.
  - `boilerplate_nav` — standard navigation boilerplate; usually `No aplica`.
- `readsTables` / `writesTables` are extracted from the SQL. They are hints, not a
  complete data-flow analysis.
- `messages` is a **partial** extraction of literal message arguments. It does not
  reliably capture `RAISE_APPLICATION_ERROR`, alert definitions, concatenations or
  `SQLERRM`. Scan every trigger source and alert; do not assume the array is
  complete.
- `itemsReferenced` currently captures items **assigned** by the trigger (for
  example `:BLOCK.ITEM := ...`), not every item read in conditions. Read the
  source to find cross-field rules.
- `calls` is a best-effort extraction based on a fixed pattern list. It can miss
  package/procedure names. Follow the source and `program_units.json`; use it to
  spot alerts, reports and external calls, not as a complete call graph.
- `source` always contains the full PL/SQL, even when it could not be parsed.

### Conflicts

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

- Use `kept` / `kept_pid`.
- Record every version in `all` in `decisiones.md` (PID, date, lines) and state
  that the kept one was ported.
- Never merge versions unless the user explicitly asks; if you do, document the
  merge and add tests for the merged behavior.

### Tables, messages and warnings

`tables.referenced` and `tables.written` are regex-derived inventories. They can
include aliases, `SELECT ... INTO` variables, package names, dummy tables or
fragments. Use them to find candidate tables, then confirm with the DDL and the
SQL.

`messages` is a flat array. It can mix:

- full user texts (`"No se puede eliminar el registro."`),
- numeric codes (`"000044"`),
- quoted fragments extracted from `MESSAGE`, `ALERT` or SQL.

If a code has no text in any input, keep the code and mark the message
`PENDIENTE`; do not invent text.

`warnings` is a list of `{ code, message }`:

| Code | Meaning | What to do |
| --- | --- | --- |
| `VERSION-CONFLICT` | An object has divergent versions; the newest was kept | Review `conflicts`, document discarded versions |
| `HEURISTIC-LIMITS` | The binary reader hit a known limit (for example a trigger that does not start with `BEGIN`/`DECLARE`) | Inspect the source manually; mark any gap `PENDIENTE` |
| `NO-FORMS-VERSION` | The exact Forms version could not be determined; the catalog uses the 6i-validated format | Do not claim 9i+ properties; verify with the user |

The v1.0 builder emits `HEURISTIC-LIMITS` and `NO-FORMS-VERSION` for every form,
so their presence alone does not mean this module is defective. Read the message
and decide whether it affects this module. Always include the warnings in the
final report and in `confianza.md`.

## `triggers.json`

An array with the full source of each unique trigger:

```jsonc
[
  {
    "name": "WHEN-VALIDATE-ITEM (B_ORDERS.STATUS)",
    "modified": "17-NOV-2011 07:24:32",
    "pid": "P19_17_NOV_201107_24_32",
    "versions": 1,
    "source": "BEGIN ... END;",
    "type": "WHEN-VALIDATE-ITEM",
    "scope": "FORM"
  }
]
```

- This file contains triggers, not program units. Program units are in
  `program_units.json` (and, in the current builder, in `ir.triggers` with
  `type: null`).
- Use this file when you need the exact source without the IR analysis fields, or
  when an IR tool truncates long strings.
- The same source appears in the IR; they must match. If they do not, report the
  inconsistency and use the IR as authoritative.

## `program_units.json`

An array of PL/SQL program units:

```jsonc
[
  {
    "name": "CALCULATE_TOTAL",
    "modified": "14-SEP-2009 09:44:41",
    "pid": "P5_14_SEP_200909_44_41",
    "versions": 1,
    "source": "BEGIN ... END;"
  }
]
```

- A program unit can be a procedure, function or package. The catalog does not
  always store the kind; infer it from `source` (`PROCEDURE`, `FUNCTION`,
  `PACKAGE`) and record the assumption if it matters.
- In the current builder, program units also appear in `ir.triggers` with
  `type: null`; use `program_units.json` as the authoritative list.
- Program units are dependencies of triggers (`calls`). Port them as services,
  helpers or domain methods; do not leave a call unresolved.
- Preserve the original source in `plsql/` and reference it from the checklist.
- Package state (package variables) is not session state in Laravel. Map it to
  request-scoped state, a service instance, cache or the database, and document
  the decision.

## `conflicts.json`

Same array as `ir.conflicts`. It exists separately for tooling. If it is empty,
there are no conflicts. If it is missing but the IR has conflicts, use the IR.

## `reglas.json` / `reglas.md`

`reglas.json` is a derived summary. Shape:

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
            "mensajes": ["El estado es obligatorio."],
            "llamadas": ["alerta"],
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

Important:

- `bloques` is an **object keyed by block name**, not an array.
- `items` is an **object keyed by item name**; each value is an array of trigger
  objects. When a block has no items, it is serialized as an empty array `[]`.
- `tabla` may be `null` even for a database block. Do not use it as the mapping;
  use `candidateTable` from the IR plus evidence.
- Form-level triggers and program units are grouped under a **pseudo-block** named
  after the form/module (or a form-level placeholder). Do not map that pseudo-block
  to a table or model. Detect form-level triggers in the IR with `block === null`;
  do not rely on `reglas.json.bloques` for that.
- `triggers_bloque` holds block-level triggers; `items.*` holds item-level ones.
- `boilerplate` lists triggers classified as boilerplate, with `clase`
  (`boilerplate_lov` or `boilerplate_nav`). This is guidance, not proof.
- `tablas_globales` is the union of referenced tables, including aliases and
  lookup tables. It is not a schema.
- `reglas.md` is a readable summary. It may show names and messages but not the
  full code. Always cross-check with the IR.
- The rules file does **not** contain LOV definitions, block relations, alert
  definitions, menus, item properties or schema constraints. Those are L3/DDL
  inputs; mark them `PENDIENTE` if missing.

## Classification and what to do with it

| Classification | Typical triggers | Default action |
| --- | --- | --- |
| `logica` | `WHEN-VALIDATE-ITEM`, `WHEN-VALIDATE-RECORD`, `PRE-INSERT`, `POST-QUERY`, `WHEN-BUTTON-PRESSED` with business logic | Port to a rule, service, observer or action |
| `boilerplate_lov` | Standard `KEY-LISTVAL` / `WHEN-LIST-CHANGED` for a lookup | `No aplica` if the UI already provides the LOV; verify no extra filtering |
| `boilerplate_nav` | `KEY-NEXT-ITEM`, `KEY-PRVTBLK`, standard navigation | `No aplica`; Forms navigation is replaced by the web UI |

The classification is **orientative**. A `boilerplate_nav` trigger can hide a
business rule (for example, setting a date when moving to the next item). Always
read the source before marking it `No aplica`.

## Practical checks before coding

- [ ] `irVersion` is `1.0` (or the schema was reviewed).
- [ ] `module.name` and `stats` are read.
- [ ] All warnings are in the checklist/`confianza.md`.
- [ ] The full block/item list is from L3 or the report states it is trigger-derived.
- [ ] Every block with `candidateTable` has a confirmation source.
- [ ] Every item has a confirmed column or a `PENDIENTE` mapping.
- [ ] LOVs, relations and alerts are in the checklist (or explicitly pending).
- [ ] Every conflict has a `decisiones.md` row.
- [ ] Every program unit called by a trigger exists in the inputs.
- [ ] Every message code has a text, or is marked `PENDIENTE`.
- [ ] `itemsReferenced` and `messages` were treated as partial and the sources scanned.
- [ ] No table or column is used without DDL or trigger evidence.
