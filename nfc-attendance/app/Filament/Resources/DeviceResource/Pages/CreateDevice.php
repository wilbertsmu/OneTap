<?php

namespace App\Filament\Resources\DeviceResource\Pages;

use App\Filament\Resources\DeviceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateDevice extends CreateRecord
{
    protected static string $resource = DeviceResource::class;

    protected function afterCreate(): void
    {
        $token = $this->record->createToken($this->record->device_key)->plainTextToken;

        Notification::make()
            ->title('Device token generated')
            ->body("Copy this now and enter it into the Android app's Settings — it will not be shown again:\n\n{$token}")
            ->success()
            ->persistent()
            ->send();
    }
}
