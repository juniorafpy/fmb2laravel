# fmb2laravel IR — v1.0

La **IR (Intermediate Representation)** es el contrato estable entre el lector de
`.fmb` y el generador Laravel/Filament. Se escribe en `<form>.ir.json` con
`fmb2laravel extract`.

## Estructura

```jsonc
{
  "irVersion": "1.0",
  "generator": { "name": "fmb2laravel", "version": "0.1.0-alpha" },
  "source": {
    "file": "MIFORM.fmb",
    "sizeBytes": 1376256,
    "sha256": "71121e00..."
  },
  "module": { "name": "MIFORM" },
  "stats": {
    "records": 228,       // registros crudos encontrados (con versiones antiguas)
    "triggers": 176,      // triggers únicos (versión más reciente)
    "programUnits": 1,    // packages/procedimientos encontrados
    "conflicts": 0,       // objetos con versiones divergentes
    "blocks": 4,
    "items": 85
  },
  "blocks": [
    {
      "name": "B_TAREAS",
      "candidateTable": "TAREAS",   // inferida (B_X -> X); ajustable con --config
      "triggers": [1, 2],            // ids de triggers de nivel bloque
      "items": [
        { "name": "COD_ESTADO", "triggers": [3] }
      ],
      "triggerCount": 5,
      "itemCount": 61
    }
  ],
  "triggers": [
    {
      "id": 3,
      "name": "WHEN-VALIDATE-ITEM (B_TAREAS.COD_ESTADO)",
      "type": "WHEN-VALIDATE-ITEM",   // null para program units
      "scope": "B_TAREAS.COD_ESTADO",
      "block": "B_TAREAS",            // null si es de nivel formulario (PANTALLA)
      "item": "COD_ESTADO",
      "modified": "17-NOV-2011 07:24:32",
      "pid": "P19_17_NOV_201107_24_32",
      "versions": 1,                   // nº de versiones encontradas en el binario
      "lines": 12,
      "classification": "logica",      // logica | boilerplate_lov | boilerplate_nav
      "readsTables": ["tareas"],
      "writesTables": ["TAREAS"],
      "messages": ["Debe indicar estado"],
      "itemsReferenced": ["B_TAREAS.FEC_INICIO"],
      "calls": ["alerta", "inicializa_globales"],
      "source": "BEGIN\n..."
    }
  ],
  "conflicts": [
    {
      "name": "WHEN-BUTTON-PRESSED (B_TAREAS.BTN_OK)",
      "versions": 2,
      "kept": "02-FEB-2024 09:15:10",
      "kept_pid": "P11_02_FEB_202409_15_10",
      "all": [
        { "pid": "P10_01_ENE_202408_00_00", "modified": "01-ENE-2024 08:00:00", "lines": 3 },
        { "pid": "P11_02_FEB_202409_15_10", "modified": "02-FEB-2024 09:15:10", "lines": 5 }
      ]
    }
  ],
  "tables": {
    "referenced": ["empleado", "tareas"],
    "written": ["TAREAS", "TAREA_AFECTADA"]
  },
  "messages": ["No se puede borrar", "Debe indicar estado"],
  "warnings": [
    { "code": "HEURISTIC-LIMITS", "message": "..." }
  ]
}
```

## Códigos de `warnings`

| Código | Significado |
| --- | --- |
| `VERSION-CONFLICT` | Un objeto tiene varias versiones con código distinto; se conservó la más reciente y se lista en `conflicts`. |
| `HEURISTIC-LIMITS` | Límite conocido del lector heurístico (p. ej. triggers que no empiezan con `BEGIN`/`DECLARE`). |
| `NO-FORMS-VERSION` | No se pudo determinar la versión exacta de Forms; el catálogo usa el formato validado en 6i. |

## Reglas del contrato

1. **Nunca fallar en silencio**: todo lo que no se puede traducir queda en `source`
   junto con un `warning`. La IR siempre contiene el PL/SQL completo.
2. Los campos comienzan en `irVersion` y solo cambian con incrementos de versión
   mayor documentados aquí.
3. `classification` es orientativa: los `boilerplate_*` son candidatos a omitir en
   la generación, pero el generador debe poder incluirlos si se le pide.
