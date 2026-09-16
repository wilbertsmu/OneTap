<?php

namespace App\Filament\Resources\NfcCardResource\Pages;

use App\Filament\Resources\NfcCardResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditNfcCard extends EditRecord
{
    protected static string $resource = NfcCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
