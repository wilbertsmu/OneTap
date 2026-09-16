<?php

namespace App\Filament\Imports;

use App\Models\Employee;
use App\Models\NfcCard;
use App\Models\Student;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class NfcCardImporter extends Importer
{
    protected static ?string $model = NfcCard::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('uid')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            // Kept as "student" for backward compatibility with the existing
            // template, but resolves against either a student or an employee
            // by ID number — whichever matches.
            ImportColumn::make('student')
                ->label('Student/Employee ID Number')
                ->rules([
                    'nullable',
                    function (string $attribute, $value, \Closure $fail) {
                        if (blank($value)) {
                            return;
                        }
                        $exists = Student::where('id_number', $value)->exists()
                            || Employee::where('id_number', $value)->exists();
                        if (! $exists) {
                            $fail("No student or employee found with ID number \"{$value}\".");
                        }
                    },
                ])
                ->fillRecordUsing(function (NfcCard $record, ?string $state) {
                    if (blank($state)) {
                        $record->student_id = null;
                        $record->employee_id = null;

                        return;
                    }

                    $student = Student::where('id_number', $state)->first();
                    if ($student) {
                        $record->student_id = $student->id;
                        $record->employee_id = null;

                        return;
                    }

                    $employee = Employee::where('id_number', $state)->first();
                    $record->employee_id = $employee?->id;
                    $record->student_id = null;
                }),
            ImportColumn::make('status')
                ->castStateUsing(fn (?string $state) => $state ? strtolower(trim($state)) : null)
                ->rules(['nullable', 'in:active,lost,revoked']),
        ];
    }

    public function resolveRecord(): ?NfcCard
    {
        return NfcCard::firstOrNew([
            'uid' => $this->data['uid'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your card import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
