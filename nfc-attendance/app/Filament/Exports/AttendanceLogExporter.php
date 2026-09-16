<?php

namespace App\Filament\Exports;

use App\Models\AttendanceLog;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class AttendanceLogExporter extends Exporter
{
    protected static ?string $model = AttendanceLog::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('scanned_at')
                ->label('Date/Time'),
            ExportColumn::make('id_number')
                ->label('ID Number')
                ->getStateUsing(fn (AttendanceLog $record) => $record->holder()?->id_number),
            ExportColumn::make('name')
                ->label('Name')
                ->getStateUsing(fn (AttendanceLog $record) => $record->holder()?->full_name),
            ExportColumn::make('type')
                ->label('Type')
                ->getStateUsing(fn (AttendanceLog $record) => match (true) {
                    $record->student_id !== null => 'Student',
                    $record->employee_id !== null => 'Employee',
                    default => null,
                }),
            ExportColumn::make('department')
                ->label('Department')
                ->getStateUsing(fn (AttendanceLog $record) => $record->holder()?->department),
            ExportColumn::make('uid_scanned')
                ->label('Card UID'),
            ExportColumn::make('device_id')
                ->label('Device'),
            ExportColumn::make('result')
                ->label('Result'),
            ExportColumn::make('source')
                ->label('Source'),
            ExportColumn::make('encodedBy.name')
                ->label('Encoded By'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your attendance log export has completed and ' . number_format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }
}
