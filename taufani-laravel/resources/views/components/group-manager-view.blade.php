<?php

use Livewire\Volt\Component;
use App\Models\Group;
use App\Services\GroupContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public string $name  = '';
    public string $emoji = '👥';
    public bool $showForm = false;

    public function with(): array
    {
        $user   = Auth::user();
        $groups = $user->groups()->withCount('members')->latest()->get();

        return ['groups' => $groups];
    }

    public function createGroup(GroupContextService $service): void
    {
        $this->validate([
            'name'  => 'required|min:2|max:60',
            'emoji' => 'required',
        ]);

        $group = Group::create([
            'name'       => trim($this->name),
            'emoji'      => $this->emoji,
            'created_by' => Auth::id(),
        ]);

        $group->members()->attach(Auth::id());

        $service->setActiveGroup($group->id);

        $this->redirectRoute('dashboard', navigate: true);
    }

    public function openGroup(int $groupId, GroupContextService $service): void
    {
        $service->setActiveGroup($groupId);
        $this->redirectRoute('dashboard', navigate: true);
    }
};
?>

<div class="space-y-4">

    {{-- Header --}}
    <div class="px-1">
        <h1 class="text-2xl font-extrabold text-slate-900">Groups</h1>
        <p class="text-xs text-slate-400 mt-0.5">Switch between groups or create a new one</p>
    </div>

    {{-- Group list --}}
    @forelse($groups as $group)
        <div class="flex items-center gap-4 rounded-2xl bg-white border border-slate-100 shadow-sm px-5 py-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 border border-indigo-100 text-2xl leading-none select-none">
                {{ $group->emoji }}
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-slate-900 truncate">{{ $group->name }}</p>
                <p class="text-xs text-slate-400 mt-0.5">{{ $group->members_count }} {{ Str::plural('member', $group->members_count) }}</p>
            </div>
            <button wire:click="openGroup({{ $group->id }})"
                    class="shrink-0 rounded-2xl bg-indigo-600 px-4 py-2.5 text-xs font-bold text-white shadow-md shadow-indigo-200 hover:bg-indigo-700 transition-all active:scale-[0.97]">
                Open →
            </button>
        </div>
    @empty
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-10 text-center">
            <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-50">
                <x-icon name="users" class="w-6 h-6 text-indigo-400" stroke-width="2" />
            </div>
            <h3 class="text-base font-bold text-slate-900">No groups yet</h3>
            <p class="mt-1 text-sm text-slate-400">Create your first group to start splitting expenses</p>
        </div>
    @endforelse

    {{-- Create group --}}
    <div x-data="{ open: false }" class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">

        <button @click="open = !open"
                class="w-full flex items-center justify-between px-5 py-4 hover:bg-slate-50/50 transition-colors">
            <div class="flex items-center gap-3">
                <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-600 text-white shadow-md shadow-indigo-200">
                    <x-icon name="plus" class="w-4 h-4" stroke-width="3" />
                </div>
                <span class="text-sm font-bold text-slate-900">Create New Group</span>
            </div>
            <x-icon name="chevron-down"
                    class="w-4 h-4 text-slate-400 transition-transform duration-200"
                    x-bind:class="open ? 'rotate-180' : ''"
                    stroke-width="2.5" />
        </button>

        <div x-show="open"
             x-transition:enter="transition ease-out duration-150"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="border-t border-slate-100 p-5 space-y-4"
             style="display:none">

            {{-- Emoji picker --}}
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">PICK AN EMOJI</p>
                <div class="flex flex-wrap gap-2">
                    @foreach(['👥','🏠','🏢','🏔️','🎉','🍕','🚗','✈️','🎓','💼','🏋️','🎮','🌴','🎯','💰','🏥','⚽','🎵','🍳','🛒'] as $e)
                        <button type="button"
                                wire:click="$set('emoji', '{{ $e }}')"
                                class="flex h-10 w-10 items-center justify-center rounded-xl text-xl transition-all
                                       {{ $emoji === $e
                                            ? 'bg-indigo-100 ring-2 ring-indigo-500 ring-offset-1 scale-110'
                                            : 'bg-slate-100 hover:bg-slate-200' }}">
                            {{ $e }}
                        </button>
                    @endforeach
                </div>
            </div>

            {{-- Name --}}
            <div>
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">GROUP NAME</p>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 border border-indigo-100 text-2xl leading-none">
                        {{ $emoji }}
                    </div>
                    <x-text-input wire:model="name"
                                  type="text"
                                  placeholder="e.g. House Mates, Murree Trip…" />
                </div>
                @error('name') <p class="text-xs text-rose-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <button wire:click="createGroup"
                    class="w-full rounded-2xl bg-indigo-600 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all active:scale-[0.98]">
                Create Group
            </button>
        </div>
    </div>

</div>
