<?php

namespace daacreators\CreatorsTicketing\Traits;

use Filament\Facades\Filament;

trait HasTicketPermissions
{
    protected function getUserPermissions()
    {
        $user = Filament::auth()->user();

        if (!$user) {
            return [
                'is_admin' => false,
                'permissions' => [],
            ];
        }

        $field = config('creators-ticketing.navigation_visibility.field', 'email');
        $allowed = config('creators-ticketing.navigation_visibility.allowed', []);
        $isAdmin = in_array($user->{$field} ?? null, $allowed, true);

        $permissions = [];

        if ($isAdmin) {
            $permissions[] = [
                'role' => 'admin',
                'can_create_tickets' => true,
                'can_view_all_tickets' => true,
                'can_assign_tickets' => true,
                'can_change_status' => true,
                'can_change_priority' => true,
                'can_delete_tickets' => true,
                'can_reply_to_tickets' => true,
                'can_add_internal_notes' => true,
                'can_view_internal_notes' => true,
            ];
        }

        return [
            'is_admin' => $isAdmin,
            'permissions' => $permissions,
        ];
    }

    protected function canUserViewAllTickets(): bool
    {
        $perms = $this->getUserPermissions();

        return $perms['is_admin'];
    }

    public static function canAccessNavigation(array $parameters = []): bool
    {
        $instance = new static();
        $perms = $instance->getUserPermissions();

        return $perms['is_admin'];
    }

    public static function shouldRegisterNavigation(array $parameters = []): bool
    {
        return static::canAccessNavigation();
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::canAccessNavigation();
    }

    public static function userCan(string $permissionKey): bool
    {
        $instance = new static();
        $data = $instance->getUserPermissions();

        if ($data['is_admin']) {
            return true;
        }

        foreach ($data['permissions'] as $deptPerms) {
            if (!empty($deptPerms[$permissionKey])) {
                return true;
            }
        }

        return false;
    }
}