# fmb2laravel

[![CI](https://github.com/juniorafpy/fmb2laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/juniorafpy/fmb2laravel/actions/workflows/ci.yml)

**Convierte módulos de Oracle Forms (`.fmb`) en esqueletos de aplicaciones Laravel + Filament — sin instalar Oracle.**

> Estado: **alpha (v0.1)**. El núcleo de extracción está probado contra un formulario real
> de producción (Oracle Forms 6i, 177 triggers). El generador de código Laravel/Filament
> (v0.2) y los binarios autónomos (v0.3) están en el roadmap.

## Por qué

Miles de empresas siguen ejecutando lógica crítica dentro de Oracle Forms. Leer un `.fmb`
normalmente requiere tener Forms Builder instalado, Java + JDAPI o consultoría costosa.
`fmb2laravel` toma otro camino:

- lee el binario `.fmb` **directamente** (parser heurístico, PHP puro, sin dependencias),
- recupera todos los triggers PL/SQL, quedándose con la **versión más reciente** de cada objeto,
- construye una **IR (representación intermedia)** legible por máquinas y un reporte humano
  de reglas de negocio (tablas leídas/escritas, mensajes, items, llamadas externas),
- (roadmap) genera modelos/migraciones Laravel y recursos Filament a partir de la IR.

## Inicio rápido

Requiere PHP >= 8.2 con `ext-mbstring`.

```bash
git clone https://github.com/juniorafpy/fmb2laravel
cd fmb2laravel

# 0. Ya puedes probar el CLI sin instalar nada (composer es solo para desarrollo):
php bin/fmb2laravel inspect tests/Fixtures/demo.fmb

# 1. Ver qué hay dentro de un módulo (no escribe nada):
php bin/fmb2laravel inspect /ruta/A/MIFORM.fmb

# 2. Extraer el catálogo completo:
php bin/fmb2laravel extract /ruta/A/MIFORM.fmb --out=catalog
```

`extract` genera:

| Archivo | Contenido |
| --- | --- |
| `<form>.ir.json` | Representación intermedia: bloques, items, triggers, tablas, mensajes, avisos |
| `<form>.triggers.sql` | El PL/SQL de cada trigger (versión más nueva), listo para revisar |
| `<form>.triggers.json` | Extracción estructurada: nombre, tipo, ámbito, fecha de versión |
| `<form>.program_units.json` | Program units (paquetes/procedimientos) encontrados |
| `<form>.conflicts.json` | Objetos con versiones divergentes (se marca la conservada) |
| `<form>.reglas.json` / `<form>.reglas.md` | Análisis de reglas de negocio para el porting |

## Cómo funciona

Un `.fmb` de Oracle Forms 6i guarda el PL/SQL compilado como registros con longitud:

```
[len]"P<id>_<DD>_<MMM>_<AAAA><HH>_<MM>_<SS>"   <- id de objeto + fecha de compilación
[len]"BEGIN\n" ... [len]chunk ... [len]"\nEND;"  <- cuerpo PL/SQL
[len]"WHEN-VALIDATE-ITEM (B_TAREAS.COD_X)"       <- nombre del objeto
```

El parser busca esos registros, los decodifica (CP1252 → UTF-8), agrupa por nombre de
objeto, conserva la compilación más reciente y marca conflictos cuando dos versiones
difieren. Una pasada de respaldo recupera registros antiguos sin encabezado `P<id>`.

## Cobertura y honestidad

- La herramienta **nunca descarta código en silencio**: todo lo que la heurística no
  traduce queda exportado como PL/SQL en el catálogo, y el reporte de cobertura señala
  lo que necesita revisión manual.
- La sección `warnings` de la IR documenta los límites heurísticos de cada formulario.
- `.fmb` es un formato binario indocumentado: los resultados se validan con formularios
  reales, pero no existe garantía a nivel binario. Si un módulo fue compilado con
  ofuscación o una versión muy antigua, empieza con `fmb2laravel inspect`.

## Roadmap

- **v0.1** — extractor núcleo (esto): lector heurístico, IR, catálogo, CLI `inspect`/`extract`.
- **v0.2** — paquete Laravel `forms:import`: modelos, migraciones y recursos Filament
  generados desde la IR.
- **v0.3** — adaptadores opcionales XML (`frmf2xml` / JDAPI) para extracción 100% fiel.
- **v1.0** — binarios autónomos (PHPacker), documentación completa, formulario demo
  sintético y lanzamiento público.

## Licencia

MIT. Ver [LICENSE](LICENSE).
