# Compatibilidad

## Versiones de Oracle Forms

| Versión de Forms | Lector | Estado |
| --- | --- | --- |
| 6i (6.0.8.x) | Heurístico (binario) | ✅ Validado contra un formulario real de producción (176 triggers, byte-idéntico al prototipo) |
| 9i / 10g / 11g / 12c | Heurístico (mejor esfuerzo) | ⏳ Pendiente de pruebas con módulos reales |
| Cualquiera con `frmf2xml` | Adaptador XML | 🔜 v0.3 |
| Cualquiera con JDAPI | Adaptador JDAPI | 🔜 v0.3 |

## Requisitos

- PHP **8.2+** con `ext-mbstring`. Sin dependencias de runtime.
- **No requiere Oracle** ni Java ni Forms Builder instalado.

## Qué extrae (v0.1)

| Elemento | Soporte |
| --- | --- |
| Triggers PL/SQL (todas las versiones guardadas en el binario) | ✅ se conserva la más reciente |
| Nombre, tipo y ámbito (`BLOQUE.ITEM`) de cada trigger | ✅ |
| Fecha de compilación (del encabezado `P<id>`) | ✅ |
| Program units / packages | ✅ (los capturados por el lector) |
| Deduplicación de versiones y conflictos | ✅ |
| Pool de cadenas (`strings`) | ✅ comando `strings` |
| Lista completa de bloques/items con propiedades | ⏳ requiere adaptador XML/JDAPI (v0.3) |
| LOVs, canvas, triggers de menú de Forms | ⏳ v0.3 |

## Límites conocidos del lector heurístico

- Los objetos cuyo código **no comienza con `BEGIN`/`DECLARE`** (p. ej. un
  `POST-QUERY` iniciado por un comentario) pueden no capturarse asociados a su
  nombre; el código suele seguir visible en el pool de cadenas y se reporta como
  aviso `HEURISTIC-LIMITS`.
- Módulos compilados con ofuscación o versiones muy antiguas pueden requerir
  revisión manual (`fmb2laravel inspect` primero).
- La detección de tablas/mensajes es por expresiones regulares sobre el PL/SQL:
  sirve como inventario para el porting, no como análisis semántico.
