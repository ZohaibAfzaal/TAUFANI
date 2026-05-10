<?php

use Livewire\Volt\Component;
use App\Models\Group;
use App\Models\User;
use App\Services\GroupContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $activeGroupId = null;
    public string $searchEmail = '';
    public string $memberSearch = '';
    public bool $showDeleteModal = false;
    public string $groupToDelete = '';

    public function mount(GroupContextService $service): void
    {
        $this->activeGroupId = $service->getActiveGroup()?->id;
    }

    public function with(GroupContextService $service): array
    {
        $user = Auth::user();
        $activeGroup = $service->getActiveGroup($user);

        if (!$activeGroup) {
            return ['activeGroup' => null, 'userGroups' => collect(), 'availableUsers' => collect()];
        }

        $userGroups = $user->groups()->withCount('members')->orderBy('name')->get();
        $memberIds = $activeGroup->members()->pluck('users.id');

        $availableUsersQuery = User::query()
            ->whereNotIn('id', $memberIds)
            ->orderBy('name');

        if ($this->memberSearch !== '') {
            $availableUsersQuery->where(function ($query) {
                $query->where('name', 'like', '%' . $this->memberSearch . '%')
                    ->orWhere('email', 'like', '%' . $this->memberSearch . '%');
            });
        }

        $availableUsers = $availableUsersQuery->limit(8)->get();

        return [
            'activeGroup' => $activeGroup,
            'userGroups' => $userGroups,
            'availableUsers' => $availableUsers,
        ];
    }

    public function switchGroup(int $groupId, GroupContextService $service): void
    {
        if ($service->setActiveGroup($groupId)) {
            $this->redirect(request()->header('Referer', route('settings')), navigate: true);
        }
    }

    public function addMember($groupId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->members()->where('users.id', Auth::id())->exists(), 403);

        $this->validate([
            'searchEmail' => 'required|email|exists:users,email',
        ], [
            'searchEmail.exists' => 'No user found with that email address.',
        ]);

        $userToAdd = User::where('email', $this->searchEmail)->first();

        if ($group->members()->where('users.id', $userToAdd->id)->exists()) {
            $this->addError('searchEmail', 'User is already a member.');
            return;
        }

        $group->members()->attach($userToAdd->id);
        $this->reset('searchEmail');
        $this->dispatch('member-added', groupId: $groupId);
    }

    public function addExistingUser($groupId, $userId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->members()->where('users.id', Auth::id())->exists(), 403);

        $userToAdd = User::findOrFail($userId);

        if ($group->members()->where('users.id', $userToAdd->id)->exists()) {
            $this->addError('memberSearch', 'User is already a member.');
            return;
        }

        $group->members()->attach($userToAdd->id);
        $this->reset('memberSearch');
        $this->dispatch('member-added', groupId: $groupId);
    }

    public function removeMember($groupId, $memberId)
    {
        $group = Group::findOrFail($groupId);
        abort_unless($group->members()->where('users.id', Auth::id())->exists(), 403);

        // Prevent removing if they have unpaid debts/expenses
        $group->members()->detach($memberId);
        $this->dispatch('member-removed', groupId: $groupId);
    }

    public function leaveGroup(int $id, GroupContextService $service): void
    {
        $group = Group::findOrFail($id);

        if ($group->created_by === Auth::id()) {
            $this->addError('leave', 'You created this group. Delete it instead of leaving.');
            return;
        }

        $group->members()->detach(Auth::id());

        if ($this->activeGroupId == $id) {
            $service->clearActiveGroup();
        }

        $this->dispatch('group-left', groupId: $id);
    }

    public function deleteGroup(int $id, GroupContextService $service): void
    {
        $group = Group::findOrFail($id);
        abort_unless($group->created_by === Auth::id(), 403);

        $group->delete();

        if ($this->activeGroupId == $id) {
            $service->clearActiveGroup();
        }

        $this->showDeleteModal = false;
        $this->dispatch('group-deleted', groupId: $id);
    }
};
?>

<div>
@if(!$activeGroup)
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-10 text-center">
        <p class="text-sm font-medium text-slate-400">No group selected</p>
    </div>
