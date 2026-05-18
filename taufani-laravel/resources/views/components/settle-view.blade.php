<?php

use Livewire\Volt\Component;
use App\Models\Group;
use App\Models\Settlement;
use App\Services\BalanceCalculator;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $selectedGroupId = '';
    public $fromId = '';
    public $toId = '';
    public $amount = '';
    public $note = '';
    public $date;

    public function mount()
    {
        $this->date = date('Y-m-d');
        $this->toId = (string) Auth::id();
    }

    public function updatedSelectedGroupId($value)
    {
        $this->fromId = '';
        $this->toId   = (string) Auth::id();

        if ($value) {
            $group       = Group::with('members')->find($value);
            $otherMember = $group?->members->where('id', '!=', Auth::id())->first();
            if ($otherMember) {
                $this->fromId = (string) $otherMember->id;
            }
        }
    }

    public function with()
    {
        $groups  = Auth::user()->groups()->with('members')->get();
        $members = $this->selectedGroupId
            ? Group::find($this->selectedGroupId)?->members ?? collect()
            : collect();

        $userId = Auth::id();

        $recentSettlements = $this->selectedGroupId
            ? Settlement::where('group_id', $this->selectedGroupId)
                ->where(fn ($q) => $q->where('from_id', $userId)->orWhere('to_id', $userId))
                ->with(['from', 'to'])
                ->latest()
                ->take(5)
                ->get()
            : collect();

        $groupBalances = [];
        if ($this->selectedGroupId) {
            $calculator    = new BalanceCalculator();
            $groupBalances = $calculator->withUsers(
                $calculator->calculate(collect([$this->selectedGroupId]), $userId)
            );
        }

        return [
            'groups'            => $groups,
            'members'           => $members,
            'recentSettlements' => $recentSettlements,
            'groupBalances'     => $groupBalances,
        ];
    }

    public function saveSettlement()
    {
        $this->validate([
            'selectedGroupId' => 'required|exists:groups,id',
            'fromId'          => 'required|exists:users,id|different:toId',
            'toId'            => 'required|exists:users,id',
            'amount'          => 'required|numeric|min:0.01',
            'note'            => 'nullable|string|max:255',
        ]);

        Settlement::create([
            'group_id' => $this->selectedGroupId,
            'from_id'  => $this->fromId,
            'to_id'    => $this->toId,
            'amount'   => $this->amount,
            'date'     => $this->date,
            'note'     => $this->note ?: null,
        ]);

        $this->reset(['amount', 'note']);
        $this->dispatch('settlement-saved');
    }

    public function deleteSettlement($id)
    {
        $settlement = Settlement::findOrFail($id);

        abort_unless(
            $settlement->from_id === Auth::id() || $settlement->to_id === Auth::id(),
            403
        );

        $settlement->delete();
    }
};
?>

