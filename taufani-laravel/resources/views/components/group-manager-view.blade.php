<?php

use Livewire\Volt\Component;
use App\Models\Group;
use App\Models\User;
use App\Models\Expense;
use App\Models\Settlement;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $showCreate = false;
    public $name = '';
    public $emoji = '👥';

    public $searchEmail = '';
    public $activeGroupId = null;

    public function with()
    {
        $user = Auth::user();
        $groups = $user->groups()->with('members')->withCount(['members', 'expenses'])->latest()->get();
        
        $activeGroup = null;
        $activeExpenses = collect();
        $activeBalances = [];

        if ($this->activeGroupId) {
            $activeGroup = Group::with('members')->find($this->activeGroupId);
            if ($activeGroup) {
                $activeExpenses = Expense::where('group_id', $this->activeGroupId)
                    ->with(['payer', 'participants'])
                    ->latest()
                    ->get();
                $activeBalances = $this->calculateGroupBalances($this->activeGroupId);
            }
        }

        return [
            'groups' => $groups,
            'activeGroup' => $activeGroup,
            'activeExpenses' => $activeExpenses,
            'activeBalances' => $activeBalances,
        ];
    }

    protected function calculateGroupBalances($groupId)
    {
        $balanceMap = [];
        $pairKey = function($a, $b) {
            return $a < $b ? "$a|$b" : "$b|$a";
        };
        $pairSign = function($from, $to) {
            return $from < $to ? 1 : -1;
        };

        $expenses = Expense::where('group_id', $groupId)->with(['participants'])->get();
        foreach ($expenses as $exp) {
            $n = $exp->participants->count();
            if ($n === 0) continue;

            foreach ($exp->participants as $p) {
                if ($p->id === $exp->paid_by) continue;

                $share = ($exp->split_type === 'custom') 
                    ? ($p->pivot->amount ?? 0) 
                    : ($exp->amount / $n);

                $k = $pairKey($p->id, $exp->paid_by);
                $s = $pairSign($p->id, $exp->paid_by);
                $balanceMap[$k] = ($balanceMap[$k] ?? 0) + ($share * $s);
            }
        }

        $settlements = Settlement::where('group_id', $groupId)->get();
        foreach ($settlements as $stl) {
            $k = $pairKey($stl->from_id, $stl->to_id);
            $s = $pairSign($stl->from_id, $stl->to_id);
            $balanceMap[$k] = ($balanceMap[$k] ?? 0) - ($stl->amount * $s);
        }

        $results = [];
        foreach ($balanceMap as $k => $val) {
            if (abs($val) < 0.01) continue;
            [$a, $b] = explode('|', $k);
            if ($val > 0) {
                $results[] = ['from' => (int)$a, 'to' => (int)$b, 'amount' => round($val, 2)];
            } else {
                $results[] = ['from' => (int)$b, 'to' => (int)$a, 'amount' => round(-$val, 2)];
            }
        }

        return $results;
    }

    public function createGroup()
    {
        $this->validate([
            'name' => 'required|min:3',
        ]);

        $group = Group::create([
            'name' => $this->name,
            'emoji' => $this->emoji,
            'created_by' => Auth::id(),
        ]);

        $group->members()->attach(Auth::id());
        $this->reset(['name', 'emoji', 'showCreate']);
    }

    public function addMember($groupId)
    {
        $this->validate([
            'searchEmail' => 'required|email|exists:users,email',
        ], [
            'searchEmail.exists' => 'No user found with that email address.',
        ]);

        $userToAdd = User::where('email', $this->searchEmail)->first();
        $group = Group::findOrFail($groupId);

        if ($group->members()->where('users.id', $userToAdd->id)->exists()) {
            $this->addError('searchEmail', 'User is already a member.');
            return;
        }

        $group->members()->attach($userToAdd->id);
        $this->reset('searchEmail');
    }

    public function deleteGroup($id)
    {
        $group = Group::findOrFail($id);
        $group->delete();
        if ($this->activeGroupId == $id) {
            $this->activeGroupId = null;
        }
    }

    public function deleteExpense($id)
    {
        $expense = Expense::findOrFail($id);
        $expense->delete();
    }
};
?>

