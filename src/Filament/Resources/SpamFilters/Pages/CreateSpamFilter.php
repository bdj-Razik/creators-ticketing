<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\SpamFilters\Pages;

use Filament\Resources\Pages\CreateRecord;
use daacreators\CreatorsTicketing\Filament\Resources\SpamFilters\SpamFilterResource;

class CreateSpamFilter extends CreateRecord
{
    protected static string $resource = SpamFilterResource::class;
}
