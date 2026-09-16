<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Admin Users';

    // Only the Super Admin manages other admin users — not permission-based
    // like the other resources, since granting/revoking access itself is
    // reserved for whoever holds the top-level role.
    public static function canAccess(): bool
    {
        return auth()->user()?->is_super_admin ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->required(fn (string $context) => $context === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->dehydrateStateUsing(fn (string $state) => Hash::make($state))
                    ->helperText(fn (string $context) => $context === 'edit' ? 'Leave blank to keep the current password.' : null)
                    ->maxLength(255),
                Forms\Components\Toggle::make('is_super_admin')
                    ->label('Super Admin')
                    ->helperText('A super admin can manage everything, including other admin users, regardless of the permissions below.')
                    ->live(),
                Forms\Components\CheckboxList::make('permissions')
                    ->label('Can Manage')
                    ->relationship('permissions', 'name')
                    ->options(PermissionSeeder::PERMISSIONS)
                    ->columns(2)
                    ->hidden(fn (Forms\Get $get) => $get('is_super_admin'))
                    ->helperText('Pick which sections this user can access. "Manage Entry Logs" is typically given alongside at least one other section.'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_super_admin')
                    ->label('Super Admin')
                    ->boolean(),
                Tables\Columns\TextColumn::make('permissions.name')
                    ->label('Permissions')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => \Database\Seeders\PermissionSeeder::PERMISSIONS[$state] ?? $state)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_super_admin')
                    ->label('Super Admin'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn (User $record) => $record->id === auth()->id())
                    ->tooltip(fn (User $record) => $record->id === auth()->id() ? "You can't delete your own account" : null),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
