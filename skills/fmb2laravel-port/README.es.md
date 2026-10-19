# fmb2laravel-port

Skill para agentes de IA que convierte un **catálogo de fmb2laravel** en un port a
**Laravel + Filament**: esquema Oracle preservado, modelos, validaciones, triggers,
mensajes, tests y un checklist de cobertura.

No es un lector de `.fmb`. El catálogo se genera antes con el panel web de
fmb2laravel o con el CLI. La skill enseña al agente a usar ese catálogo sin
inventar lo que no contiene.

> Esta skill se distribuye **aparte** del ZIP de catálogo. El ZIP no cambia y no
> incluye la carpeta `skills/`.

## Qué incluye

| Archivo | Para qué |
| --- | --- |
| `SKILL.md` | Instrucciones completas para el agente (en inglés) |
| `README.es.md` | Esta guía humana |
| `PROMPT.md` | Prompt condensado para chats sin soporte de skills |
| `references/ir-schema.md` | Contrato IR v1.0 saneado: campos, límites y compatibilidad |
| `references/catalogo.md` | Cómo usar cada archivo del catálogo (triggers, reglas, conflictos, warnings) |
| `references/entradas-y-confianza.md` | Qué se puede prometer con L1, L2 y L3 |
| `references/perfil-de-port.md` | Preguntas de destino antes de programar |
| `references/validaciones.md` | Matriz de validaciones y mensajes |
| `references/triggers.md` | Mapa de triggers de Forms a capas Laravel |
| `references/forms-builtins.md` | `:SYSTEM`, `:GLOBAL`, built-ins y PL/SQL |
| `references/bloques-relaciones.md` | Bloques, maestros-detalle, claves y controles |
| `references/esquema-oracle.md` | DDL Oracle → migraciones y modelos |
| `references/filament.md` | Recursos, formularios, tablas y acciones |
| `references/arquitectura.md` | Servicios, observers, transacciones y estructura |
| `references/cobertura-y-tests.md` | Checklist, cobertura y plan de tests |
| `assets/checklist-port.md` | Checklist de cobertura por objeto |
| `assets/checklist-ui.md` | Checklist de UI: bloques, ítems, `Column Name`, LOVs y relaciones |
| `assets/validaciones.md` | Plantilla de matriz de validaciones |
| `assets/decisiones.md` | Plantilla de conflictos, mapeos y decisiones |
| `assets/tablas.sql` | Plantilla de DDL Oracle de entrada |
| `assets/*.stub` | Stubs de migración, modelo, recurso, formulario, observer, servicio, regla y test |

## Requisitos

- Un catálogo generado por fmb2laravel (`.ir.json`, `.triggers.json`,
  `.triggers.sql`, `.program_units.json`, `.conflicts.json`, `.reglas.json`).
- Opcional pero muy recomendable: DDL Oracle (`tablas.sql`) y el checklist de UI
  rellenado por una persona.
- Para el destino por defecto: PHP 8.3+, Laravel 13, Filament 5 y PHPUnit. La
  skill se adapta si el perfil del proyecto indica otra cosa.

> El IR solo lista bloques e ítems que aparecen en triggers. No es un inventario
> completo del formulario: para cobertura total hay que completar el checklist de
> UI desde Forms Builder/JDAPI o marcar el inventario como derivado de triggers.

## Instalación

La skill es una carpeta con `SKILL.md` en la raíz. Instálala una vez en el agente
que vayas a usar; no hace falta copiarla en cada proyecto.

### opencode

Copia la carpeta a:

```text
<tu-proyecto>/.opencode/skills/fmb2laravel-port/
```

o, para todos tus proyectos:

```text
~/.config/opencode/skills/fmb2laravel-port/
```

opencode también descubre `.claude/skills/` y `.agents/skills/`, así que puedes
usar cualquiera de esas ubicaciones.

### Claude Code

Copia la carpeta a:

```text
<tu-proyecto>/.claude/skills/fmb2laravel-port/
```

o a `~/.claude/skills/fmb2laravel-port/` para uso global.

### Cursor, Codex, Windsurf y otros

