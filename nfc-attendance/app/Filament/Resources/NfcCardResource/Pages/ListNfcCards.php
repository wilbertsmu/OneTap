<?php

namespace App\Filament\Resources\NfcCardResource\Pages;

use App\Filament\Resources\NfcCardResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListNfcCards extends ListRecords
{
    protected static string $resource = NfcCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