<div class="space-y-6">
    @if(!$activeGroupId)
        <div class="flex items-center justify-between px-1">
            <h3 class="text-sm font-black uppercase tracking-widest text-slate-400">Your Groups</h3>
            <button 
                wire:click="$toggle('showCreate')"
                class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-lg shadow-indigo-100 transition-all hover:scale-110 active:scale-95"
            >
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            </button>
        </div>

        @if($showCreate)
            <div class="rounded-[2.5rem] bg-white p-6 shadow-sm border border-slate-100 animate-in fade-in slide-in-from-top-4 duration-300">
                <div class="mb-6 flex items-center justify-between">
                    <h4 class="text-lg font-black text-slate-900">New Group</h4>
                    <button wire:click="$set('showCreate', false)" class="h-8 w-8 flex items-center justify-center rounded-full bg-slate-50 text-slate-400 hover:text-slate-600">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-x"><path d="M18 6L6 18"/><path d="M6 6l12 12"/></svg>
                    </button>
                </div>
                
                <form wire:submit.prevent="createGroup" class="space-y-6">
                    <div>
                        <label class="px-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 mb-2 block">Group Name</label>
                        <input 
                            type="text" 
                            wire:model="name"
                            placeholder="e.g. Goa Trip 🌴"
                            class="w-full rounded-2xl border-slate-100 bg-slate-50 px-5 py-4 text-sm font-bold focus:border-indigo-500 focus:ring-0 outline-none transition-all"
                        >
                        @error('name') <p class="mt-2 text-xs font-bold text-rose-500 px-1">{{ $message }}</p> @enderror
                    </div>

                    <div class="grid grid-cols-5 gap-3">
                        @foreach(['👥', '🌴', '🏠', '🍕', '🍻', '🛹', '🍿', '🎸', '🏟️', '🚗'] as $e)
                            <button 
                                type="button"
                                wire:click="$set('emoji', '{{ $e }}')"
                                class="flex h-12 items-center justify-center rounded-2xl border-2 transition-all {{ $emoji === $e ? 'border-indigo-600 bg-indigo-50 text-lg shadow-sm' : 'border-transparent bg-slate-50 hover:bg-slate-100' }}"
                            >
                                {{ $e }}
                            </button>
                        @endforeach
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

        <div class="grid gap-4">
            @forelse($groups as $group)
                <div 
                    wire:click="$set('activeGroupId', {{ $group->id }})"
                    class="group relative cursor-pointer overflow-hidden rounded-[2.5rem] bg-white p-6 shadow-sm border border-slate-100 transition-all hover:border-indigo-200 hover:shadow-xl hover:shadow-indigo-50 hover:-translate-y-1"
                >
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-5">
                            <div class="flex h-16 w-16 items-center justify-center rounded-3xl bg-slate-50 text-3xl group-hover:bg-indigo-50 transition-colors">
                                {{ $group->emoji }}
                            </div>
                            <div>
                                <h4 class="text-lg font-black text-slate-900 group-hover:text-indigo-600 transition-colors">{{ $group->name }}</h4>
                                <p class="text-xs font-bold text-slate-400 capitalize tracking-tight">{{ $group->members_count }} members • {{ $group->expenses_count }} expenses</p>
                            </div>
                        </div>
                        <div class="h-10 w-10 flex items-center justify-center rounded-full bg-slate-50 text-slate-300 group-hover:text-indigo-600 transition-colors">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"/></svg>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-[2.5rem] border-2 border-dashed border-slate-100 p-16 text-center">
                    <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-slate-300"><path d="M17 6.1H3"/><path d="M21 12.1H3"/><path d="M15.1 18.1H3"/></svg>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900">No Groups Found</h4>
                    <p class="mt-1 text-xs text-slate-400">Join or create a group to start splitting.</p>
                </div>
            @endforelse
        </div>
    @else
        <!-- Active Group Detail View -->
        <div class="animate-in fade-in slide-in-from-right-4 duration-500 space-y-8 pb-20">
            <!-- Header -->
            <div class="flex items-center gap-4">
                <button 
                    wire:click="$set('activeGroupId', null)"
                    class="h-12 w-12 flex items-center justify-center rounded-2xl bg-white border border-slate-100 text-slate-900 shadow-sm transition-all hover:bg-slate-50 active:scale-90"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <div class="flex items-center gap-3">
                    <span class="text-3xl">{{ $activeGroup->emoji }}</span>
                    <div>
                        <h2 class="text-xl font-black text-slate-900 leading-none">{{ $activeGroup->name }}</h2>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mt-1">Group Details</p>
                    </div>
                </div>
            </div>

            <!-- Balances -->
            <div class="space-y-4">
                <h3 class="text-sm font-black uppercase tracking-widest text-slate-400 px-1">Debt Balances</h3>
                @forelse($activeBalances as $b)
                    @php
                        $fromUser = App\Models\User::find($b['from']);
                        $toUser = App\Models\User::find($b['to']);
                    @endphp
                    <div class="flex items-center justify-between rounded-3xl bg-white p-5 border border-slate-100 shadow-sm">
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-slate-50 text-xs font-black text-slate-700">
                                {{ substr($fromUser->name, 0, 1) }}
                            </div>
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="text-slate-300"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-50 text-xs font-black text-indigo-700">
                                {{ substr($toUser->name, 0, 1) }}
                            </div>
                            <span class="text-xs font-bold text-slate-600 ml-1">
                                {{ $fromUser->id === Auth::id() ? 'You owe' : $fromUser->name . ' owes' }} {{ $toUser->id === Auth::id() ? 'you' : $toUser->name }}
                            </span>
                        </div>
                        <p class="text-sm font-black text-rose-500">RS{{ number_format($b['amount'], 0) }}</p>
                    </div>
                @empty
                    <div class="rounded-3xl border-2 border-dashed border-slate-100 p-8 text-center text-slate-300 font-bold text-xs">
                        All settled up! 🎉
                    </div>
                @endforelse
            </div>

            <!-- Expenses List -->
            <div class="space-y-4">
                <h3 class="text-sm font-black uppercase tracking-widest text-slate-400 px-1">Expense History</h3>
                @forelse($activeExpenses as $exp)
                    <div class="group relative overflow-hidden rounded-[2rem] bg-white p-5 border border-slate-100 shadow-sm">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-4">
                                <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-indigo-50 text-xl">
                                    💰
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-slate-900 capitalize">{{ $exp->description }}</p>
                                    <p class="text-[10px] font-bold text-slate-400">Paid by {{ $exp->payer->name }} • {{ $exp->date->format('M d') }}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-4">
                                <p class="text-sm font-black text-slate-900">RS{{ number_format($exp->amount, 0) }}</p>
                                <button 
                                    wire:click="deleteExpense({{ $exp->id }})"
                                    onclick="confirm('Delete this expense?') || event.stopImmediatePropagation()"
                                    class="p-2 text-slate-200 hover:text-rose-500 transition-colors"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                </button>
                            </div>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-1.5 pl-16">
                            @foreach($exp->participants as $p)
                                <span class="rounded-lg bg-slate-50 px-2 py-1 text-[9px] font-bold text-slate-400 lowercase italic">
                                    {{ $p->name }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="text-center py-8 text-xs font-bold text-slate-300">No expenses recorded yet.</p>
                @endforelse
            </div>

            <!-- Manage Members Danger Zone -->
            <div class="rounded-[2.5rem] bg-rose-50 p-6 border border-rose-100">
                <h3 class="text-xs font-black uppercase tracking-widest text-rose-400 mb-4 px-1">Settings</h3>
                
                <div class="space-y-4">
                    <form wire:submit.prevent="addMember({{ $activeGroupId }})" class="flex gap-2">
                        <input 
                            type="email" 
                            wire:model="searchEmail"
                            placeholder="Add member by email..."
                            class="flex-1 rounded-2xl border-white bg-white px-4 py-3 text-xs font-bold focus:border-rose-200 focus:ring-0 outline-none transition-all shadow-sm"
                        >
                        <button type="submit" class="rounded-2xl bg-slate-900 px-6 py-3 text-xs font-bold text-white hover:bg-black transition-colors">
                            Add
                        </button>
                    </form>
                    @error('searchEmail') <p class="text-[10px] font-bold text-rose-500 px-2">{{ $message }}</p> @enderror

                    <div class="flex flex-wrap gap-2 py-2">
                        @foreach($activeGroup->members as $member)
                            <div class="flex items-center gap-2 rounded-full bg-white px-3 py-1.5 text-[10px] font-bold text-slate-600 border border-slate-100 shadow-sm">
                                <span class="h-4 w-4 rounded-full bg-indigo-100 text-[8px] flex items-center justify-center font-bold text-indigo-700">
                                    {{ substr($member->name, 0, 1) }}
                                </span>
                                {{ $member->name }}
                            </div>
                        @endforeach
                    </div>

                    <button 
                        wire:click="deleteGroup({{ $activeGroupId }})"
                        onclick="confirm('Delete group and ALL data?') || event.stopImmediatePropagation()"
                        class="w-full rounded-2xl bg-white border border-rose-200 py-3 text-xs font-bold text-rose-500 transition-all hover:bg-rose-500 hover:text-white"
                    >
                        Delete Group Permanently
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>