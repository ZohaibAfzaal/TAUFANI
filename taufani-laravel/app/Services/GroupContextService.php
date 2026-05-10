<?php

namespace App\Services;

use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Session;

class GroupContextService
{
    const SESSION_KEY = 'active_group_id';

    /**
     * Set the active group for the current user session.
     */
    public function setActiveGroup(int $groupId, ?User $user = null): ?Group
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return null;
        }

        // Verify user is member of this group
        $group = Group::find($groupId);
        if (!$group || !$group->members()->where('users.id', $user->id)->exists()) {
            return null;
        }

        Session::put(self::SESSION_KEY, $groupId);
        return $group;
    }

    /**
     * Get the currently active group, or first available group for user.
     */
    public function getActiveGroup(?User $user = null): ?Group
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return null;
        }

        $groupId = Session::get(self::SESSION_KEY);

        if ($groupId) {
            $group = Group::find($groupId);
            // Verify user is still a member
            if ($group && $group->members()->where('users.id', $user->id)->exists()) {
                return $group;
            }
            // Invalid or removed group, clear session
            Session::forget(self::SESSION_KEY);
        }

        // Return first group user is member of
        return $user->groups()->first();
    }

    /**
     * Get active group ID or null.
     */
    public function getActiveGroupId(?User $user = null): ?int
    {
        $group = $this->getActiveGroup($user);
        return $group?->id;
    }

    /**
     * Clear active group context.
     */
    public function clearActiveGroup(): void
    {
        Session::forget(self::SESSION_KEY);
    }

    /**
     * Check if user is member of group.
     */
    public function isMemberOfGroup(int $groupId, ?User $user = null): bool
    {
        $user = $user ?? auth()->user();
        if (!$user) {
            return false;
        }

        return Group::find($groupId)?->members()->where('users.id', $user->id)->exists() ?? false;
    }
}
