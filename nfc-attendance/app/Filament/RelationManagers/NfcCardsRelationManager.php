<?php

namespace App\Filament\RelationManagers;

use App\Models\Employee;
use App\Models\NfcCard;
use App\Models\Student;
use Closure;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Shared by both StudentResource and EmployeeResource — the owner record
 * (accessible via getOwnerRecord()) is whichever of the two is being
 * edited. Registers up to NfcCard::MAX_ACTIVE_CARDS_PER_HOLDER active
 * cards directly against that person.
 */
class NfcCardsRelationManager extends RelationManager
{
    protected static string $relationship = 'nfcCards';

    protected static ?string $title = 'NFC Cards';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uid')
                    ->label('Card UID')
                    ->required()
                    ->unique(table: 'nfc_cards', column: 'uid', ignoreRecord: true)
                    ->maxLength(255),
                Forms\Components\Select::make('status')
                    ->options([
                        'active' => 'Active',
                        'lost' => 'Lost',
                        'revoked' => 'Revoked',
                    ])
                    ->required()
                    ->default('active')
                    ->rules([
                        fn (): Closure => function (string $attribute, $value, Closure $fail) {
                            if ($value !== 'active') {
                                return;
                            }

                            /** @var Student|Employee $owner */
                            $owner = $this->getOwnerRecord();
                            $editingId = $this->getMountedTableActionRecord()?->id;

                            if (NfcCard::activeCountFor($owner, $editingId) >= NfcCard::MAX_ACTIVE_CARDS_PER_HOLDER) {
                                $fail('This person already has ' . NfcCard::MAX_ACTIVE_CARDS_PER_HOLDER . ' active cards. Revoke or mark one lost before adding another.');
                            }
                        },
                    ]),
                Forms\Components\DateTimePicker::make('issued_at')
                    ->default(now()),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('uid')
            ->columns([
                Tables\Columns\TextColumn::make('uid')
                    ->label('Card UID')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('status')
                    ->colors([
                        'success' => 'active',
                        'warning' => 'lost',
                        'danger' => 'revoked',
                    ]),
                Tables\Columns\TextColumn::make('issued_at')
                    ->dateTime(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Register Card')
                    ->disabled(fn () => NfcCard::activeCountFor($this->getOwnerRecord()) >= NfcCard::MAX_ACTIVE_CARDS_PER_HOLDER)
                    ->tooltip(fn () => NfcCard::activeCountFor($this->getOwnerRecord()) >= NfcCard::MAX_ACTIVE_CARDS_PER_HOLDER
                        ? 'Already at the ' . NfcCard::MAX_ACTIVE_CARDS_PER_HOLDER . '-card limit'
                        : null),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
