# Filament UI

The default target is Filament 5 on Laravel 13. If the target app uses another
version or another UI stack, adapt the concepts; do not copy an API that is not
installed. Check `composer.json` before writing code.

The UI is a translation of the Forms screen, not a replica. Build a clean,
responsive interface that preserves behavior and labels; do not reproduce windows,
canvases, coordinates, toolbars or key bindings.

## Resource structure

Follow the app's existing structure. A common Filament 5 layout:

```text
app/Filament/Resources/Orders/
  OrderResource.php
  Schemas/OrderForm.php
  Tables/OrdersTable.php
  Pages/ListOrders.php
  Pages/CreateOrder.php
  Pages/EditOrder.php
  Pages/ViewOrder.php
  RelationManagers/OrderLinesRelationManager.php
```

```php
namespace App\Filament\Resources\Orders;

use App\Filament\Resources\Orders\Pages\CreateOrder;
use App\Filament\Resources\Orders\Pages\EditOrder;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\Orders\Schemas\OrderForm;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingCart;

    protected static ?string $navigationLabel = 'Pedidos';
    protected static ?string $modelLabel = 'pedido';
    protected static ?string $pluralModelLabel = 'pedidos';
    protected static ?string $recordTitleAttribute = 'code';

    public static function form(Schema $schema): Schema
    {
        return OrderForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OrdersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrders::route('/'),
            'create' => CreateOrder::route('/create'),
            'edit' => EditOrder::route('/{record}/edit'),
            'view' => ViewOrder::route('/{record}'),
        ];
    }
}
```

Use the source form language for labels when it is evidenced by the UI checklist
or messages; otherwise ask. Do not translate messages or data. The field names and
messages in the examples are illustrative: replace them with the confirmed column
and the exact source message. An item name is not necessarily a column name.

## Forms

```php
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

return $schema
    ->columns(2)
    ->components([
        Section::make('Datos generales')
            ->columns(2)
            ->schema([
                TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(10)
                    ->unique(ignoreRecord: true)
                    ->validationMessages([
                        'required' => 'El código es obligatorio.',
                        'unique' => 'El código ya existe.',
                    ]),

                Select::make('customer_id')
                    ->label('Cliente')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                DatePicker::make('ordered_at')
                    ->label('Fecha')
                    ->required(),

                Toggle::make('active')
                    ->label('Activo')
                    ->default(true),
            ]),

        Section::make('Observaciones')
            ->schema([
                Textarea::make('notes')
                    ->label('Notas')
                    ->rows(4)
                    ->columnSpanFull(),
            ]),
    ]);
```

### Field mapping

| Forms | Filament |
| --- | --- |
| Text item | `TextInput` |
| Multi-line text | `Textarea` |
| Date | `DatePicker` (with format) |
| Date + time | `DateTimePicker` |
| Number | `TextInput::numeric()` or `Select` for enums |
| Checkbox | `Toggle`/`Checkbox` |
| Radio group | `Radio` |
| List item | `Select` |
| LOV | `Select::relationship()` or searchable options |
| Button | `Action` |
| Display-only | `Placeholder`/infolist |
| Image | `FileUpload`/avatar |
| Hidden key | `Hidden` (but validate on the server) |

### Validation

Use field rules for shape and a custom `Rule` for business logic. See
`references/validaciones.md`. Keep messages exact. Use `dehydrated(false)` for
derived or display-only fields.

### Dynamic behavior

```php
use Filament\Schemas\Components\Utilities\Get;

TextInput::make('reason')
    ->required(fn (Get $get): bool => $get('status') === 'C')
    ->visible(fn (Get $get): bool => $get('status') === 'C');
```

For components that change other fields, use `->live()` and
`->afterStateUpdated()`, and re-check the result on the server. A client-side
lookup is a convenience, not a guarantee.

## Tables

```php
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

return $table
    ->columns([
        TextColumn::make('code')
            ->label('Código')
            ->searchable()
            ->sortable(),

        TextColumn::make('customer.name')
            ->label('Cliente')
            ->searchable()
            ->sortable(),

        TextColumn::make('status')
            ->label('Estado')
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'A' => 'success',
                'C' => 'gray',
                default => 'warning',
            }),

        TextColumn::make('total')
            ->label('Total')
            ->money('USD')
            ->sortable(),

        TextColumn::make('ordered_at')
            ->label('Fecha')
            ->dateTime('d/m/Y H:i')
            ->sortable(),
    ])
    ->filters([
        SelectFilter::make('status')
            ->label('Estado')
            ->options(/* status labels */),
    ])
    ->defaultSort('ordered_at', 'desc')
    ->recordActions([
        ViewAction::make(),
        EditAction::make(),
    ]);
```

