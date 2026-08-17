<?php

namespace App\Filament\Resources\Roles\Schemas;

use App\Enums\Permission as PermissionEnum;
use App\Enums\Role as RoleEnum;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Spatie\Permission\Models\Permission as PermissionModel;
use Spatie\Permission\Models\Role as RoleModel;

class RoleForm
{
    public static function sections(): array
    {
        return [
            Section::make('اطلاعات نقش')
                ->description('نام نقش و دسترسی‌ها')
                ->icon('heroicon-o-shield-check')
                ->collapsible()
                ->schema([
                    Grid::make(1)
                        ->schema([
                            TextInput::make('name')
                                ->label('نام نقش')
                                ->required()
                                ->unique(ignoreRecord: true)
                                ->maxLength(50)
                                ->disabled(fn (string $operation, ?RoleModel $record): bool => static::isProtectedRole($operation, $record)),

                            CheckboxList::make('permissions')
                                ->relationship('permissions', 'name')
                                ->label('دسترسی‌ها')
                                ->options(static::permissionOptions())
                                ->columns(3)
                                ->searchable()
                                ->bulkToggleable()
                                ->disabled(fn (string $operation, ?RoleModel $record): bool => static::isProtectedRole($operation, $record)),
                        ]),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components(static::sections());
    }

    private static function isProtectedRole(string $operation, ?RoleModel $record): bool
    {
        return $operation === 'edit' && $record?->name === RoleEnum::Admin->value;
    }

    /**
     * @return array<int, string> Keyed by permission ID with a grouped Persian label.
     */
    private static function permissionOptions(): array
    {
        return PermissionModel::query()
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (PermissionModel $permission): array => [
                $permission->id => static::permissionLabel($permission),
            ])
            ->sortBy(fn (string $label): string => $label)
            ->all();
    }

    private static function permissionLabel(PermissionModel $permission): string
    {
        $enum = PermissionEnum::tryFrom($permission->name);

        if ($enum === null) {
            return $permission->name;
        }

        return $enum->group().' — '.$enum->label();
    }
}