<div class="space-y-8 animate-in fade-in duration-500 pb-20">

    <!-- Header -->
    <div class="text-center">
        <h3 class="text-xl font-extrabold text-slate-900">Record Payment</h3>
        <p class="mt-1 text-xs text-slate-400">Mark a debt as settled between members</p>
    </div>

    @if (session()->has('success'))
        <div class="flex items-center gap-3 rounded-2xl bg-emerald-50 border border-emerald-100 px-4 py-3 text-sm font-semibold text-emerald-700 shadow-sm animate-in fade-in zoom-in duration-300">
            <x-icon name="circle-check" class="w-4 h-4 shrink-0 text-emerald-500" stroke-width="2.5" />
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="saveSettlement" class="space-y-7">

        <!-- Group Selection -->
        <div class="space-y-2.5">
            <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">Select Group</label>
            <div class="grid grid-cols-2 gap-3">
                @foreach($groups as $g)
                    <button
                        type="button"
                        wire:click="$set('selectedGroupId', {{ $g->id }})"
                        class="flex flex-col items-center gap-2.5 rounded-[1.5rem] border-2 p-4 transition-all {{ $selectedGroupId == $g->id ? 'border-indigo-500 bg-indigo-50 shadow-sm' : 'border-slate-100 bg-white hover:border-slate-200' }}"
                    >
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl {{ $selectedGroupId == $g->id ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-500' }} transition-colors">
                            <x-icon :name="$g->emoji" class="w-5 h-5" stroke-width="2" />
                        </div>
                        <span class="text-xs font-semibold text-slate-700 text-center leading-tight">{{ $g->name }}</span>
                    </button>
                @endforeach
            </div>
            @error('selectedGroupId') <p class="text-[11px] font-medium text-rose-500 px-1">{{ $message }}</p> @enderror
        </div>

        @if($selectedGroupId)
            <div class="space-y-7 animate-in slide-in-from-top-4 duration-300">

                <!-- Balance Summary -->
                @if(count($groupBalances) > 0)
                    <div class="space-y-2">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">Current Balances</label>
                        @foreach($groupBalances as $b)
                            <div class="flex items-center justify-between rounded-2xl bg-amber-50 border border-amber-100 px-4 py-3">
                                <span class="text-xs font-medium text-slate-600">
                                    {{ $b['from'] === Auth::id() ? 'You owe' : $b['fromUser']->name . ' owes' }}
                                    {{ $b['to'] === Auth::id() ? 'you' : $b['toUser']->name }}
                                </span>
                                <span class="text-sm font-bold text-amber-600">RS{{ number_format($b['amount'], 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-emerald-100 bg-emerald-50/40 py-4">
                        <x-icon name="circle-check" class="w-4 h-4 text-emerald-400" stroke-width="2" />
                        <span class="text-xs font-semibold text-emerald-600">All settled up in this group</span>
                    </div>
                @endif

                <!-- Who paid → Whom -->
                <div class="space-y-4">
                    <div class="space-y-2.5">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">Who Paid?</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($members as $m)
                                <button
                                    type="button"
                                    wire:click="$set('fromId', {{ $m->id }})"
                                    class="flex items-center gap-2 rounded-full border-2 px-4 py-2 text-xs font-semibold transition-all {{ $fromId == $m->id ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm' : 'border-slate-100 bg-white text-slate-600 hover:border-slate-200' }}"
                                >
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-200 text-[8px] font-black text-slate-600">
                                        {{ strtoupper(substr($m->name, 0, 1)) }}
                                    </span>
                                    {{ $m->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-center">
                        <div class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-900 text-white shadow-lg">
                            <x-icon name="arrow-down" class="w-4 h-4" stroke-width="2.5" />
                        </div>
                    </div>

                    <div class="space-y-2.5">
                        <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">To Whom?</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($members as $m)
                                <button
                                    type="button"
                                    wire:click="$set('toId', {{ $m->id }})"
                                    class="flex items-center gap-2 rounded-full border-2 px-4 py-2 text-xs font-semibold transition-all {{ $toId == $m->id ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm' : 'border-slate-100 bg-white text-slate-600 hover:border-slate-200' }}"
                                >
                                    <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-200 text-[8px] font-black text-slate-600">
                                        {{ strtoupper(substr($m->name, 0, 1)) }}
                                    </span>
                                    {{ $m->name }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Amount -->
                <div class="space-y-2.5">
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">Amount</label>
                    <div class="relative">
                        <span class="absolute left-5 top-1/2 -translate-y-1/2 text-lg font-bold text-slate-300 pointer-events-none">RS</span>
                        <input
                            type="number"
                            step="0.01"
                            wire:model="amount"
                            placeholder="0.00"
                            class="w-full rounded-[1.75rem] border border-slate-200 bg-white pl-14 pr-6 py-5 text-3xl font-black focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-sm"
                        >
                    </div>
                    @error('amount') <p class="text-[11px] font-medium text-rose-500 px-1">{{ $message }}</p> @enderror
                </div>

                <!-- Note -->
                <div class="space-y-2.5">
                    <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">
                        Note <span class="normal-case font-normal text-slate-300">(optional)</span>
                    </label>
                    <input
                        type="text"
                        wire:model="note"
                        placeholder="e.g. Cash, bank transfer, UPI..."
                        class="w-full rounded-2xl border border-slate-200 bg-white px-5 py-3.5 text-sm font-medium placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-sm"
                    >
                    @error('note') <p class="text-[11px] font-medium text-rose-500 px-1">{{ $message }}</p> @enderror
                </div>

                <button
                    type="submit"
                    class="w-full rounded-2xl bg-indigo-600 py-4 text-sm font-bold text-white shadow-lg shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-[0.98]"
                >
                    Record Payment
                </button>

                <!-- Recent Settlements -->
                @if($recentSettlements->count() > 0)
                    <div class="space-y-3 pt-2">
                        <h4 class="text-[11px] font-bold uppercase tracking-widest text-slate-400 px-1">Recent Settlements</h4>
                        <div class="space-y-2">
                            @foreach($recentSettlements as $s)
                                <div class="flex items-center justify-between rounded-2xl bg-white border border-slate-100 px-4 py-3 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="flex -space-x-1.5 shrink-0">
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-slate-100 text-[9px] font-bold border-2 border-white">{{ strtoupper(substr($s->from->name, 0, 1)) }}</span>
                                            <span class="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-100 text-[9px] font-bold border-2 border-white text-indigo-700">{{ strtoupper(substr($s->to->name, 0, 1)) }}</span>
                                        </div>
                                        <div>
                                            <p class="text-xs font-semibold text-slate-700">
                                                {{ $s->from->name }} → {{ $s->to->name }}
                                            </p>
                                            @if($s->note)
                                                <p class="text-[11px] text-slate-400">{{ $s->note }}</p>
                                            @endif
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="text-sm font-bold text-emerald-600">RS{{ number_format($s->amount, 0) }}</span>
                                        @if($s->from_id === Auth::id() || $s->to_id === Auth::id())
                                            <button
                                                wire:click="deleteSettlement({{ $s->id }})"
                                                wire:confirm="Delete this settlement?"
                                                class="flex h-7 w-7 items-center justify-center rounded-xl text-slate-300 hover:text-rose-500 hover:bg-rose-50 transition-colors"
                                            >
                                                <x-icon name="trash" class="w-3.5 h-3.5" stroke-width="2.5" />
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </form>
</div>
