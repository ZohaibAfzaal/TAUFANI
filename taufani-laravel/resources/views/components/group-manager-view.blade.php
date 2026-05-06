<?php

use Livewire\Volt\Component;
use App\Models\Group;
use App\Models\User;
use App\Models\Expense;
use App\Services\BalanceCalculator;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $showCreate = false;
    public $name = '';
    public $emoji = 'users'; // stores an icon name, kept as 'emoji' for DB compat

    public $searchEmail = '';
    public $activeGroupId = null;

    public function with()
    {
        $user   = Auth::user();
        $groups = $user->groups()->with('members')->withCount(['members', 'expenses'])->latest()->get();

        $activeGroup    = null;
        $activeExpenses = collect();
        $activeBalances = [];

        if ($this->activeGroupId) {
            $activeGroup = Group::with('members')->find($this->activeGroupId);
            if ($activeGroup) {
                $activeExpenses = Expense::where('group_id', $this->activeGroupId)
                    ->with(['payer', 'participants'])
                    ->latest()
                    ->get();

                $calculator     = new BalanceCalculator();
                $rawBalances    = $calculator->calculate(collect([$this->activeGroupId]));
                $activeBalances = $calculator->withUsers($rawBalances);
            }
        }

        return [
            'groups'         => $groups,
            'activeGroup'    => $activeGroup,
            'activeExpenses' => $activeExpenses,
            'activeBalances' => $activeBalances,
        ];
    }

    public function createGroup()
    {
        $this->validate([
            'name' => 'required|min:3',
        ]);

        $group = Group::create([
            'name'       => $this->name,
            'emoji'      => $this->emoji,
            'created_by' => Auth::id(),
        ]);

        $group->members()->attach(Auth::id());
        $this->reset(['name', 'emoji', 'showCreate']);
        $this->emoji = 'users';
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
    }

    public function leaveGroup($id)
    {
        $group = Group::findOrFail($id);

        if ($group->created_by === Auth::id()) {
            $this->addError('leave', 'You created this group. Delete it instead of leaving.');
            return;
        }

        $group->members()->detach(Auth::id());

        if ($this->activeGroupId == $id) {
            $this->activeGroupId = null;
        }
    }

    public function deleteGroup($id)
    {
        $group = Group::findOrFail($id);
        abort_unless($group->created_by === Auth::id(), 403);

        $group->delete();

        if ($this->activeGroupId == $id) {
            $this->activeGroupId = null;
        }
    }

    public function deleteExpense($id)
    {
        $expense = Expense::with('group')->findOrFail($id);
        abort_unless(
            $expense->paid_by === Auth::id() || $expense->group->created_by === Auth::id(),
            403
        );
        $expense->delete();
    }
};
?>

@php
$iconOptions = [
    'users'     => 'People',
    'home'      => 'Home',
    'plane'     => 'Travel',
    'coffee'    => 'Food',
    'music'     => 'Music',
    'film'      => 'Movies',
    'trophy'    => 'Sports',
    'briefcase' => 'Work',
    'heart'     => 'Personal',
    'map-pin'   => 'Events',
];
@endphp

