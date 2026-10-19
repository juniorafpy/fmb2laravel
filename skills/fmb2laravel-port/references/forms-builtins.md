# Forms built-ins and session variables

Forms code is full of `:SYSTEM`, `:GLOBAL`, `:PARAMETER` and built-in calls that
have no direct Laravel equivalent. Do not translate them mechanically. Decide what
the code is trying to do and map that intent to the web architecture.

## Session variables

### `:SYSTEM.*`

| Variable | Meaning | Laravel mapping |
| --- | --- | --- |
| `CURRENT_ITEM` | Item with focus | Current field / request context |
| `CURSOR_ITEM` | Item under the cursor | Usually `No aplica` |
| `CURRENT_BLOCK` / `CURSOR_BLOCK` | Current block | Current resource/section |
| `CURRENT_RECORD` / `CURSOR_RECORD` | Current record | Route model / selected row |
| `TRIGGER_ITEM`, `TRIGGER_BLOCK`, `TRIGGER_RECORD` | Object that fired the trigger | Context passed to the service |
| `RECORD_STATUS` | `NEW`, `CHANGED`, `QUERY`, `INSERT` | `exists` / `isDirty()` / UI state |
| `BLOCK_STATUS` | `NEW`, `CHANGED`, `QUERY` | Form/table state |
| `FORM_STATUS` | `NEW`, `CHANGED`, `QUERY` | Page state |
| `MODE` | `NORMAL`, `ENTER-QUERY`, `QUERY` | Filter/search mode |
| `MESSAGE_LEVEL` | Message severity threshold | Log level / notification policy |
| `MESSAGE_TYPE` | Message type | Notification type |
| `LAST_QUERY` | Last query executed | Debug/audit only |
| `COORDINATION_OPERATION` | Master-detail coordination | Relation manager context |
| `EVENT_WINDOW`, `EVENT_OBJECT` | Event source | UI event context |
| `SUPPRESS_WORKING` | Hide working dialog | `No aplica` |
| `VALIDATION` | Validation unit | Usually `No aplica` |

Rules:

- If a trigger only uses `:SYSTEM` for navigation or focus, it is usually
  `No aplica`.
- If behavior depends on `RECORD_STATUS`, map it to `exists`/`isDirty()` rather
  than a hidden field.
- If behavior depends on `MODE` (query vs normal), map it to explicit filter mode
  or a separate action.
- Never expose `:SYSTEM` values to the client as trusted input.

### `:GLOBAL.*`

Forms globals are session-scoped variables shared across forms (for example the
current company, user, module or form name).

Mapping options, in order of preference:

1. **Request context**: a `FormContext`/DTO passed to the service.
2. **Authenticated user/session**: `auth()->user()`, session, or a tenant scope.
3. **Database**: when the value must survive requests and be auditable.
4. **Cache**: for short-lived shared state, with an explicit key and TTL. Never
   cache a per-user/session/tenant value under a global key: include the
   user/session/tenant in the key, or prefer request state.

Do not use PHP globals or static properties. They leak between requests and tests.
If a global is set in one trigger and read in another, trace the full life cycle
and make the dependency explicit in the service signature.

### `:PARAMETER.*`

Forms parameters are passed by `CALL_FORM`/`OPEN_FORM` or the runtime.

Map them to:

- route parameters / filters;
- action arguments;
- config values;
- a DTO created at the entry point.

If a parameter is optional, make the default explicit. Do not read it from a
global.

### Block variables (`:VARIABLES.*` or a control block)

Non-database items used as scratch state.

- Map them to local variables, a DTO or form state.
- If the value must be validated or persisted, decide where it belongs; do not
  create a column just to hold a scratch value.
- If a block named like a variables block exists, treat it as a control block, not
  a table.

## Built-ins by group

### Data and records

