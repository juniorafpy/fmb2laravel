# AGENTS.md — fmb2laravel

Monorepo del producto comunitario `fmb2laravel` (MIT, público).

## Estructura

- `packages/core/` — lector heurístico de `.fmb`, IR, catálogo, comandos. PHP puro,
  **sin dependencias de runtime** (solo `ext-mbstring`).
- `bin/fmb2laravel` — CLI (usa el autoloader de Composer si existe; si no, uno propio).
- `panel/` — panel web (Laravel + Filament) para subir `.fmb` y ver el catálogo;
  sin login; consume `packages/core` vía path repository de Composer.
- `skills/fmb2laravel-port/` — skill para agentes IA (instrucciones EN + guía ES) que
  enseña a portar cualquier catálogo a Laravel + Filament; se distribuye aparte y no
  se incluye en el ZIP de catálogo.
- `tests/` — PHPUnit. Fixtures sintéticos en `tests/Fixtures/` (nunca se commitean
  `.fmb` reales; `forms/` está en `.gitignore`).
- `docs/` — documentación de la IR, compatibilidad y publicación.

## Comandos (Windows, esta máquina)

- PHP: `C:\Users\Junior\AppData\Local\Microsoft\WinGet\Packages\PHP.PHP.8.3_Microsoft.Winget.Source_8wekyb3d8bbwe\php.exe`
- Tests: `& $php vendor/bin/phpunit`
- Estilo: `& $php vendor/bin/pint` (no usar `--test` como sustituto; arreglar de verdad)

## Reglas

- `declare(strict_types=1)` y clases `final` salvo necesidad en `packages/core/` y
  `bin/`; `panel/` sigue las convenciones estándar de Laravel/Filament (estilo
  artisan, sin `strict_types`).
- Toda la salida de usuario en español (es el público inicial), docs EN principal + ES.
- Regla de oro: **nunca fallar en silencio** — lo no traducible se exporta como PL/SQL
  + aviso en `warnings` de la IR.
- No tocar `orcl-lara` (proyecto privado del autor) desde aquí; es solo consumidor.
