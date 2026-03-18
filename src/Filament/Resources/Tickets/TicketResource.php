<?php

namespace daacreators\CreatorsTicketing\Filament\Resources\Tickets;

use BackedEnum;
use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Schemas\Schema;
use Filament\Facades\Filament;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Schemas\Components;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\Select;
use Filament\Actions\DeleteBulkAction;
use Filament\Schemas\Components\Group;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Model;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Components\TextEntry;
use daacreators\CreatorsTicketing\Models\Ticket;
use daacreators\CreatorsTicketing\Enums\TicketPriority;
use Filament\Infolists\Components\Section as InfoSection;
use daacreators\CreatorsTicketing\Support\UserNameResolver;
use daacreators\CreatorsTicketing\Traits\HasTicketingNavGroup;
use daacreators\CreatorsTicketing\Traits\HasTicketPermissions;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\Pages;
use daacreators\CreatorsTicketing\Filament\Resources\Tickets\RelationManagers\InternalNotesRelationManager;

class TicketResource extends Resource
{
    use HasTicketPermissions, HasTicketingNavGroup;

    protected static ?string $model = Ticket::class;
    
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationLabel(): string
    {
        return __('creators-ticketing::resources.ticket.title');
    }

    public static function getModelLabel(): string
    {
        return __('creators-ticketing::resources.ticket.label');
    }

    public static function getPluralModelLabel(): string
    {
        return __('creators-ticketing::resources.ticket.plural_label');
    }

    public static function canViewAny(): bool
    {
        $permissions = (new static)->getUserPermissions();
        if ($permissions['is_admin']) {
            return true;
        }
        $user = Filament::auth()->user();
        if (!$user) {
            return false;
        }
        return !empty($permissions['departments']) || $user->tickets()->exists();
    }

    public static function canCreate(): bool
    {
        $permissions = (new static)->getUserPermissions();
        
        if ($permissions['is_admin']) {
            return true;
        }
        
        foreach ($permissions['permissions'] as $deptPerms) {
            if ($deptPerms['can_create_tickets']) {
                return true;
            }
        }
        
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        if (!$record instanceof Ticket) {
            return false;
        }

        $permissions = (new static)->getUserPermissions();
        if ($permissions['is_admin']) {
            return true;
        }

        $user = Filament::auth()->user();
        if (!$user) {
            return false;
        }

        if ($record->user_id === $user->getKey()) {
            return true;
        }

        foreach ($permissions['permissions'] as $deptPerms) {
            if ($deptPerms['can_assign_tickets'] || $deptPerms['can_change_status'] || $deptPerms['can_change_priority'] || $deptPerms['can_reply_to_tickets']) {
                return true;
            }
        }

        return false;
    }

    public static function canDelete(Model $record): bool
    {
        if (!$record instanceof Ticket) {
            return false;
        }

        $permissions = (new static)->getUserPermissions();
        if ($permissions['is_admin']) {
            return true;
        }

        foreach ($permissions['permissions'] as $deptPerms) {
            if ($deptPerms['can_delete_tickets']) {
                return true;
            }
        }

        return false;
    }

    public static function form(Schema $schema): Schema
    {
        $permissions = (new static)->getUserPermissions();
        $requesterModel = config('creators-ticketing.requester_model', \App\Models\User::class);
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);