| Built-in | Behavior | Laravel mapping |
| --- | --- | --- |
| `COMMIT_FORM` | Commit the current transaction | `DB::transaction` / save action |
| `POST` | Post changes without commit | `save()` inside a transaction, no commit boundary |
| `ROLLBACK` | Roll back | Exception + transaction rollback |
| `CLEAR_FORM` | Clear all blocks | Reset form state |
| `CLEAR_BLOCK` | Clear a block | Reset section/table |
| `CREATE_RECORD` | Create an empty record | Create action/defaults |
| `DELETE_RECORD` | Delete current record | Delete action + confirmation |
| `DUPLICATE_RECORD` | Duplicate current record | Replicate action |
| `EXECUTE_QUERY` | Run the query | Apply filters / refresh table |
| `ENTER_QUERY` | Enter query mode | Enter filter mode |
| `COUNT_QUERY` | Count query results | `count()` / pagination total |
| `FETCH_RECORDS` | Fetch rows | Pagination/iteration |
| `SELECT_ALL` | Select all | Bulk action |
| `FIND_*` | Find a record | Search/filter |

Preserve transaction boundaries and failure behavior. A `COMMIT_FORM` in the
middle of a multi-step trigger is not the same as saving each field.

### Navigation and forms

| Built-in | Laravel mapping |
| --- | --- |
| `GO_ITEM`, `GO_BLOCK` | Focus/redirect; often `No aplica` |
| `NEXT_ITEM`, `PREVIOUS_ITEM` | `No aplica` (web navigation) |
| `NEXT_BLOCK`, `PREVIOUS_BLOCK` | `No aplica` |
| `NEXT_RECORD`, `PREVIOUS_RECORD` | Table pagination/selection |
| `UP`, `DOWN`, `SCROLL_UP`, `SCROLL_DOWN` | `No aplica` |
| `EXIT_FORM` | Redirect/logout; define the target |
| `CALL_FORM`, `OPEN_FORM`, `NEW_FORM` | Route/Livewire navigation or modal |
| `CLOSE_FORM` | Close modal/redirect |
| `SHOW_MENU`, `HIDE_MENU` | `No aplica` (navigation is the app shell) |

If `CALL_FORM` passes parameters, map them to route/action arguments and validate
them on arrival. Do not use hidden globals.

### Messages, alerts and errors

| Built-in | Laravel mapping |
| --- | --- |
| `MESSAGE` | Notification/log, depending on `MESSAGE_LEVEL`/`MESSAGE_TYPE` |
| `ALERT`, `SHOW_ALERT` | Modal/confirmation/notification |
| `SET_ALERT_PROPERTY`, `GET_ALERT_PROPERTY` | Alert definition; usually static |
| `BEL` | `No aplica` (terminal bell) |
| `RAISE FORM_TRIGGER_FAILURE` | Abort operation via validation/domain exception |
| `RAISE_APPLICATION_ERROR` | Exception with code; keep the code |
| `FORM_SUCCESS`, `FORM_FAILURE`, `FORM_FATAL` | Return status/exception mapping |

Read the alert definition if available; the message text may not be in the
trigger. Preserve button semantics (OK/Cancel/Yes/No) as confirmation behavior.

### Item, block and window properties

| Built-in | Laravel mapping |
| --- | --- |
| `SET_ITEM_PROPERTY(... REQUIRED)` | Dynamic required rule |
| `SET_ITEM_PROPERTY(... ENABLED)` | `disabled()`/`readOnly()` |
| `SET_ITEM_PROPERTY(... VISIBLE)` | `hidden()`/`visible()` |
| `SET_ITEM_PROPERTY(... UPDATE_ALLOWED)` | Read-only field + server rule |
| `SET_ITEM_PROPERTY(... LOV_NAME)` | Relationship/options |
| `GET_ITEM_PROPERTY` | Read UI state; usually `No aplica` |
| `SET_BLOCK_PROPERTY(... WHERE_CLAUSE)` | Query scope (never trust client input) |
| `SET_BLOCK_PROPERTY(... DEFAULT_WHERE)` | Base scope |
| `SET_WINDOW_PROPERTY`, `GET_WINDOW_PROPERTY` | `No aplica` (Forms chrome) |
| `SET_LOV_PROPERTY`, `GET_LOV_PROPERTY` | Options/relationship config |
| `SET_MENU_ITEM_PROPERTY` | Navigation/permissions |

