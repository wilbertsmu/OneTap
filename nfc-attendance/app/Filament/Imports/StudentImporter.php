<?php

namespace App\Filament\Imports;

use App\Models\Student;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class StudentImporter extends Importer
{
    protected static ?string $model = Student::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id_number')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            // Names are only set when creating a brand-new student — if the
            // id_number already matches an existing student, the row updates
            // that student's course/year_level/department/status instead,
            // without touching the name already on file.
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(function (Student $record, ?string $state) {
                    if (! $record->exists) {
                        $record->first_name = $state;
                    }
                }),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required', 'max:255'])
                ->fillRecordUsing(function (Student $record, ?string $state) {
                    if (! $record->exists) {
                        $record->last_name = $state;
                    }
                }),
            ImportColumn::make('course')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('year_level')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('department')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('status')
                ->castStateUsing(fn (?string $state) => $state ? strtolower(trim($state)) : null)
                ->rules(['nullable', 'in:active,inactive']),
        ];
    }

    public function resolveRecord(): ?Student
    {
        // If a student with this id_number already exists, update it
        // (course/year_level/department/status) instead of discarding the row.
        return Student::firstOrNew([
            'id_number' => $this->data['id_number'],
        ]);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your student import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
