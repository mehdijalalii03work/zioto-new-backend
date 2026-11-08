<?php

namespace App\Filament\Resources\Products\Tables;

use App\Services\Pricing\DynamicPriceService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Modules\Product\Models\Product;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                SpatieMediaLibraryImageColumn::make('product-images')
                    ->label('تصویر')
                    ->collection('product-images')
                    ->circular()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('name')
                    ->label('نام محصول')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'published' => 'منشتر شده',
                        'draft' => 'پیش‌نویس',
                        default => $state,
                    }),

                TextColumn::make('visibility')
                    ->label('نمایش')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'public' => 'info',
                        'private' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'public' => 'عمومی',
                        'private' => 'خصوصی',
                        default => $state,
                    }),

                IconColumn::make('contact_only')
                    ->label('فقط تماس')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('category.name')
                    ->label('دسته‌بندی')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('sku')
                    ->label('شناسه محصول')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('آدرس محصول')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('metal_type')
                    ->label('نوع')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->sortable(),

                TextColumn::make('form')
                    ->label('نوع کالا')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->sortable(),

                TextColumn::make('ayar')
                    ->label('عیار')
                    ->formatStateUsing(fn ($state) => $state?->label() ?? '-')
                    ->sortable(),

                TextColumn::make('price')
                    ->label('قیمت (تومان)')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(fn ($state): string => number_format((int) $state / 10)),

                IconColumn::make('dynamic_pricing')
                    ->label('قیمت‌گذاری پویای اجرت')
                    ->state(fn ($record): bool => app(DynamicPriceService::class)->isEligibleProduct($record))
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pack_size')
                    ->label('تعداد بسته')
                    ->numeric()
                    ->sortable()
                    ->badge()
                    ->color(fn ($state): string => $state > 1 ? 'info' : 'gray')
                    ->formatStateUsing(fn ($state): string => $state > 1 ? "{$state} عددی" : 'تکی'),

                TextColumn::make('stock_quantity')
                    ->label('موجودی')
                    ->numeric()
                    ->sortable(),

                TextColumn::make('hesabfa_physical_stock')
                    ->label('موجودی فیزیکی')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('success'),

                TextColumn::make('hesabfa_reserved_stock')
                    ->label('رزرو شده')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color('warning'),

                TextColumn::make('sellable_stock')
                    ->label('قابل فروش')
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->color(fn ($state): string => $state > 0 ? 'info' : 'danger'),

                TextColumn::make('created_at')
                    ->label('تاریخ ایجاد')
                    ->dateTime()
                    ->jalaliDateTime('H:i Y/m/d')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('وضعیت')
                    ->options([
                        'published' => 'منشتر شده',
                        'draft' => 'پیش‌نویس',
                    ]),

                SelectFilter::make('visibility')
                    ->label('نمایش')
                    ->options([
                        'public' => 'عمومی',
                        'private' => 'خصوصی',
                    ]),

                SelectFilter::make('contact_only')
                    ->label('فقط تماس')
                    ->options([
                        1 => 'بله',
                        0 => 'خیر',
                    ]),

                SelectFilter::make('hesabfa_exclude_from_sync')
                    ->label('غیرفعال کردن سینک')
                    ->options([
                        1 => 'بله',
                        0 => 'خیر',
                    ]),
            ])
            ->defaultPaginationPageOption(25)
            ->reorderable('sort_order')
            ->recordActions([
                ViewAction::make()
                    ->label('مشاهده در سایت')
                    ->url(fn ($record): string => config('app.frontend_url').'/product/'.$record->slug)
                    ->openUrlInNewTab(),

                EditAction::make()
                    ->label('ویرایش'),

                Action::make('copy')
                    ->label('کپی')
                    ->icon('heroicon-o-square-2-stack')
                    ->authorize(fn (): bool => auth()->user()?->can('create', Product::class) ?? false)
                    ->requiresConfirmation()
                    ->successNotificationTitle('کپی محصول ایجاد شد.')
                    ->action(function (Product $record): void {
                        $copy = $record->replicate();
                        $copy->name = $record->name.' (کپی)';
                        $copy->slug = Str::limit($record->slug, 200, '').'-copy-'.Str::uuid();
                        $copy->sku = null;
                        $copy->tapsi_product_id = null;
                        $copy->status = 'draft';
                        $copy->visibility = 'private';
                        $copy->stock_quantity = 0;
                        $copy->hesabfa_physical_stock = 0;
                        $copy->hesabfa_reserved_stock = 0;
                        $copy->hesabfa_manual_reserved = 0;
                        $copy->hesabfa_stock_locked = false;
                        $copy->hesabfa_stock_synced_at = null;
                        $copy->views_count = 0;
                        $copy->save();

                        foreach ($record->getMedia('product-images') as $media) {
                            $media->copy($copy, 'product-images', 'public');
                        }

                        foreach ($record->images as $image) {
                            if (! Storage::disk('public')->exists($image->image_path)) {
                                continue;
                            }

                            $path = 'product-images/'.Str::uuid().'.'.pathinfo($image->image_path, PATHINFO_EXTENSION);
                            Storage::disk('public')->copy($image->image_path, $path);

                            $copy->images()->create([
                                'image_path' => $path,
                                'alt' => $image->alt,
                                'is_primary' => $image->is_primary,
                                'sort_order' => $image->sort_order,
                            ]);
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('حذف انتخاب شده‌ها'),
                ]),
            ]);
    }
}
