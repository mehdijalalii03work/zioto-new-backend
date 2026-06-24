<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BrandResource\Pages;
use App\Models\Brand;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BrandResource extends Resource
{
    protected static ?string $model = Brand::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'برندها';

    protected static string|\UnitEnum|null $navigationGroup = 'فروشگاه';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return 'برند';
    }

    public static function getPluralModelLabel(): string
    {
        return 'برندها';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('اطلاعات برند')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('name')
                                    ->label('نام برند')
                                    ->required()
                                    ->live(onBlur: true),

                                TextInput::make('slug')
                                    ->label('آدرس محصول')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->helperText('به صورت خودکار از نام تولید می‌شود'),

                                TextInput::make('description')
                                    ->label('توضیحات')
                                    ->placeholder('توضیحات برند (اختیاری)'),

                                Toggle::make('is_active')
                                    ->label('فعال')
                                    ->default(true),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('نام')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('شناسه')
                    ->searchable(),

                TextColumn::make('products_count')
                    ->label('تعداد محصولات')
                    ->counts('products')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('فعال')
                    ->boolean(),

                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->date('Y/m/d')
                    ->sortable(),
            ])
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make()
                    ->label('ویرایش'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف'),
                    ForceDeleteBulkAction::make()
                        ->label('حذف دائم'),
                    RestoreBulkAction::make()
                        ->label('بازیابی'),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBrands::route('/'),
            'create' => Pages\CreateBrand::route('/create'),
            'edit' => Pages\EditBrand::route('/{record}/edit'),
        ];
    }
}
