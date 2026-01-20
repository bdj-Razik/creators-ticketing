<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\SpamFilters\Pages;

use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use daacreators\CreatorsTicketing\Filament\Resources\SpamFilters\SpamFilterResource;

class EditSpamFilter extends EditRecord
{
    protected static string $resource = SpamFilterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
