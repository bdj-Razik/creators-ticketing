<?php

namespace daacreators\CreatorsTicketing;

use Filament\Panel;
use Filament\Contracts\Plugin;
use daacreators\CreatorsTicketing\Filament\Resources\Forms\FormResource;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\TicketResource;
use daacreators\CreatorsTicketing\Filament\Resources\Departments\DepartmentResource;
use daacreators\CreatorsTicketing\Filament\Resources\TicketStatuses\TicketStatusResource;
use daacreators\CreatorsTicketing\Filament\Resources\AutomationRules\AutomationRuleResource;
use daacreators\CreatorsTicketing\Filament\Resources\SpamFilters\SpamFilterResource;
use daacreators\CreatorsTicketing\Filament\Resources\SpamLogs\SpamLogResource;

class TicketingPlugin implements Plugin
{
    public function getId(): string
    {
        return 'creators-ticketing';
    }

    public function register(Panel $panel): void
    {
        $panel->resources([
            FormResource::class,
            DepartmentResource::class,
            TicketResource::class,
            TicketStatusResource::class,
            AutomationRuleResource::class,
            SpamFilterResource::class,
            SpamLogResource::class,
        ]);
    }

    public function boot(Panel $panel): void
    {
        //
    }

    public static function make(): static
    {
        return new static();
    }
}