        return $schema->schema([

            
            Group::make()->schema([
                Section::make(__('creators-ticketing::resources.ticket.properties'))
                    ->schema([
                        Select::make('user_id')
                            ->label(__('creators-ticketing::resources.ticket.requester'))
                            ->searchable()
                            ->required()
                            ->getSearchResultsUsing(function (string $search) use ($requesterModel) {
                                $nameColumn = config('creators-ticketing.requester_name_column', 'name');
                                return $requesterModel::where($nameColumn, 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email]);
                            })
                            ->getOptionLabelUsing(function ($value) use ($requesterModel): ?string {
                                $user = $requesterModel::find($value);
                                return $user ? UserNameResolver::resolve($user) . ' - ' . $user->email : null;
                            })
                            ->visible(fn (?Model $record) => 
                                $permissions['is_admin'] || 
                                ($record === null && collect($permissions['permissions'])->contains(fn($p) => $p['can_assign_tickets'] ?? false))
                            )
                            ->disabled(fn (?Model $record) => $record instanceof Ticket && !$permissions['is_admin']),
                        
                        Select::make('assignee_id')
                            ->label(__('creators-ticketing::resources.ticket.assignee'))
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) use ($userModel) {
                                $nameColumn = config('creators-ticketing.user_name_column', 'name');
                                return $userModel::where($nameColumn, 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email]);
                            })
                           ->getOptionLabelUsing(function ($value) use ($userModel): ?string {
                                $user = $userModel::find($value);
                                return $user ? UserNameResolver::resolve($user) . ' - ' . $user->email : null;
                            })
                            ->preload(false)
                            ->native(false)
                            ->visible(fn (?Model $record) => 
                                $permissions['is_admin'] || 
                                collect($permissions['permissions'])->contains(fn($p) => $p['can_assign_tickets'] ?? false)
                            )
                            ->disabled(fn (?Model $record) => 
                                $record instanceof Ticket && !$permissions['is_admin'] && 
                                !collect($permissions['permissions'])->contains(fn($p) => $p['can_assign_tickets'] ?? false)
                            ),
                        
 
                        
                        Select::make('ticket_status_id')
                            ->label(__('creators-ticketing::resources.ticket.status'))
                            ->relationship('status', 'name')
                            ->visible(fn (?Model $record) => 
                                $permissions['is_admin'] || 
                                collect($permissions['permissions'])->contains(fn($p) => $p['can_change_status'] ?? false)
                            )
                            ->disabled(fn (?Model $record) => 
                                $record instanceof Ticket && !$permissions['is_admin'] && 
                                !collect($permissions['permissions'])->contains(fn($p) => $p['can_change_status'] ?? false)
                            ),
                        
                        Select::make('priority')
                            ->label(__('creators-ticketing::resources.ticket.priority'))
                            ->options(TicketPriority::class)
                            ->enum(TicketPriority::class)
                            ->required()
                            ->default(TicketPriority::LOW)
                            ->visible(fn (?Model $record) => 
                                $permissions['is_admin'] || 
                                collect($permissions['permissions'])->contains(fn($p) => $p['can_change_priority'] ?? false)
                            )
                            ->disabled(fn (?Model $record) => 
                                $record instanceof Ticket && !$permissions['is_admin'] && 
                                !collect($permissions['permissions'])->contains(fn($p) => $p['can_change_priority'] ?? false)
                            ),
                    ]),
            ])->columnSpan(1),
            ])->columns(3);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                InfoSection::make(__('creators-ticketing::resources.ticket.information'))
                    ->schema([
                        TextEntry::make('ticket_uid')
                            ->label(__('creators-ticketing::resources.ticket.ticket_id')),
                        
                        TextEntry::make('title'),
                        
                        TextEntry::make('content')
                            ->html()
                            ->columnSpanFull(),
                        
                        TextEntry::make('requester')
                            ->label(__('creators-ticketing::resources.ticket.requester'))
                            ->formatStateUsing(fn ($record) => UserNameResolver::resolve($record->requester)),
                        
                        TextEntry::make('assignee')
                            ->label(__('creators-ticketing::resources.ticket.assignee'))
                            ->formatStateUsing(fn ($record) => $record->assignee ? UserNameResolver::resolve($record->assignee) : __('creators-ticketing::resources.ticket.unassigned'))
                            ->default(__('creators-ticketing::resources.ticket.unassigned')),
                        
                   
                        TextEntry::make('status.name')
                            ->label(__('creators-ticketing::resources.ticket.status'))
                            ->badge()
                            ->color(fn ($record) => $record->status?->color ?? 'gray'),
                        
                        TextEntry::make('priority')
                            ->badge(),
                        
                        TextEntry::make('created_at')
                            ->dateTime(),
                        
                        TextEntry::make('last_activity_at')
                            ->dateTime(),
                    ])
                    ->columns(2),
                
                InfoSection::make(__('creators-ticketing::resources.ticket.custom_fields'))
                    ->schema(function (Ticket $record) {
                        if (empty($record->custom_fields)) {
                            return [
                                TextEntry::make('no_custom_fields')
                                    ->label('')
                                    ->default(__('creators-ticketing::resources.ticket.no_custom_fields'))
                                    ->columnSpanFull(),
                            ];
                        }

                        $schema = [];

                        foreach ($record->custom_fields as $key => $value) {
                            if ($value === null) continue;

                            $label = str($key)->replace('_', ' ')->title()->toString();

                            if (is_bool($value)) {
                                $schema[] = TextEntry::make("custom_fields.{$key}")
                                    ->label($label)
                                    ->formatStateUsing(fn ($state) => $state ? __('creators-ticketing::resources.ticket.yes') : __('creators-ticketing::resources.ticket.no'));
                            } else {
                                $schema[] = TextEntry::make("custom_fields.{$key}")
                                    ->label($label)
                                    ->html();
                            }
                        }

                        return $schema ?: [
                            TextEntry::make('no_data')
                                ->label('')
                                ->default(__('creators-ticketing::resources.ticket.no_custom_data'))
                                ->columnSpanFull(),
                        ];
                    })
                    ->columns(2)
                    ->visible(fn (Ticket $record) => 
                        !empty($record->custom_fields)
                    ),
            ]);
    }

   public static function table(Table $table): Table
    {
        $userModel = config('creators-ticketing.user_model', \App\Models\User::class);
        $permissions = (new static)->getUserPermissions();
        $user = Filament::auth()->user();

        return $table
            ->modifyQueryUsing(function (Builder $query) use ($permissions, $user) {
                if (!$user) {
                    $query->whereRaw('1 = 0');
                    return;
                }

                if ($permissions['is_admin']) {
                    return;
                }

                $canViewAll = collect($permissions['permissions'])
                    ->contains(fn($perm) => $perm['can_view_all_tickets'] ?? false);

                if ($canViewAll) {
                    return;
                }

                $query->where(function (Builder $q) use ($user) {
                    $q->where('user_id', $user->getKey())
                      ->orWhere('assignee_id', $user->getKey());
                });
            })
           ->recordClasses(fn (Model $record) => match (true) {
                method_exists($record, 'isUnseen') && $record->isUnseen() 
                    => 'font-bold bg-primary-50/50 dark:bg-primary-900/20',
                default => null,
            })
            ->columns([
                BadgeColumn::make('unread_indicator')
                    ->label('')
                    ->color('info')
                    ->size('sm')
                    ->state(fn (Model $record) =>
                                        method_exists($record, 'isUnseen') && $record->isUnseen()
                                            ? __('creators-ticketing::resources.ticket.new')
                                            : null
                    )
                    ->extraAttributes(['class' => 'text-[11px]'])
                    ->tooltip(fn ($state) => $state ? __('creators-ticketing::resources.ticket.new_tool_tip') : null),

                TextColumn::make('ticket_uid')
                    ->label(__('creators-ticketing::resources.ticket.id'))
                    ->searchable()
                    ->sortable(),
                
                TextColumn::make('title')
                    ->label(__('creators-ticketing::resources.ticket.title_field'))
                    ->weight(fn (Model $record) => (method_exists($record, 'isUnseen') && $record->isUnseen()) ? 'bold' : 'medium')
                    ->searchable(query: function ($query, string $search) {
                        return $query->where(function ($q) use ($search) {
                            $q->where('ticket_uid', 'like', "%{$search}%")
                            ->orWhereRaw("JSON_EXTRACT(custom_fields, '$.*') LIKE ?", ["%{$search}%"]);
                        });
                    })
                    ->limit(40)
                    ->tooltip(fn (Ticket $record): string => $record->title),
                
                TextColumn::make('requester.name')
                    ->label(__('creators-ticketing::resources.ticket.requester'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($record) => UserNameResolver::resolve($record->requester)),
                
                TextColumn::make('assignee.name')
                    ->label(__('creators-ticketing::resources.ticket.assignee'))
                    ->searchable()
                    ->sortable()
                    ->default(__('creators-ticketing::resources.ticket.unassigned'))
                    ->formatStateUsing(fn ($record) => $record->assignee ? UserNameResolver::resolve($record->assignee) : __('creators-ticketing::resources.ticket.unassigned')),
                
                TextColumn::make('status.name')
                    ->label(__('creators-ticketing::resources.ticket.status'))
                    ->formatStateUsing(fn ($record) => 
                        $record->status?->name ? 
                            "<span style='
                                display: inline-flex;
                                align-items: center;
                                background-color: {$record->status->color}10;
                                color: {$record->status->color};
                                padding: 0.3rem 0.8rem;
                                border-radius: 9999px;
                                font-size: 0.7rem;
                                font-weight: 600;
                                line-height: 1;
                                border: 1.5px solid {$record->status->color};
                                white-space: nowrap;
                            '>{$record->status->name}</span>" 
                        : ''
                    )
                    ->html(),
                
                TextColumn::make('priority')
                    ->label(__('creators-ticketing::resources.ticket.priority'))
                    ->badge(),
                
                TextColumn::make('last_activity_at')
                    ->label(__('creators-ticketing::resources.ticket.last_activity'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('creators-ticketing::resources.ticket.status'))
                    ->relationship('status', 'name')
                    ->preload(),
                
                SelectFilter::make('priority')
                    ->label(__('creators-ticketing::resources.ticket.priority'))
                    ->options(TicketPriority::class)
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('assign')
                    ->label(__('creators-ticketing::resources.ticket.actions.assign'))
                    ->icon('heroicon-o-user-plus')
                    ->visible(fn (Model $record) => 
                        $record instanceof Ticket && (
                        $permissions['is_admin'] || 
                        collect($permissions['permissions'])->contains(fn($p) => $p['can_assign_tickets'] ?? false))
                    )
                    ->form([
                        Select::make('assignee_id')
                            ->label(__('creators-ticketing::resources.ticket.actions.select_assignee'))
                            ->searchable()
                            ->getSearchResultsUsing(function (string $search) use ($userModel) {
                                $nameColumn = config('creators-ticketing.user_name_column', 'name');
                                return $userModel::where($nameColumn, 'like', "%{$search}%")
                                    ->orWhere('email', 'like', "%{$search}%")
                                    ->limit(50)
                                    ->get()
                                    ->mapWithKeys(fn($user) => [$user->getKey() => UserNameResolver::resolve($user) . ' - ' . $user->email]);
                            })
                            ->getOptionLabelUsing(function ($value) use ($userModel): ?string {
                                $user = $userModel::find($value);
                                return $user ? UserNameResolver::resolve($user) . ' - ' . $user->email : null;
                            })
                            ->default(fn (Model $record) => $record instanceof Ticket ? $record->assignee_id : null)
                            ->preload(false)
                            ->native(false),
                    ])
                    ->action(function (Model $record, array $data) use ($userModel) {
                        if (!$record instanceof Ticket) return;
                        $record->update(['assignee_id' => $data['assignee_id']]);

                        $assignee = $userModel::find($data['assignee_id']);
                        $record->activities()->create([
                            'user_id' => Filament::auth()->user()->getKey(),
                            'description' => 'Ticket assigned',
                            'new_value' => $assignee ? UserNameResolver::resolve($assignee) : null,
                        ]);
                        
                        Notification::make()
                            ->title(__('creators-ticketing::resources.ticket.notifications.assigned'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => 
                           $permissions['is_admin'] || 
                           collect($permissions['permissions'])->pluck('can_delete_tickets')->contains(true)
                        ),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            InternalNotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}