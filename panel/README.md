# fmb2laravel panel

Web panel for [fmb2laravel](../): upload Oracle Forms modules (`.fmb`) in the browser
and browse/download the extracted catalog. Pure PHP, **no Oracle and no login** — it
is a local tool.

## Requirements

- PHP >= 8.3 with `ext-mbstring`, `ext-zip` and `pdo_sqlite`

## Install and run

```bash
cd panel
composer install
cp .env.example .env    # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate     # accept creating database/database.sqlite
php artisan serve --port=8001
```

Open http://localhost:8001: the panel starts directly on the imports list (there is
no login screen). Press **Importar formulario** → drop a `.fmb` → **Importar y
procesar**.

## What you get

For every import:

- a summary: triggers, program units, version conflicts, blocks/items, tables read
  and written, messages and the heuristic warnings;
- downloadable files: `<form>.ir.json`, `<form>.triggers.sql`, `<form>.triggers.json`,
  `<form>.program_units.json`, `<form>.conflicts.json`, `<form>.reglas.json`,
  `<form>.reglas.md` (the readable report) and `<form>-catalogo.zip` with everything.

Everything stays private: uploads and results live in
`storage/app/private/fmb-imports/{id}` and are served through a whitelisted route,
never from `public/`.

## Tests

```bash
php artisan test
```

## Notes

- `fmb2laravel/core` is required through a Composer *path repository*
  (`../packages/core`), so a normal `git clone` of the product repo is all you need.
- Trigger coverage is heuristic: whatever cannot be translated is still exported as
  PL/SQL and flagged in the summary (the product's golden rule: never fail silently).

## License

MIT — same as fmb2laravel.

---

# panel de fmb2laravel

Panel web de [fmb2laravel](../): sube módulos de Oracle Forms (`.fmb`) desde el
navegador y explora/descarga el catálogo extraído. PHP puro, **sin Oracle y sin
login** — es una herramienta local.

## Requisitos

- PHP >= 8.3 con `ext-mbstring`, `ext-zip` y `pdo_sqlite`

## Instalación y arranque

```bash
cd panel
composer install
cp .env.example .env    # Windows: copy .env.example .env
php artisan key:generate
php artisan migrate     # acepta crear database/database.sqlite
php artisan serve --port=8001
```

Abre http://localhost:8001: el panel arranca directo en la lista de importaciones
(no hay pantalla de login). Pulsa **Importar formulario** → suelta un `.fmb` →
**Importar y procesar**.

## Qué obtienes

Por cada importación:

- un resumen: triggers, program units, conflictos de versiones, bloques/items, tablas
  leídas y escritas, mensajes y las advertencias de la lectura heurística;
- archivos descargables: `<form>.ir.json`, `<form>.triggers.sql`, `<form>.triggers.json`,
  `<form>.program_units.json`, `<form>.conflicts.json`, `<form>.reglas.json`,
  `<form>.reglas.md` (el informe legible) y `<form>-catalogo.zip` con todo.

Todo queda privado: subidas y resultados viven en
`storage/app/private/fmb-imports/{id}` y se sirven por una ruta con lista blanca,
nunca desde `public/`.

## Tests

```bash
php artisan test
```

## Notas

- `fmb2laravel/core` se instala vía *path repository* de Composer
  (`../packages/core`): con clonar el repo del producto es suficiente.
- La cobertura de triggers es heurística: lo que no se puede traducir se exporta
  igualmente como PL/SQL y se avisa en el resumen (la regla de oro del producto:
  nunca fallar en silencio).

## Licencia

MIT — igual que fmb2laravel.
