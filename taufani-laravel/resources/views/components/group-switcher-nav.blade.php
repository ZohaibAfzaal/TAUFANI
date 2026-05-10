<?php

use Livewire\Volt\Component;
use App\Services\GroupContextService;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public ?int $activeGroupId = null;

    public function mount(GroupContextService $service): void
    {
        $this->activeGroupId = $service->getActiveGroupId();
    }

    public function with(GroupContextService $service): array
    {
        $user = Auth::user();
        $activeGroup = $service->getActiveGroup($user);
        $groups = $user->groups()->withCount('members')->orderBy('name')->get();

        return [
            'activeGroup' => $activeGroup,
            'groups' => $groups,
        ];
    }

    public function switchGroup(int $groupId, GroupContextService $service): void
    {
        if ($service->setActiveGroup($groupId)) {
            $this->redirect(
                request()->header('Referer', route('dashboard')),
                navigate: true
            );
        }
    }
};
?>

<div x-data="{ open: false }" class="relative w-full">
    @if($activeGroup)
        <button @click="open = !open"
                class="flex w-full items-center gap-2 rounded-2xl border border-slate-200 bg-white/80 px-3 py-2 text-left shadow-sm transition-all hover:border-indigo-300 hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
            <span class="text-base leading-none shrink-0">{{ $activeGroup->emoji }}</span>
            <span class="flex-1 min-w-0 truncate text-sm font-semibold text-slate-800">{{ $activeGroup->name }}</span>
            <x-icon name="chevron-down" class="w-3.5 h-3.5 shrink-0 text-slate-400 transition-transform duration-200" x-bind:class="open ? 'rotate-180' : ''" stroke-width="2.5" />
        </button>

        <div x-show="open" @click.outside="open = false"
             x-transition:enter="transition ease-out duration-100" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-75" x-transition:leave-start="opacity-100 translate-y-0" x-transition:leave-end="opacity-0 -translate-y-1"
             class="absolute left-0 top-full mt-2 z-50 w-72 origin-top rounded-2xl border border-slate-100 bg-white shadow-xl shadow-slate-200/60" style="display:none;">

            <div class="px-4 pt-3 pb-1.5">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Your Groups</p>
            </div>

            <div class="max-h-60 overflow-y-auto pb-2">
                @forelse($groups as $group)
                    <button wire:click="switchGroup({{ $group->id }})" @click="open = false"
                            class="flex w-full items-center gap-3 px-4 py-2.5 text-left transition-colors hover:bg-slate-50 {{ $activeGroup->id === $group->id ? 'bg-indigo-50' : '' }}">
                        <span class="text-xl leading-none shrink-0">{{ $group->emoji }}</span>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 truncate">{{ $group->name }}</p>
                            <p class="text-xs text-slate-400">{{ $group->members_count }} members</p>
                        </div>
                        @if($activeGroup->id === $group->id)
                            <x-icon name="check" class="w-4 h-4 text-indigo-600 shrink-0" stroke-width="2.5" />
                        @endif
                    </button>
                @empty
                    <p class="px-4 py-3 text-sm text-slate-400">No groups yet</p>
                @endforelse
            </div>

            <div class="border-t border-slate-100 p-3">
                <a href="{{ route('groups') }}" wire:navigate class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold text-indigo-600 hover:bg-indigo-50 transition-colors">
                    <x-icon name="settings" class="w-4 h-4" stroke-width="2" />
                    Manage Groups
                </a>
            </div>
        </div>
    @else
        <a href="{{ route('groups') }}" wire:navigate class="flex w-full items-center gap-2 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-3 py-2 text-sm font-semibold text-slate-500 hover:bg-slate-100 transition-colors">
            <x-icon name="users" class="w-4 h-4 shrink-0" stroke-width="2" />
            <span class="truncate">Create or join a group</span>
        </a>
    @endif
</div>
