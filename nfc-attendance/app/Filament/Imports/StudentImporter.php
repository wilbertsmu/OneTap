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
                ->rules([
                    'required',
                    'max:255',
                    function (string $attribute, $value, \Closure $fail) {
                        if (Student::where('id_number', $value)->exists()) {
                            $fail("A student with ID Number \"{$value}\" already exists — this row was discarded.");
                        }
                    },
                ]),
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('last_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
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
        // Validation above already rejects rows whose id_number exists, so
        // every row that reaches here is guaranteed new.
        return new Student();
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
