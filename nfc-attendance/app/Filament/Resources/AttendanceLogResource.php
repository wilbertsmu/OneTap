<?php

namespace App\Filament\Resources;

use App\Filament\Exports\AttendanceLogExporter;
use App\Filament\Pages\MissingEntries;
use App\Filament\Resources\AttendanceLogResource\Pages;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Student;
use App\Services\AttendanceLogger;
use Closure;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\ExportAction;
use Illuminate\Database\Eloquent\Builder;

class AttendanceLogResource extends Resource
{
    protected static ?string $model = AttendanceLog::class;

    public static function canAccess(): bool
    {
        return auth()->user()?->canManage('manage_entry_logs') ?? false;
    }

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $modelLabel = 'Entry Log';

    protected static ?string $pluralModelLabel = 'Entry Logs';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('uid_scanned')
                    ->label('Scanned UID')
                    ->maxLength(255),
                Forms\Components\Select::make('student_id')
                    ->label('Student')
                    ->relationship('student', 'id_number')
                    ->getOptionLabelFromRecordUsing(fn (Student $record) => "{$record->id_number} — {$record->first_name} {$record->last_name}")
                    ->searchable(['id_number', 'first_name', 'last_name'])
                    ->preload()
                    ->live()
                    ->nullable()
                    ->rules([
                        fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get) {
                            if ($value && $get('employee_id')) {
                                $fail('An entry cannot be tied to both a student and an employee.');
                            }
                        },
                    ]),
                Forms\Components\Select::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'id_number')
                    ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->id_number} — {$record->first_name} {$record->last_name}")
                    ->searchable(['id_number', 'first_name', 'last_name'])
                    ->preload()
                    ->live()
                    ->nullable(),
                Forms\Components\TextInput::make('device_id')
                    ->label('Device / Reader')
                    ->maxLength(255),
                Forms\Components\DateTimePicker::make('scanned_at')
                    ->required()
                    ->default(now()),
                Forms\Components\Select::make('result')
                    ->options([
                        'success' => 'Success',
                        'unregistered_card' => 'Unregistered Card',
                        'inactive_card' => 'Inactive Card',
                        'inactive_student' => 'Inactive Student',
                        'inactive_employee' => 'Inactive Employee',
                    ])
                    ->required()
                    ->default('success'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('scanned_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('scanned_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('uid_scanned')
                    ->label('UID')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('holder_id_number')
                    ->label('ID Number')
                    ->getStateUsing(fn (AttendanceLog $record) => $record->holder()?->id_number)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('holder_name')
                    ->label('Name')
                    ->getStateUsing(fn (AttendanceLog $record) => $record->holder()?->full_name)
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('device_id')
                    ->label('Device')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\BadgeColumn::make('result')
                    ->colors([
                        'success' => 'success',
                        'danger' => ['unregistered_card', 'inactive_card', 'inactive_student', 'inactive_employee'],
                    ]),
                Tables\Columns\BadgeColumn::make('source')
                    ->label('Source')
                    ->colors([
                        'gray' => 'scan',
                        'warning' => 'manual',
                    ])
                    ->formatStateUsing(fn (string $state) => $state === 'manual' ? 'Manually Encoded' : 'NFC Scan'),
                Tables\Columns\TextColumn::make('encodedBy.name')
                    ->label('Encoded By')
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('result')
                    ->options([
                        'success' => 'Success',
                        'unregistered_card' => 'Unregistered Card',
                        'inactive_card' => 'Inactive Card',
                        'inactive_student' => 'Inactive Student',
                        'inactive_employee' => 'Inactive Employee',
                    ]),
                Tables\Filters\SelectFilter::make('student_id')
                    ->label('Student')
                    ->relationship('student', 'id_number')
                    ->getOptionLabelFromRecordUsing(fn (Student $record) => "{$record->id_number} — {$record->first_name} {$record->last_name}")
                    ->searchable(),
                Tables\Filters\SelectFilter::make('employee_id')
                    ->label('Employee')
                    ->relationship('employee', 'id_number')
                    ->getOptionLabelFromRecordUsing(fn (Employee $record) => "{$record->id_number} — {$record->first_name} {$record->last_name}")
                    ->searchable(),
                Tables\Filters\Filter::make('scanned_at')
                    ->form([
                        Forms\Components\DateTimePicker::make('from')
                            ->seconds(false),
                        Forms\Components\DateTimePicker::make('until')
                            ->seconds(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $q, $datetime) => $q->where('scanned_at', '>=', $datetime))
                            ->when($data['until'], fn (Builder $q, $datetime) => $q->where('scanned_at', '<=', $datetime));
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'From ' . \Illuminate\Support\Carbon::parse($data['from'])->format('M j, Y g:i A');
                        }

                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Until ' . \Illuminate\Support\Carbon::parse($data['until'])->format('M j, Y g:i A');
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('source')
                    ->options([
                        'scan' => 'NFC Scan',
                        'manual' => 'Manually Encoded',
                    ]),
                Tables\Filters\SelectFilter::make('department')
                    ->options(fn () => Student::query()->distinct()->pluck('department', 'department')
                        ->union(Employee::query()->distinct()->pluck('department', 'department'))
                        ->sort()
                        ->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        return $query->when(
                            $data['value'] ?? null,
                            fn (Builder $q, $department) => $q->where(function (Builder $q) use ($department) {
                                $q->whereHas('student', fn ($sq) => $sq->where('department', $department))
                                    ->orWhereHas('employee', fn ($eq) => $eq->where('department', $department));
                            })
                        );
                    }),
            ])
            ->headerActions([
                Tables\Actions\Action::make('missingEntries')
                    ->label('Missing Entries')
                    ->icon('heroicon-o-user-minus')
                    ->color('gray')
                    ->url(fn () => MissingEntries::getUrl()),
                ExportAction::make()
                    ->label('Export to Excel')
                    ->exporter(AttendanceLogExporter::class)
                    ->formats([ExportFormat::Xlsx]),
                Tables\Actions\Action::make('manualEncode')
                    ->label('Manually Encode Entry')
                    ->icon('heroicon-o-pencil-square')
                    ->form([
                        Forms\Components\TextInput::make('id_number')
                            ->label('Student/Employee ID Number')
                            ->required(),
                    ])
                    ->action(function (array $data, AttendanceLogger $logger) {
                        [$log, $error] = $logger->logManualEntry($data['id_number'], auth()->user());

                        if ($error) {
                            Notification::make()
                                ->title('Could not encode entry')
                                ->body($error)
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('Entry manually encoded')
                            ->body($log->holder()->full_name . ' — ' . ($log->result === 'success' ? 'logged successfully.' : 'logged, but flagged: ' . str_replace('_', ' ', $log->result) . '.'))
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            'index' => Pages\ListAttendanceLogs::route('/'),
            'create' => Pages\CreateAttendanceLog::route('/create'),
            'edit' => Pages\EditAttendanceLog::route('/{record}/edit'),
        ];
    }
}
