<?php

namespace App\Filament\Imports;

use App\Models\Employee;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

class EmployeeImporter extends Importer
{
    protected static ?string $model = Employee::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('id_number')
                ->requiredMapping()
                ->rules([
                    'required',
                    'max:255',
                    function (string $attribute, $value, \Closure $fail) {
                        if (Employee::where('id_number', $value)->exists()) {
                            $fail("An employee with ID Number \"{$value}\" already exists — this row was discarded.");
                        }
                    },
                ]),
            ImportColumn::make('first_name')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('last_name')
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

    public function resolveRecord(): ?Employee
    {
        // Validation above already rejects rows whose id_number exists, so
        // every row that reaches here is guaranteed new.
        return new Employee();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your employee import has completed and ' . number_format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }
}
