# fmb2laravel

[![CI](https://github.com/juniorafpy/fmb2laravel/actions/workflows/ci.yml/badge.svg)](https://github.com/juniorafpy/fmb2laravel/actions/workflows/ci.yml)

**Turn Oracle Forms modules (`.fmb`) into Laravel + Filament application scaffolds — without installing Oracle.**

> Status: **alpha (v0.1)**. The extraction core is field-tested against a real 177-trigger
> Oracle Forms 6i production form. The Laravel/Filament code generator (v0.2) and the
> standalone binaries (v0.3) are on the roadmap.

## Why

Thousands of businesses still run mission-critical logic inside Oracle Forms. Reading a
`.fmb` file normally requires a Forms Builder installation, Java + JDAPI, or expensive
consulting. `fmb2laravel` takes a different path:

- reads the `.fmb` binary **directly** (heuristic parser, pure PHP, no dependencies),
- recovers every PL/SQL trigger, keeping the **newest version** of each object,
- builds a machine-readable **IR (Intermediate Representation)** plus a human report of
  business rules (tables read/written, messages, items, external calls),
- (roadmap) generates Laravel models/migrations and Filament resources from that IR.

## Quickstart

Requires PHP >= 8.2 with `ext-mbstring`.

```bash
git clone https://github.com/juniorafpy/fmb2laravel
cd fmb2laravel

# 0. You can try the CLI without installing anything (composer is for development only):
php bin/fmb2laravel inspect tests/Fixtures/demo.fmb

# 1. See what's inside a module (writes nothing):
php bin/fmb2laravel inspect /path/to/MYFORM.fmb

# 2. Extract the full catalog:
php bin/fmb2laravel extract /path/to/MYFORM.fmb --out=catalog
```

`extract` produces:

| File | Contents |
| --- | --- |
| `<form>.ir.json` | Intermediate representation: blocks, items, triggers, tables, messages, warnings |
| `<form>.triggers.sql` | Every trigger's PL/SQL (newest version), ready to review |
| `<form>.triggers.json` | Structured extraction: name, type, scope, version date |
| `<form>.program_units.json` | Program units (packages/procedures) found in the module |
| `<form>.conflicts.json` | Objects found with diverging versions (kept one is marked) |
| `<form>.reglas.json` / `<form>.reglas.md` | Business-rule analysis for the porting work |

## How it works

An Oracle Forms 6i `.fmb` stores compiled PL/SQL as length-prefixed records:

```
[len]"P<id>_<DD>_<MON>_<YYYY><HH>_<MM>_<SS>"   <- object id + compilation date
[len]"BEGIN\n" ... [len]chunk ... [len]"\nEND;"  <- PL/SQL body
[len]"WHEN-VALIDATE-ITEM (B_TAREAS.COD_X)"       <- object name
```

The parser scans for those records, decodes them (CP1252 → UTF-8), groups by object
name, keeps the newest compilation, and flags conflicts when two versions disagree.
A fallback pass recovers old records that predate the `P<id>` headers.

## Coverage and honesty

- The tool **never silently drops code**: whatever the heuristics cannot translate is
  still exported as PL/SQL in the catalog, and the coverage report lists what needs
  manual review.
- The IR `warnings` section documents heuristic limits for each form.
- `.fmb` is an undocumented binary format: results are validated on real forms but a
  binary-level guarantee is impossible. If a module was compiled with obfuscation or a
  very old version, use `fmb2laravel inspect` first.

## Roadmap

- **v0.1** — core extractor (this): heuristic reader, IR, catalog, `inspect`/`extract` CLI.
- **v0.2** — `forms:import` Laravel package: models, migrations and Filament resources
  generated from the IR.
- **v0.3** — optional `frmf2xml` / JDAPI XML adapters for 100%-fidelity extraction.
- **v1.0** — standalone binaries (PHPacker), full docs, synthetic demo form, public launch.

## License

MIT. See [LICENSE](LICENSE).