Guidance:

- Use the field order from the UI checklist/Forms Builder. The IR has no order; if
  you use a business-sensible order, record it as an assumption.
- Confirm each item's `Column Name` before writing the field. Do not assume the
  item name is the column name.
- Make searchable only columns that make sense for the user; too many searchable
  columns hurt performance.
- Use badges for statuses/enums; map the stored code, not a translated label.
- Use the source date/number format when the checklist provides it; otherwise use
  the app convention.
- Add filters for `PRE-QUERY` behavior and for LOV/FK columns.
- Keep column order close to the Forms block order when it is meaningful.
- Use `toggleable()` for secondary columns.
- Do not show columns the form does not expose unless they help the user.

## Actions and buttons

```php
use Filament\Actions\Action;

Action::make('approve')
    ->label('Aprobar')
    ->icon('heroicon-o-check')
    ->requiresConfirmation()
    ->modalHeading('Aprobar pedido')
    ->modalDescription('Esta acción no se puede deshacer.')
    ->visible(fn (Order $record): bool => $record->status === 'P')
    ->action(fn (Order $record) => app(ApproveOrder::class)->handle($record));
```

- Put business logic in a service, not in the action closure.
- Destructive actions need confirmation and authorization.
- Map `ALERT` choices to confirmation modals; map informational messages to
  notifications.
- Use `recordActions()` for row actions, `toolbarActions()` for bulk actions and
  `headerActions()` for page actions (adapt to the installed version).
- Hide an action only for UX; enforce permission with a policy.

## Relation managers

For master-detail:

```php
public static function getRelations(): array
{
    return [
        OrderLinesRelationManager::class,
    ];
}
```

- The relation manager owns the child table and form.
- Set the foreign key on the server; do not trust a hidden field.
- Recompute totals after child changes.
- Confirm cascade/delete behavior.
- Test creating a child from the master page and unauthorized access.

## Infolists and view pages

- Use an infolist for read-only data and computed values.
- Do not put editable fields in a view page.
- Keep calculated values consistent with the service/accessor.

## Navigation

- Group resources by business area (`$navigationGroup`) when the app has groups.
- Use a meaningful icon and sort order.
- Do not expose internal/technical tables as navigation items.
- Do not reproduce the Forms menu; build the app's navigation. If the form menu is
  part of the requirements, map it to resources/pages and document it.

## Labels and language

- Default: the source form language.
- Labels from the screen checklist; otherwise humanize the item name and record
  the assumption in `supuestos.md`.
- Do not translate stored messages or codes.
- If the app uses translation files, add keys; otherwise use literal strings
  consistently.
- Keep labels short; use helper text for hints from the form.

## Files and exports

- File upload: configure disk, directory, visibility, max size and accepted types.
  Store the path, not the binary, unless the schema requires a BLOB.
- Download: streamed response or Filament action.
- Export/report: use the app's export package or a queued job; do not run a heavy
  report synchronously in a request.
- If the original uses `RUN_PRODUCT`/`RUN_REPORT_OBJECT`, the report contract must
  be specified; otherwise mark it `PENDIENTE`.

## Authorization

- Use policies/gates, not only hidden buttons.
- Map Forms roles/permissions if the form defines them; otherwise do not invent a
  role model.
- Test `canViewAny`, `canCreate`, `canUpdate`, `canDelete` for the main roles.
- Scope queries by tenant/company if the form does.

## Testing the UI

- Use Livewire tests for create/edit forms and table pages.
- Assert validation errors and messages.
- Assert that actions change state and send notifications.
- Smoke-test list/create/edit/view pages for each resource.
- Run the app's formatter after generating files.

## Anti-patterns

- Business logic in the resource/action closure.
- One resource per block when several blocks belong to one entity.
- A model for a control block.
- Reproducing Forms windows/coordinates.
- Trusting disabled or hidden fields.
- Translating or rewriting stored messages.
- Making every column searchable.
- Omitting authorization because the button is hidden.
- Generating a UI before the table mapping is confirmed.
