# Publicación (checklist)

Pasos para convertir este repo local en el proyecto público `fmb2laravel`.
Requieren la máquina del autor (git + cuenta de GitHub).

## 1. Instalar Git

```powershell
winget install --id Git.Git -e
# reinicia la terminal después
git --version
```

## 2. Crear el repositorio y primer commit

```powershell
cd C:\Users\Junior\Desktop\Proyectos\fmb2laravel
git init -b main
git add .
git commit -m "v0.1: extractor heurístico de .fmb + IR + CLI"
```

> La firma de commits se configura en `~/.gitconfig` (nombre/correo del autor).

## 3. Crear el repo en GitHub

Opción A — con `gh`:

```powershell
winget install --id GitHub.cli -e
gh auth login
gh repo create fmb2laravel --public --source . --push
```

Opción B — manual: crear `github.com/<usuario>/fmb2laravel` vacío y luego

```powershell
git remote add origin https://github.com/<usuario>/fmb2laravel.git
git push -u origin main
```

## 4. Detalles a elegir por el autor (buscar TODO-DECISION)

- [ ] **Titular del copyright** en `LICENSE` (hoy dice `fmb2laravel contributors`).
- [ ] ¿Cuenta personal u organización `fmb2laravel` en GitHub?
- [ ] Repositorio espejo del demo sintético de Form Builder 6i (se creará en la
      máquina con 6i) en `tests/fixtures/demo/` o releases.

## 5. Packagist (cuando haya release)

1. Crear tag: `git tag v0.1.0 && git push --tags`.
2. `composer.json` de la raíz usa el nombre `fmb2laravel/fmb2laravel` (proyecto);
   los paquetes divisibles son `packages/core` (`fmb2laravel/core`) y el futuro
   `packages/laravel` (`fmb2laravel/laravel`).
3. Publicar en packagist.org con la URL del repo. Verificar antes que
   `fmb2laravel/core` no exista (a la fecha de escritura: libre).

## 6. Badges del README

Cuando exista el repo, reemplazar en `README.md` / `README.es.md`:

```markdown
[![CI](https://github.com/<usuario>/fmb2laravel/actions/workflows/ci.yml/badge.svg)](...)
```

## Pendientes técnicos previos al anuncio (ver ROADMAP en README)

- Generador `forms:import` (v0.2).
- Demo sintético creado en la máquina con Forms 6i.
- Adaptadores XML/JDAPI (v0.3) y binarios PHPacker.
