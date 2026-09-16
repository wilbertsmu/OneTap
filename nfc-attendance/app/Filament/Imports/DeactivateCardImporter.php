<?php

namespace App\Filament\Imports;

use App\Models\NfcCard;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;

/**
 * Bulk-deactivates existing cards by UID — unlike NfcCardImporter, this
 * never creates a new card. A UID that doesn't already exist fails the row
 * loudly (shows up in Failed Rows) rather than silently doing nothing.
 */
class DeactivateCardImporter extends Importer
{
    protected static ?string $model = NfcCard::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('uid')
                ->label('Card UID')
                ->requiredMapping()
                ->rules([
                    'required',
                    'max:255',
                    function (string $attribute, $value, \Closure $fail) {
                        if (! NfcCard::where('uid', $value)->exists()) {
                            $fail("No NFC card found with UID \"{$value}\" — it was not deactivated.");
                        }
                    },
                ]),
            ImportColumn::make('status')
                ->label('Reason (Lost/Revoked)')
                ->castStateUsing(fn (?string $state) => $state ? strtolower(trim($state)) : 'revoked')
                ->rules(['nullable', 'in:lost,revoked']),
        ];
    }

    public function resolveRecord(): ?NfcCard
    {
        return NfcCard::where('uid', $this->data['uid'])->first();
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your card deactivation has completed and ' . number_format($import->successful_rows) . ' ' . str('card')->plural($import->successful_rows) . ' marked inactive.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . number_format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed — see the failed rows for which UIDs did not match.';
        }

        return $body;
    }
}