Estas herramientas no cargan `SKILL.md` automáticamente. Copia `PROMPT.md` en tu
archivo de reglas (`AGENTS.md`, `.cursor/rules/`, etc.) o pégalo al inicio de la
conversación, y adjunta los archivos del catálogo.

### ChatGPT, Gemini u otro chat

Pega el contenido de `PROMPT.md` y luego adjunta o pega:

1. el `.ir.json`;
2. los `.triggers.json` / `.triggers.sql`;
3. el `.program_units.json` y `.conflicts.json`;
4. opcionalmente el DDL y el checklist de UI.

Adjunta también `references/ir-schema.md` y `references/catalogo.md` (o pega su
contenido) para que el agente interprete los archivos y conozca los límites del
IR. Para catálogos grandes, pide al agente que trabaje por bloques.

## Uso

1. **Genera el catálogo** en el panel de fmb2laravel (o con el CLI) y descarga el
   ZIP.
2. **Opcional (L2):** consigue el DDL de las tablas implicadas. Sin DDL no se
   pueden generar columnas, constraints ni claves con fidelidad.
3. **Opcional (L3):** rellena `assets/checklist-ui.md` con la lista completa de
   bloques/ítems y sus propiedades (obligatorio, longitud, máscara, LOV, default,
   visible, ayuda, `Column Name`). Se puede completar desde Forms Builder, JDAPI o
   capturas de la interfaz.
4. **Pide el port** con algo como:

```text
Usa la skill fmb2laravel-port para portar este módulo a Laravel 13 + Filament 5.
Adjunto el catálogo, el DDL (L2) y el checklist de UI (L3).
Idioma de la aplicación: el del formulario original.
No hagas migración de datos.
Entrega el checklist de cobertura y los tests.
```

5. **Revisa** `docs/porting/<modulo>/checklist.md`, `supuestos.md` y
   `confianza.md` antes de aceptar el trabajo.

## Niveles de entrada

| Nivel | Entradas | Resultado |
| --- | --- | --- |
| **L1** | Solo catálogo | Lógica, triggers, mensajes y UI aproximada. Esquema parcial, inventario derivado de triggers y validaciones de campo pendientes. |
| **L2** | Catálogo + DDL | Esquema fiel: tipos, nulos, claves, constraints e índices. |
| **L3** | L2 + checklist de UI | Lista completa de bloques/ítems y propiedades fieles: obligatorio, longitud, máscara, LOV, defaults, visibilidad, `Column Name`. |

La skill siempre declara el nivel alcanzado y lista los pendientes. **No se debe
prometer un port completo con L1.**

## Reglas de oro

- **No inventar**: tablas, columnas, relaciones, LOVs, obligatorios, longitudes,
  máscaras, mensajes o roles que no estén en las entradas.
- **Cobertura de lo conocido**: cada bloque, ítem, trigger, LOV, relación, unidad
  de programa, mensaje, conflicto y warning **presente en el catálogo o en el
  checklist de UI** aparece como `Portado`, `No aplica` o `PENDIENTE` con motivo.
- **Nunca fallar en silencio**: lo que no se traduce se conserva en PL/SQL
  original dentro de `plsql/` y se marca `PENDIENTE`.
- **Mensajes exactos**: se conserva el texto y el código; no se reescriben.
- **Tests por regla portada**: cada validación o regla de negocio portada tiene su
  test; una regla pendiente indica qué test queda bloqueado y por qué.
- **Supuestos explícitos**: toda inferencia va a `supuestos.md` y se confirma.

## Qué produce el agente

```text
docs/porting/<MODULO>/
  perfil.md
  checklist.md
  supuestos.md
  decisiones.md
  confianza.md
  plsql/
  validaciones.md
app/
  Models/
  Enums/                 # opcional
  Services/<Modulo>/
  Observers/
  Rules/
  Policies/              # opcional
  Filament/Resources/
database/migrations/
database/factories/      # opcional
tests/Feature/<Modulo>/
tests/Unit/              # opcional
```

La estructura exacta se adapta a las convenciones de la aplicación destino.

## Licencia

MIT, igual que fmb2laravel.
