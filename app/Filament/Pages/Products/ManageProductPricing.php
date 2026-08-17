<?php

namespace App\Filament\Pages\Products;

use App\Enums\Permission;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Modules\Product\Models\Product;

class ManageProductPricing extends Page
{
    protected static ?string $slug = 'products/manage-pricing';

    protected static ?string $title = 'مدیریت قیمت‌گذاری';

    protected string $view = 'filament.pages.manage-product-pricing';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::ProductEdit->value) ?? false;
    }

    public ?array $data = [];

    public bool $isEditing = false;

    public static function getNavigationLabel(): string
    {
        return 'مدیریت قیمت‌گذاری';
    }

    public static function getNavigationGroup(): string
    {
        return 'مدیریت کالا';
    }

    public static function getNavigationSort(): ?int
    {
        return 2;
    }

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-currency-dollar';
    }

    public function mount(): void
    {
        $this->form->fill([
            'products' => Product::query()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'weight' => $p->weight,
                    'price_board_item' => $p->price_board_item,
                    'fee_business_hours' => $p->fee_business_hours,
                    'fee_off_hours' => $p->fee_off_hours,
                ])
                ->toArray(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Repeater::make('products')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('نام محصول')
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\TextInput::make('weight')
                            ->label('وزن (گرم)')
                            ->numeric()
                            ->disabled()
                            ->dehydrated(false),

                        Forms\Components\Select::make('price_board_item')
                            ->label('آیتم تابلو قیمت')
                            ->options([
                                'Gold995' => 'طلای ۹۹۵ (شمش)',
                                'Gold999' => 'طلای ۹۹۹',
                                'Gold9999' => 'طلای ۹۹۹.۹',
                                'Gold750' => 'طلای ۱۸ عیار (۷۵۰)',
                                'Gold705' => 'طلای ۱۷.۵ عیار (۷۰۵)',
                                'Silver990' => 'نقره ۹۹۰',
                                'Silver999' => 'نقره ۹۹۹',
                                'Silver9999' => 'نقره ۹۹۹.۹',
                                'Silver 925' => 'نقره ۹۲۵',
                                'Euro' => 'یورو',
                                'USDollar' => 'دلار آمریکا',
                            ])
                            ->searchable()
                            ->disabled(fn () => ! $this->isEditing)
                            ->dehydrated(),

                        Forms\Components\TextInput::make('fee_business_hours')
                            ->label('اجرت اداری (۹ تا ۱۷:۵۹)')
                            ->numeric()
                            ->suffix('٪')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->nullable()
                            ->disabled(fn () => ! $this->isEditing)
                            ->dehydrated(),

                        Forms\Components\TextInput::make('fee_off_hours')
                            ->label('اجرت غیراداری (۱۸ تا ۸:۵۹)')
                            ->numeric()
                            ->suffix('٪')
                            ->minValue(0)
                            ->maxValue(100)
                            ->step(0.01)
                            ->nullable()
                            ->disabled(fn () => ! $this->isEditing)
                            ->dehydrated(),
                    ])
                    ->columns(5)
                    ->defaultItems(0)
                    ->collapsible(),
            ])
            ->statePath('data');
    }

    public function toggleEdit(): void
    {
        $this->isEditing = ! $this->isEditing;
    }

    public function save(): void
    {
        $data = $this->form->getState();

        if (empty($data['products'])) {
            Notification::make()
                ->title('خطا')
                ->body('هیچ محصولی برای بروزرسانی وجود ندارد.')
                ->danger()
                ->send();

            return;
        }

        $updated = 0;

        foreach ($data['products'] as $item) {
            Product::where('id', $item['id'])->update([
                'price_board_item' => $item['price_board_item'] ?? null,
                'fee_business_hours' => $item['fee_business_hours'] ?? 0,
                'fee_off_hours' => $item['fee_off_hours'] ?? 0,
            ]);
            $updated++;
        }

        $this->isEditing = false;

        Notification::make()
            ->title('ذخیره شد')
            ->body("{$updated} محصول بروزرسانی شد.")
            ->success()
            ->send();
    }
}