@else
    <div class="space-y-4">
        {{-- Header --}}
        <div class="px-1">
            <h1 class="text-2xl font-extrabold text-slate-900">Settings</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $activeGroup->emoji }} {{ $activeGroup->name }}</p>
        </div>

        {{-- Members card --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-900">Members</h3>
                <p class="text-xs text-slate-400 mt-0.5">{{ $activeGroup->members->count() }} people in this group</p>
            </div>

            <div class="divide-y divide-slate-50">
                @foreach($activeGroup->members as $member)
                    <div class="flex items-center gap-3 px-5 py-4">
                        <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode($member->name) }}"
                             alt="{{ $member->name }}"
                             class="h-10 w-10 shrink-0 rounded-2xl bg-indigo-50 object-cover shadow-md shadow-indigo-100">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $member->name }}</p>
                                @if($activeGroup->created_by === $member->id)
                                    <span class="shrink-0 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-bold text-amber-700">Creator</span>
                                @endif
                            </div>
                            <p class="text-xs text-slate-400 truncate">{{ $member->email }}</p>
                        </div>
                        @if($activeGroup->created_by === Auth::id() && $member->id !== Auth::id())
                            <button wire:click="removeMember({{ $activeGroup->id }}, {{ $member->id }})"
                                    onclick="return confirm('Remove {{ $member->name }}?')"
                                    class="shrink-0 rounded-xl border border-rose-100 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-600 hover:bg-rose-100 transition-colors">
                                Remove
                            </button>
                        @endif
                    </div>
                @endforeach
            </div>

            {{-- Add member (creator only) --}}
            @if($activeGroup->created_by === Auth::id())
                <div class="border-t border-slate-100 p-5 space-y-4 bg-slate-50/50">
                    <div class="space-y-2">
                        <x-input-label value="Search existing users" />
                        <x-text-input wire:model.live="memberSearch" type="text" placeholder="Search by name or email…" />

                        <div class="space-y-1.5 pt-1">
                            @forelse($availableUsers as $user)
                                <div class="flex items-center justify-between rounded-2xl border border-slate-100 bg-white px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-900 truncate">{{ $user->name }}</p>
                                        <p class="text-xs text-slate-400 truncate">{{ $user->email }}</p>
                                    </div>
                                    <button wire:click="addExistingUser({{ $activeGroup->id }}, {{ $user->id }})" type="button"
                                            class="ml-3 shrink-0 rounded-xl bg-indigo-600 px-3 py-2 text-xs font-bold text-white hover:bg-indigo-700 transition-colors">
                                        Add
                                    </button>
                                </div>
                            @empty
                                <p class="text-xs text-slate-400 text-center py-2">{{ $memberSearch ? 'No matching users found' : 'All registered users are already members' }}</p>
                            @endforelse
                        </div>
                        @error('memberSearch') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="space-y-2">
                        <x-input-label value="Add by email" />
                        <form wire:submit="addMember({{ $activeGroup->id }})" class="flex gap-2">
                            <x-text-input wire:model="searchEmail" type="email" placeholder="member@email.com" class="flex-1" />
                            <button type="submit"
                                    class="shrink-0 rounded-2xl bg-indigo-600 px-4 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all active:scale-[0.97]">
                                Add
                            </button>
                        </form>
                        @error('searchEmail') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            @endif
        </div>

        {{-- Other groups --}}
        @if($userGroups->count() > 1)
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Your Other Groups</h3>
                </div>
                <div class="divide-y divide-slate-50">
                    @foreach($userGroups as $group)
                        @if($group->id !== $activeGroup->id)
                            <div class="flex items-center justify-between px-5 py-4 hover:bg-slate-50/50 transition-colors">
                                <div>
                                    <p class="text-sm font-semibold text-slate-900">{{ $group->emoji }} {{ $group->name }}</p>
                                    <p class="text-xs text-slate-400 mt-0.5">{{ $group->members_count }} members</p>
                                </div>
                                <button wire:click="switchGroup({{ $group->id }})"
                                        class="rounded-xl bg-indigo-50 border border-indigo-100 px-3 py-1.5 text-xs font-bold text-indigo-600 hover:bg-indigo-100 transition-colors">
                                    Switch
                                </button>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Danger zone --}}
        <div class="rounded-2xl bg-white border border-rose-100 shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-rose-50">
                <h3 class="text-sm font-bold text-rose-600">Danger Zone</h3>
            </div>
            <div class="p-5">
                @if($activeGroup->created_by === Auth::id())
                    <button wire:click="$set('showDeleteModal', true)"
                            class="w-full rounded-2xl border-2 border-rose-200 bg-rose-50 py-3.5 text-sm font-bold text-rose-600 transition-all hover:bg-rose-500 hover:text-white hover:border-rose-500 active:scale-[0.98]">
                        Delete Group
                    </button>
                @else
                    <button wire:click="leaveGroup({{ $activeGroup->id }})"
                            onclick="return confirm('Leave this group? You can rejoin if invited.')"
                            class="w-full rounded-2xl border-2 border-amber-200 bg-amber-50 py-3.5 text-sm font-bold text-amber-600 transition-all hover:bg-amber-500 hover:text-white hover:border-amber-500 active:scale-[0.98]">
                        Leave Group
                    </button>
                @endif
            </div>
        </div>
    </div>

    {{-- Delete modal --}}
    @if($showDeleteModal)
        <div class="fixed inset-0 z-50 flex items-end justify-center p-4 bg-black/50 backdrop-blur-sm">
            <div class="w-full max-w-sm rounded-[2rem] bg-white p-6 shadow-2xl">
                <div class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-rose-100 mx-auto">
                    <x-icon name="trash" class="w-6 h-6 text-rose-600" stroke-width="2" />
                </div>
                <h3 class="text-base font-bold text-slate-900 text-center mb-1">Delete Group?</h3>
                <p class="text-sm text-slate-400 text-center mb-6">This cannot be undone. All expenses and settlements will be permanently deleted.</p>
                <div class="flex gap-3">
                    <button wire:click="deleteGroup({{ $activeGroup->id }})"
                            class="flex-1 rounded-2xl bg-rose-600 py-3.5 text-sm font-bold text-white hover:bg-rose-700 transition-all active:scale-[0.97]">
                        Delete
                    </button>
                    <button wire:click="$set('showDeleteModal', false)"
                            class="flex-1 rounded-2xl border border-slate-200 py-3.5 text-sm font-bold text-slate-700 hover:bg-slate-50 transition-all">
                        Cancel
                    </button>
                </div>
            </div>
        </div>
    @endif
@endif
</div>