<div class="space-y-6">
    @if(!$activeGroupId)
        <div class="flex items-center justify-between px-1">
            <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400">Your Groups</h3>
            <button
                wire:click="$toggle('showCreate')"
                class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-100 transition-all hover:scale-110 active:scale-95"
            >
                <x-icon name="plus" class="w-5 h-5" stroke-width="3" />
            </button>
        </div>

        @if($showCreate)
            <div class="rounded-[2.5rem] bg-white p-6 shadow-sm border border-slate-100 animate-in fade-in slide-in-from-top-4 duration-300">
                <div class="mb-6 flex items-center justify-between">
                    <h4 class="text-lg font-extrabold text-slate-900">New Group</h4>
                    <button wire:click="$set('showCreate', false)" class="h-8 w-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:text-slate-600">
                        <x-icon name="x" class="w-[18px] h-[18px]" stroke-width="2.5" />
                    </button>
                </div>

                <form wire:submit.prevent="createGroup" class="space-y-6">
                    <!-- Group Name -->
                    <div>
                        <label class="px-1 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 mb-2 block">Group Name</label>
                        <input
                            type="text"
                            wire:model="name"
                            placeholder="e.g. Goa Trip"
                            class="w-full rounded-2xl border-slate-100 bg-slate-50 px-5 py-4 text-sm font-semibold focus:border-indigo-500 focus:ring-0 outline-none transition-all"
                        >
                        @error('name') <p class="mt-2 text-xs font-semibold text-rose-500 px-1">{{ $message }}</p> @enderror
                    </div>

                    <!-- Icon picker -->
                    <div>
                        <label class="px-1 text-[10px] font-bold uppercase tracking-[0.2em] text-slate-400 mb-3 block">Group Icon</label>
                        <div class="grid grid-cols-5 gap-2">
                            @foreach($iconOptions as $iconName => $label)
                                <button
                                    type="button"
                                    wire:click="$set('emoji', '{{ $iconName }}')"
                                    title="{{ $label }}"
                                    class="flex flex-col items-center gap-1.5 rounded-2xl border-2 py-3 px-1 transition-all
                                        {{ $emoji === $iconName
                                            ? 'border-indigo-600 bg-indigo-50 text-indigo-600 shadow-sm shadow-indigo-100'
                                            : 'border-transparent bg-slate-50 text-slate-400 hover:bg-slate-100 hover:text-slate-600' }}"
                                >
                                    <x-icon :name="$iconName" class="w-5 h-5" stroke-width="2" />
                                    <span class="text-[8px] font-bold uppercase tracking-wide leading-none">{{ $label }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <button
                        type="submit"
                        class="w-full rounded-2xl bg-indigo-600 py-4 text-sm font-bold text-white shadow-xl shadow-indigo-100 transition-all hover:bg-indigo-700 active:scale-[0.98]"
                    >
                        Create Group
                    </button>
                </form>
            </div>
        @endif

        <!-- Groups list -->
        <div class="grid gap-4">
            @forelse($groups as $group)
                <div
                    wire:click="$set('activeGroupId', {{ $group->id }})"
                    class="group relative cursor-pointer overflow-hidden rounded-[2.5rem] bg-white p-6 shadow-sm border border-slate-100 transition-all hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-50 hover:-translate-y-1"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-4">
                            <div class="flex h-14 w-14 items-center justify-center rounded-3xl bg-indigo-50 text-indigo-600 group-hover:bg-indigo-100 transition-colors">
                                <x-icon :name="$group->emoji" class="w-6 h-6" stroke-width="2" />
                            </div>
                            <div>
                                <h4 class="text-base font-extrabold text-slate-900 group-hover:text-indigo-600 transition-colors">{{ $group->name }}</h4>
                                <p class="text-xs font-medium text-slate-400 mt-0.5">{{ $group->members_count }} members · {{ $group->expenses_count }} expenses</p>
                            </div>
                        </div>
                        <div class="flex h-9 w-9 items-center justify-center rounded-full text-slate-300 group-hover:text-indigo-600 transition-colors">
                            <x-icon name="chevron-right" class="w-5 h-5" stroke-width="2.5" />
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-[2.5rem] border-2 border-dashed border-slate-100 p-16 text-center">
                    <div class="mb-5 flex h-16 w-16 items-center justify-center rounded-full bg-slate-50 text-slate-300">
                        <x-icon name="users" class="w-8 h-8" stroke-width="1.5" />
                    </div>
                    <h4 class="text-sm font-bold text-slate-900">No Groups Yet</h4>
                    <p class="mt-1 text-xs text-slate-400">Create a group to start splitting expenses.</p>
                </div>
            @endforelse
        </div>

    @else
        <!-- ── Active Group Detail ──────────────────────────────────────── -->
        <div class="animate-in fade-in slide-in-from-right-4 duration-500 space-y-8 pb-20">

            <!-- Header -->
            <div class="flex items-center gap-4">
                <button
                    wire:click="$set('activeGroupId', null)"
                    class="h-11 w-11 flex items-center justify-center rounded-2xl bg-white border border-slate-100 text-slate-900 shadow-sm transition-all hover:bg-slate-50 active:scale-90"
                >
                    <x-icon name="chevron-left" class="w-5 h-5" stroke-width="2.5" />
                </button>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                        <x-icon :name="$activeGroup->emoji" class="w-5 h-5" stroke-width="2" />
                    </div>
                    <div>
                        <h2 class="text-xl font-extrabold text-slate-900 leading-none">{{ $activeGroup->name }}</h2>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-1">Group Details</p>
                    </div>
                </div>
            </div>

            <!-- Balances -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 px-1">Debt Balances</h3>
                @forelse($activeBalances as $b)
                    <div class="flex items-center justify-between rounded-3xl bg-white p-4 border border-slate-100 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-50 text-xs font-extrabold text-slate-700">
                                {{ substr($b['fromUser']->name, 0, 1) }}
                            </div>
                            <x-icon name="arrow-right" class="w-3.5 h-3.5 text-slate-300" stroke-width="2.5" />
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 text-xs font-extrabold text-indigo-700">
                                {{ substr($b['toUser']->name, 0, 1) }}
                            </div>
                            <span class="text-xs font-semibold text-slate-600 ml-1">
                                {{ $b['from'] === Auth::id() ? 'You owe' : $b['fromUser']->name . ' owes' }}
                                {{ $b['to'] === Auth::id() ? 'you' : $b['toUser']->name }}
                            </span>
                        </div>
                        <p class="text-sm font-extrabold text-rose-500">RS{{ number_format($b['amount'], 0) }}</p>
                    </div>
                @empty
                    <div class="rounded-3xl border-2 border-dashed border-slate-100 p-8 text-center">
                        <div class="flex justify-center mb-2 text-emerald-500">
                            <x-icon name="circle-check" class="w-6 h-6" stroke-width="2" />
                        </div>
                        <p class="text-xs font-bold text-slate-400">All settled up!</p>
                    </div>
                @endforelse
            </div>

            <!-- Expenses List -->
            <div class="space-y-3">
                <h3 class="text-xs font-bold uppercase tracking-widest text-slate-400 px-1">Expense History</h3>
                @forelse($activeExpenses as $exp)
                    <div class="relative overflow-hidden rounded-[2rem] bg-white p-5 border border-slate-100 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600">
                                    <x-icon :name="$exp->category ?? 'receipt'" class="w-5 h-5" stroke-width="2" />
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900 capitalize">{{ $exp->description }}</p>
                                    <p class="text-[10px] font-semibold text-slate-400">Paid by {{ $exp->payer->name }} · {{ $exp->date->format('M d') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <p class="text-sm font-extrabold text-slate-900">RS{{ number_format($exp->amount, 0) }}</p>
                                @if($exp->paid_by === Auth::id() || $activeGroup->created_by === Auth::id())
                                    <button
                                        wire:click="deleteExpense({{ $exp->id }})"
                                        wire:confirm="Delete this expense?"
                                        class="p-2 rounded-xl text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition-colors"
                                        title="Delete expense"
                                    >
                                        <x-icon name="trash" class="w-4 h-4" stroke-width="2" />
                                    </button>
                                @endif
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5 pl-[3.75rem]">
                            @foreach($exp->participants as $p)
                                <span class="rounded-lg bg-slate-50 px-2 py-1 text-[9px] font-semibold text-slate-400">
                                    {{ $p->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-center py-8 text-xs font-semibold text-slate-300">No expenses recorded yet.</p>
                @endforelse
            </div>

            <!-- Settings Panel -->
            <div class="rounded-[2.5rem] bg-rose-50 p-6 border border-rose-100">
                <div class="flex items-center gap-2 mb-5 px-1">
                    <x-icon name="settings" class="w-4 h-4 text-rose-400" stroke-width="2" />
                    <h3 class="text-xs font-bold uppercase tracking-widest text-rose-400">Settings</h3>
                </div>

                <div class="space-y-4">
                    <!-- Add Member -->
                    <form wire:submit.prevent="addMember({{ $activeGroupId }})" class="flex gap-2">
                        <input
                            type="email"
                            wire:model="searchEmail"
                            placeholder="Add member by email..."
                            class="flex-1 rounded-2xl border-white bg-white px-4 py-3 text-xs font-semibold focus:border-rose-200 focus:ring-0 outline-none transition-all shadow-sm"
                        >
                        <button type="submit" class="rounded-2xl bg-slate-900 px-6 py-3 text-xs font-bold text-white hover:bg-black transition-colors">
                            Add
                        </button>
                    </form>
                    @error('searchEmail') <p class="text-[10px] font-semibold text-rose-500 px-2">{{ $message }}</p> @enderror

                    <!-- Members list -->
                    <div class="flex flex-wrap gap-2 py-1">
                        @foreach($activeGroup->members as $member)
                            <div class="flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-[10px] font-bold text-slate-600 border border-slate-100 shadow-sm">
                                <span class="h-4 w-4 rounded-full bg-indigo-100 text-[8px] flex items-center justify-center font-bold text-indigo-700">
                                    {{ substr($member->name, 0, 1) }}
                                </span>
                                {{ $member->name }}
                                @if($member->id === $activeGroup->created_by)
                                    <x-icon name="crown" class="w-2.5 h-2.5 text-amber-400" stroke-width="2" />
                                @endif
                            </div>
                        @endforeach
                    </div>

                    @error('leave') <p class="text-[10px] font-semibold text-rose-500 px-2">{{ $message }}</p> @enderror

                    @if($activeGroup->created_by === Auth::id())
                        <button
                            wire:click="deleteGroup({{ $activeGroupId }})"
                            wire:confirm="Delete group and ALL its data permanently?"
                            class="w-full flex items-center justify-center gap-2 rounded-2xl bg-white border border-rose-200 py-3 text-xs font-bold text-rose-500 transition-all hover:bg-rose-500 hover:text-white"
                        >
                            <x-icon name="trash" class="w-4 h-4" stroke-width="2" />
                            Delete Group Permanently
                        </button>
                    @else
                        <button
                            wire:click="leaveGroup({{ $activeGroupId }})"
                            wire:confirm="Leave this group?"
                            class="w-full flex items-center justify-center gap-2 rounded-2xl bg-white border border-slate-200 py-3 text-xs font-bold text-slate-500 transition-all hover:bg-slate-100"
                        >
                            <x-icon name="log-out" class="w-4 h-4" stroke-width="2" />
                            Leave Group
                        </button>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