Dynamic UI rules must be mirrored on the server. A hidden or disabled field is not
a security control.

### LOVs and lists

| Built-in | Laravel mapping |
| --- | --- |
| `SHOW_LOV`, `LIST_VALUES` | `Select`/relationship with search |
| `GET_LIST_ELEMENT_*`, `SET_LIST_ELEMENT_*` | Options/static list |
| `POPULATE_LIST`, `CLEAR_LIST` | Dynamic options |

The catalog does not contain LOV queries. Ask for them, or mark the LOV pending.
A free-text field is not a LOV.

### Files, environment and external systems

| Built-in | Laravel mapping |
| --- | --- |
| `READ_IMAGE_FILE`, `WRITE_IMAGE_FILE` | Storage/upload/download |
| `TEXT_IO` / file I/O | `Storage`; flag paths and encoding |
| `HOST` | Queued process/job; usually `PENDIENTE` |
| `TOOL_ENV`, `GET_ENV` | Config/env; do not expose secrets |
| `GET_APPLICATION_PROPERTY` | Config |
| `RUN_PRODUCT`, `RUN_REPORT_OBJECT` | Export/report action or queued job |
| `CALL_INPUT`, `CALL_QUERY` | Modal/dialog; often `No aplica` |
| `PAUSE` | `No aplica` |
| `SYNCHRONIZE` | `No aplica` |

External calls and reports are often the riskiest part of a port. If the target
contract (endpoint, file format, printer, report template) is not specified, mark
them `PENDIENTE` and preserve the original code.

### Timers

| Built-in | Laravel mapping |
| --- | --- |
| `CREATE_TIMER`, `SET_TIMER`, `DELETE_TIMER` | Scheduler, queued job or polling |

Choose by purpose:

- periodic data refresh → polling or Livewire refresh;
- reminder/timeout → scheduled job;
- background batch → queued job/command;
- UI animation → `No aplica`.

Never sleep in a request to imitate a Forms timer.

### Miscellaneous

| Built-in | Laravel mapping |
| --- | --- |
| `NAME_IN`, `COPY` | Variable assignment; rarely needed |
| `DEFAULT_VALUE` | Field default |
| `EXECUTE_TRIGGER` | Explicit service call; avoid indirect trigger execution |
| `DO_KEY` | Explicit action; `No aplica` for key emulation |
| `SET_APPLICATION_PROPERTY` | Config (rare) |
| `GET_FORM_PROPERTY` | Form metadata; `No aplica` |
| `GET_VIEW_PROPERTY`, `GET_CANVAS_PROPERTY` | `No aplica` |
| `GET_GROUP_*` | Record group/LOV metadata; usually `No aplica` |

## Trigger failure semantics

`RAISE FORM_TRIGGER_FAILURE` aborts the current trigger and keeps the user on the
form. In Laravel:

- validation failure → `ValidationException` with field messages;
- domain rule → a domain exception mapped to a 4xx/user message;
- unexpected error → log with context, show a safe message, keep the transaction
  rolled back.

Do not catch a failure and continue with a partial write. Do not convert a hard
failure into a success notification.

## What to mark `No aplica`

- window/canvas/coordinate behavior;
- keyboard navigation and Forms toolbars;
- terminal bell, pause, synchronize;
- Forms menu show/hide;
- LOV boilerplate already provided by the UI;
- `GO_ITEM`/`GO_BLOCK` used only for navigation.

For each one, write the reason in the checklist. If the code also validates,
defaults or writes, it is not `No aplica`; port the business part.
