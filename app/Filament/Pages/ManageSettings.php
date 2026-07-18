<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ManageSettings extends Page
{
    protected static ?string $slug = 'settings';

    protected static ?string $title = 'تنظیمات';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'تنظیمات';

    protected static string|\UnitEnum|null $navigationGroup = 'تنظیمات';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.manage-settings';

    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'tax_gold' => setting('tax_gold', 0),
            'tax_silver' => setting('tax_silver', 0),
            'hesabfa_api_key' => config('hesabfa.api_key'),
            'hesabfa_login_token' => config('hesabfa.login_token'),
            'hesabfa_default_project' => config('hesabfa.default_project'),
            'hesabfa_shipping_item_code' => config('hesabfa.shipping_item_code'),
            'hesabfa_installment_fee_item_code' => config('hesabfa.installment_fee_item_code'),
            'hesabfa_warehouse_code' => config('hesabfa.warehouse_code'),
            'hesabfa_customer_node' => config('hesabfa.customer_node'),
            'hesabfa_customer_family' => config('hesabfa.customer_family'),
            'hesabfa_draft_invoice' => config('hesabfa.draft_invoice'),
            'hesabfa_use_current_date' => config('hesabfa.use_current_date'),
            'hesabfa_auto_sync' => config('hesabfa.auto_sync'),
            'hesabfa_sync_stock' => config('hesabfa.sync_stock'),
            'hesabfa_sync_interval' => config('hesabfa.sync_interval'),
            'hesabfa_enable_warehouse_receipt' => config('hesabfa.enable_warehouse_receipt'),
            'hesabfa_enable_reserved_stock' => config('hesabfa.enable_reserved_stock'),
            'hesabfa_webhook_secret' => config('hesabfa.webhook_secret'),
            'show_price_with_tax' => setting('show_price_with_tax', true),
            'tapsi_emergency_status' => setting('tapsi_emergency_status', 'open'),
            'tapsi_auth_token' => config('tapsi.auth_token'),
            'tapsi_auth_name' => config('tapsi.auth_name'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('settings')
                    ->tabs([
                        Tabs\Tab::make('tax')
                            ->label('مالیات')
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Section::make('درصد مالیات')
                                    ->description('برای هر نوع فلز می‌توانید درصد مالیات جداگانه تعیین کنید')
                                    ->icon('heroicon-o-calculator')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('tax_gold')
                                                ->label('درصد مالیات طلا')
                                                ->numeric()
                                                ->suffix('%')
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->step(0.01)
                                                ->default(0),
                                            TextInput::make('tax_silver')
                                                ->label('درصد مالیات نقره')
                                                ->numeric()
                                                ->suffix('%')
                                                ->minValue(0)
                                                ->maxValue(100)
                                                ->step(0.01)
                                                ->default(10),
                                        ]),
                                    ]),
                            ]),
                        Tabs\Tab::make('hesabfa')
                            ->label('حسابفا')
                            ->icon('heroicon-o-arrow-path')
                            ->schema($this->getHesabfaSchema()),
                        Tabs\Tab::make('display')
                            ->label('نمایش')
                            ->icon('heroicon-o-eye')
                            ->schema([
                                Section::make('تنظیمات نمایش قیمت')
                                    ->description('تعیین کنید قیمت‌های نمایش داده شده در سایت با مالیات باشد یا خیر')
                                    ->icon('heroicon-o-currency-dollar')
                                    ->schema([
                                        Toggle::make('show_price_with_tax')
                                            ->label('نمایش قیمت با مالیات')
                                            ->helperText('اگر فعال باشد، قیمت محصولات با احتساب مالیات نمایش داده می‌شود'),
                                    ]),
                            ]),
                        Tabs\Tab::make('tapsi')
                            ->label('تپسی شاپ')
                            ->icon('heroicon-o-truck')
                            ->schema([
                                Section::make('تنظیمات تپسی شاپ')
                                    ->description('ارسال محصولات و قیمت‌ها به تپسی شاپ')
                                    ->icon('heroicon-o-cog-6-tooth')
                                    ->schema([
                                        Grid::make(2)->schema([
                                            TextInput::make('tapsi_auth_token')
                                                ->label('توکن احراز هویت')
                                                ->maxLength(255)
                                                ->placeholder('اختیاری - اگر خالی باشد از تنظیمات دیتابیس خوانده می‌شود'),
                                            TextInput::make('tapsi_auth_name')
                                                ->label('نام توکن')
                                                ->maxLength(255)
                                                ->default('zioto_sync_node'),
                                        ]),
                                    ]),

                                Section::make('کلید اضطراری (Kill Switch)')
                                    ->description('با فعال کردن این گزینه، موجودی تمام محصولات ارسالی به تپسی شاپ صفر ارسال می‌شود')
                                    ->icon('heroicon-o-exclamation-triangle')
                                    ->schema([
                                        Select::make('tapsi_emergency_status')
                                            ->label('وضعیت اضطراری')
                                            ->options([
                                                'open' => 'باز (عادی)',
                                                'closed' => 'بسته (اضطراری) - موجودی صفر',
                                            ])
                                            ->default('open'),
                                    ]),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    private function getHesabfaSchema(): array
    {
        return [
            Section::make('اعتبارنامه API')
                ->description('کلید API و توکن ورود حسابفا')
                ->icon('heroicon-o-key')
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('hesabfa_api_key')
                            ->label('کلید API')
                            ->maxLength(255),
                        TextInput::make('hesabfa_login_token')
                            ->label('توکن ورود')
                            ->maxLength(255),
                    ]),
                ]),

            Section::make('تنظیمات فاکتور')
                ->icon('heroicon-o-document-text')
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('hesabfa_default_project')
                            ->label('نام پروژه')
                            ->default('سایت ZIOTO'),
                        TextInput::make('hesabfa_warehouse_code')
                            ->label('کد انبار')
                            ->default('11'),
                    ]),
                    Grid::make(2)->schema([
                        Toggle::make('hesabfa_draft_invoice')
                            ->label('ایجاد فاکتور به صورت پیش‌نویس'),
                        Toggle::make('hesabfa_use_current_date')
                            ->label('استفاده از تاریخ امروز'),
                    ]),
                ]),

            Section::make('کد کالاها')
                ->icon('heroicon-o-cube')
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('hesabfa_shipping_item_code')
                            ->label('کد کالای ارسال')
                            ->maxLength(50),
                        TextInput::make('hesabfa_installment_fee_item_code')
                            ->label('کد کالای کارمزد اقساطی')
                            ->maxLength(50),
                    ]),
                ]),

            Section::make('تنظیمات مشتری')
                ->icon('heroicon-o-users')
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('hesabfa_customer_node')
                            ->label('نام گره مشتریان')
                            ->default('مشتریان'),
                        TextInput::make('hesabfa_customer_family')
                            ->label('خانواده مشتریان')
                            ->default('اشخاص : مشتریان'),
                    ]),
                ]),

            Section::make('تنظیمات همگام‌سازی')
                ->icon('heroicon-o-arrow-path')
                ->collapsible()
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('hesabfa_auto_sync')
                            ->label('همگام‌سازی خودکار سفارشات'),
                        Toggle::make('hesabfa_sync_stock')
                            ->label('همگام‌سازی موجودی از حسابفا'),
                    ]),
                    Grid::make(3)->schema([
                        TextInput::make('hesabfa_sync_interval')
                            ->label('فاصله همگام‌سازی (دقیقه)')
                            ->numeric()
                            ->default(60)
                            ->minValue(5)
                            ->maxValue(1440),
                        Toggle::make('hesabfa_enable_warehouse_receipt')
                            ->label('صدور رسید انبار خودکار'),
                        Toggle::make('hesabfa_enable_reserved_stock')
                            ->label('فعال‌سازی موجودی رزرو شده'),
                    ]),
                ]),

            Section::make('وب‌هوک')
                ->icon('heroicon-o-globe-alt')
                ->collapsible()
                ->schema([
                    Textarea::make('hesabfa_webhook_secret')
                        ->label('توکن وب‌هوک')
                        ->placeholder('اختیاری - برای اعتبارسنجی درخواست‌های وب‌هوک')
                        ->rows(2),
                ])
                ->columnSpanFull(),
        ];
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $taxSettings = [
            'tax_gold' => $data['tax_gold'] ?? 0,
            'tax_silver' => $data['tax_silver'] ?? 0,
        ];

        foreach ($taxSettings as $key => $value) {
            Setting::updateOrCreate(['key' => $key], [
                'value' => (string) $value,
                'type' => 'number',
                'category' => 'tax',
                'label' => $key === 'tax_gold' ? 'درصد مالیات طلا' : 'درصد مالیات نقره',
            ]);
        }

        Setting::updateOrCreate(['key' => 'show_price_with_tax'], [
            'value' => $data['show_price_with_tax'] ? 'true' : 'false',
            'type' => 'boolean',
            'category' => 'display',
            'label' => 'نمایش قیمت با مالیات',
        ]);

        $hesabfaData = collect($data)->filter(fn ($v, $k) => str_starts_with($k, 'hesabfa_'));
        $this->saveHesabfaSettings($hesabfaData->toArray());

        $this->saveTapsiSettings($data);

        Notification::make()
            ->title('تنظیمات با موفقیت ذخیره شد')
            ->success()
            ->send();
    }

    private function saveTapsiSettings(array $data): void
    {
        Setting::updateOrCreate(['key' => 'tapsi_emergency_status'], [
            'value' => $data['tapsi_emergency_status'] ?? 'open',
            'type' => 'string',
            'category' => 'tapsi',
            'label' => 'وضعیت اضطراری تپسی شاپ',
        ]);

        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        $envMap = [
            'tapsi_auth_token' => 'TAPSI_AUTH_TOKEN',
            'tapsi_auth_name' => 'TAPSI_AUTH_NAME',
        ];

        foreach ($envMap as $formKey => $envKey) {
            if (! array_key_exists($formKey, $data)) {
                continue;
            }
            $value = $data[$formKey] ?? '';
            $envContent = $this->setEnvValue($envContent, $envKey, (string) $value);
        }

        file_put_contents($envPath, $envContent);
    }

    private function saveHesabfaSettings(array $data): void
    {
        $envPath = base_path('.env');
        $envContent = file_get_contents($envPath);

        $envMap = [
            'hesabfa_api_key' => 'HESABFA_API_KEY',
            'hesabfa_login_token' => 'HESABFA_LOGIN_TOKEN',
            'hesabfa_default_project' => 'HESABFA_DEFAULT_PROJECT',
            'hesabfa_shipping_item_code' => 'HESABFA_SHIPPING_ITEM_CODE',
            'hesabfa_installment_fee_item_code' => 'HESABFA_INSTALLMENT_FEE_ITEM_CODE',
            'hesabfa_warehouse_code' => 'HESABFA_WAREHOUSE_CODE',
            'hesabfa_customer_node' => 'HESABFA_CUSTOMER_NODE',
            'hesabfa_customer_family' => 'HESABFA_CUSTOMER_FAMILY',
            'hesabfa_draft_invoice' => 'HESABFA_DRAFT_INVOICE',
            'hesabfa_use_current_date' => 'HESABFA_USE_CURRENT_DATE',
            'hesabfa_auto_sync' => 'HESABFA_AUTO_SYNC',
            'hesabfa_sync_stock' => 'HESABFA_SYNC_STOCK',
            'hesabfa_sync_interval' => 'HESABFA_SYNC_INTERVAL',
            'hesabfa_enable_warehouse_receipt' => 'HESABFA_ENABLE_WAREHOUSE_RECEIPT',
            'hesabfa_enable_reserved_stock' => 'HESABFA_ENABLE_RESERVED_STOCK',
            'hesabfa_webhook_secret' => 'HESABFA_WEBHOOK_SECRET',
        ];

        foreach ($envMap as $formKey => $envKey) {
            if (! array_key_exists($formKey, $data)) {
                continue;
            }
            $value = $data[$formKey] ?? '';
            if (is_bool($value)) {
                $value = $value ? 'true' : 'false';
            }
            $envContent = $this->setEnvValue($envContent, $envKey, (string) $value);
        }

        file_put_contents($envPath, $envContent);
    }

    private function setEnvValue(string $envContent, string $key, string $value): string
    {
        $lines = explode("\n", $envContent);
        $found = false;
        $escapedValue = $this->escapeEnvValue($value);

        foreach ($lines as &$line) {
            if (str_starts_with(trim($line), $key.'=')) {
                $line = $key.'='.$escapedValue;
                $found = true;
                break;
            }
        }

        if (! $found) {
            $lines[] = $key.'='.$escapedValue;
        }

        return implode("\n", $lines);
    }

    private function escapeEnvValue(string $value): string
    {
        if ($value === '' || str_contains($value, ' ') || str_contains($value, '#') || str_contains($value, "\n") || str_starts_with($value, '"') || str_contains($value, '"')) {
            $value = str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
            $value = str_replace("\n", '\\n', $value);

            return '"'.$value.'"';
        }

        return $value;
    }
}
