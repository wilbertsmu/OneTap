<?php

namespace App\Filament\Resources;

use App\Filament\Imports\DeactivateCardImporter;
use App\Filament\Imports\NfcCardImporter;
use App\Filament\Resources\NfcCardResource\Pages;
use App\Models\Employee;
use App\Models\NfcCard;
use App\Models\Student;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\ImportAction;

class NfcCardResource extends Resource
{
    protected static ?string $model = NfcCard::class;

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?string $modelLabel = 'NFC Card';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uid')
                    ->label('Card UID')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('student_id')
                    ->label('Assigned Student')
                    ->relationship('student', 'id_number')
                    ->getOptionLabelFromRecordUsing(fn (Student $record) => "{$record->id_number} — {$record->first_name} {$record->last_name}")
                    ->searchable(['id_number', 'first_name', 'last_name'])
                    ->preload()
                    ->live()
                    ->nullable()
                    ->helperText('A card can be assigned to a student OR an employee, not both.')
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                            if ($value && $get('employee_id')) {
                                $fail('A card cannot be assigned to both a student and an employee.');
                            }
                        },
                    ]),
                Forms\Components\Select::make('employee_id')
                    ->label('Assigned Employee')
                    ->relationship('employee', 'id_number')
                    ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->id_number} — {$record->first_name} {$record->last_name}")
                    ->searchable(['id_number', 'first_name', 'last_name'])
                    ->preload()
                    ->live()
                    ->nullable(),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'lost' => 'Lost',
                        'revoked' => 'Revoked',
                    ])
                    ->required()
                    ->default('active')
                    ->rules([
                        fn (Get $get, ?NfcCard $record): Closure => function (string $attribute, $value, Closure $fail) use ($get, $record) {
                            if ($value !== 'active') {
                                return;
                            }

                            $holder = $get('student_id')
                                ? Student::find($get('student_id'))
                                : ($get('employee_id') ? Employee::find($get('employee_id')) : null);

                            if (! $holder) {
                                return;
                            }

                            if (NfcCard::activeCountFor($holder, $record?->id) >= NfcCard::MAX_ACTIVE_CARDS_PER_HOLDER) {
                                $fail('This person already has ' . NfcCard::MAX_ACTIVE_CARDS_PER_HOLDER . ' active cards. Revoke or mark one lost before adding another.');
                            }
                        },
                    ]),
                Forms\Components\DateTimePicker::make('issued_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('uid')
                    ->label('Card UID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('holder_id_number')
                    ->label('Assigned ID')
                    ->getStateUsing(fn (NfcCard $record) => $record->holder()?->id_number)
                    ->placeholder('Unassigned'),
                Tables\Columns\TextColumn::make('holder_name')
                    ->label('Assigned Name')
                    ->getStateUsing(fn (NfcCard $record) => $record->holder()?->full_name)
                    ->placeholder('—'),
                Tables\Columns\BadgeColumn::make('holder_type')
                    ->label('Type')
                    ->getStateUsing(fn (NfcCard $record) => match (true) {
                        $record->student_id !== null => 'Student',
                        $record->employee_id !== null => 'Employee',
                        default => null,
                    })
                    ->placeholder('—')
                    ->colors([
                        'info' => 'Student',
                        'gray' => 'Employee',
                    ]),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'lost',
                        'danger' => 'revoked',
                    ]),
                Tables\Columns\TextColumn::make('issued_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('cardholder_type')
                    ->label('Cardholder Type')
                    ->options([
                        'student' => 'Student',
                        'employee' => 'Employee',
                        'unassigned' => 'Unassigned',
                    ])
                    ->query(function ($query, array $data) {
                        return match ($data['value'] ?? null) {
                            'student' => $query->whereNotNull('student_id'),
                            'employee' => $query->whereNotNull('employee_id'),
                            'unassigned' => $query->whereNull('student_id')->whereNull('employee_id'),
                            default => $query,
                        };
                    }),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Card Active')
                    ->placeholder('All cards')
                    ->trueLabel('Active')
                    ->falseLabel('Not active (lost or revoked)')
                    ->queries(
                        true: fn ($query) => $query->where('status', 'active'),
                        false: fn ($query) => $query->whereIn('status', ['lost', 'revoked']),
                    ),
                Tables\Filters\SelectFilter::make('status')
                    ->label('Status (detailed)')
                    ->options([
                        'active' => 'Active',
                        'lost' => 'Lost',
                        'revoked' => 'Revoked',
                    ]),
            ])
            ->headerActions([
                ImportAction::make()
                    ->importer(NfcCardImporter::class),
                ImportAction::make('deactivateCards')
                    ->label('Deactivate Cards')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->importer(DeactivateCardImporter::class),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
            'index' => Pages\ListNfcCards::route('/'),
            'create' => Pages\CreateNfcCard::route('/create'),
            'edit' => Pages\EditNfcCard::route('/{record}/edit'),
        ];
    }
}
