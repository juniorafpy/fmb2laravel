# Decisions — `<MODULE>`

> Record every decision that is not a direct translation of the catalog. Keep it
> short and traceable. Do not use this file to hide a pending item; pending goes
> in `checklist.md`.

## Trigger conflicts

| Object | Kept PID | Kept date | Discarded versions | Reason |
| --- | --- | --- | --- | --- |
| `<trigger name>` | `P...` | `<date>` | `P...` (`<lines>` lines) | Newest version per catalog |

## No aplica decisions

| Object | Type | Reason |
| --- | --- | --- |
| `<trigger/built-in>` | Trigger / built-in | Forms navigation, window chrome, boilerplate LOV with no logic, … |

## Schema mapping

| Block | Table / view | Evidence | Notes |
| --- | --- | --- | --- |
| `<B_...>` | `<TABLE>` | DDL / trigger SQL / user | Preserve names / mapped names |

## Item → column mapping

| Block | Item | Column | Evidence | Notes |
| --- | --- | --- | --- | --- |
| `<B_...>` | `<ITEM>` | `<COLUMN>` | Checklist `Column Name` / DDL / SQL | Item name is not a column name |

## UI mapping

| Forms object | Filament object | Notes |
| --- | --- | --- |
| Block | Resource / relation manager | |
| Tab canvas | Section / tabs | |
| Button | Action | |
| LOV | Select / relationship | |
| Control block | Filter / action / widget | |

## Other decisions

| Decision | Alternatives | Why |
| --- | --- | --- |
| | | |
