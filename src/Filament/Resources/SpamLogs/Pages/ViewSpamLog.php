<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\SpamLogs\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\ViewRecord;
use daacreators\CreatorsTicketing\Filament\Resources\SpamLogs\SpamLogResource;

class ViewSpamLog extends ViewRecord
{
    protected static string $resource = SpamLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
