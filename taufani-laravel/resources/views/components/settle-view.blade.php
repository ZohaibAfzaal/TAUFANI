<?php

use Livewire\Volt\Component;
use App\Models\Group;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $selectedGroupId = '';
    public $fromId = '';
    public $toId = '';
    public $amount = '';
    public $date;

    public function mount()
    {
        $this->date = date('Y-m-d');
        $this->toId = (string)Auth::id();
    }

    public function with()
    {
        $groups = Auth::user()->groups()->with('members')->get();
        $members = $this->selectedGroupId 
            ? Group::find($this->selectedGroupId)->members 
            : collect();
        
        $recentSettlements = $this->selectedGroupId 
            ? Settlement::where('group_id', $this->selectedGroupId)->with(['from', 'to'])->latest()->take(5)->get()
            : collect();

        if ($this->selectedGroupId && empty($this->fromId)) {
            // Find someone who ISN'T the current user to be the default payer
            $otherMember = $members->where('id', '!=', Auth::id())->first();
            if ($otherMember) $this->fromId = (string)$otherMember->id;
        }

        return [
            'groups' => $groups,
            'members' => $members,
            'recentSettlements' => $recentSettlements,
        ];
    }

    public function saveSettlement()
    {
        $this->validate([
            'selectedGroupId' => 'required|exists:groups,id',
            'fromId' => 'required|exists:users,id|different:toId',
            'toId' => 'required|exists:users,id',
            'amount' => 'required|numeric|min:0.01',
        ]);

        Settlement::create([
            'group_id' => $this->selectedGroupId,
            'from_id' => $this->fromId,
            'to_id' => $this->toId,
            'amount' => $this->amount,
            'date' => $this->date,
        ]);

        session()->flash('success', 'Settlement recorded!');
        $this->reset(['amount']);
    }

    public function deleteSettlement($id)
    {
        $s = Settlement::findOrFail($id);
        $s->delete();
    }
};
?>

<div class="space-y-10 animate-in fade-in duration-500 pb-20">
    <header class="text-center">
        <h3 class="text-2xl font-black text-slate-900 tracking-tight">Record Payment</h3>
        <p class="text-xs font-bold text-slate-400 mt-1 uppercase tracking-widest">Settle debts between friends</p>
    </header>

    @if (session()->has('success'))
        <div class="rounded-2xl bg-emerald-500 p-4 text-white text-sm font-black border border-emerald-400 animate-in fade-in zoom-in duration-300 shadow-lg shadow-emerald-100 flex items-center gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
            {{ session('success') }}
        </div>
    @endif

    <form wire:submit.prevent="saveSettlement" class="space-y-8">
        <!-- Group Selection -->
        <div class="space-y-3">
            <label class="px-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 block">Select Group</label>
            <div class="grid grid-cols-2 gap-3">
                @foreach($groups as $g)
                    <button 
                        type="button"
                        wire:click="$set('selectedGroupId', {{ $g->id }})"
                        class="flex flex-col items-center gap-2 rounded-3xl border-2 p-4 transition-all {{ $selectedGroupId == $g->id ? 'border-indigo-600 bg-indigo-50 shadow-sm' : 'border-slate-50 bg-white hover:border-slate-100' }}"
                    >
                        <span class="text-2xl">{{ $g->emoji }}</span>
                        <span class="text-xs font-black text-slate-700">{{ $g->name }}</span>
                    </button>
                @endforeach
            </div>
            @error('selectedGroupId') <p class="text-[10px] font-bold text-rose-500 px-1">{{ $message }}</p> @enderror
        </div>

        @if($selectedGroupId)
            <div class="space-y-8 animate-in slide-in-from-top-4 duration-500">
                <!-- Who Paid Whom -->
                <div class="relative grid gap-4">
                    <div class="space-y-3">
                        <label class="px-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 block">Who Paid?</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($members as $m)
                                <button 
                                    type="button"
                                    wire:click="$set('fromId', {{ $m->id }})"
                                    class="flex items-center gap-2 rounded-full border-2 px-4 py-2 transition-all {{ $fromId == $m->id ? 'border-indigo-600 bg-indigo-50 text-indigo-700 shadow-sm' : 'border-slate-50 bg-white text-slate-600' }}"
                                >
                                    <span class="h-4 w-4 rounded-full bg-slate-100 text-[8px] flex items-center justify-center font-black">
                                        {{ substr($m->name, 0, 1) }}
                                    </span>
                                    <span class="text-xs font-bold">{{ $m->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-center -my-2 relative z-10">
                        <div class="h-10 w-10 flex items-center justify-center rounded-full bg-slate-900 text-white shadow-xl rotate-0 transition-transform">
                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m7 7 5 5-5 5"/><path d="m13 7 5 5-5 5"/></svg>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <label class="px-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 block">To Whom?</label>
                        <div class="flex flex-wrap gap-2">
                            @foreach($members as $m)
                                <button 
                                    type="button"
                                    wire:click="$set('toId', {{ $m->id }})"
                                    class="flex items-center gap-2 rounded-full border-2 px-4 py-2 transition-all {{ $toId == $m->id ? 'border-indigo-600 bg-indigo-50 text-indigo-700 shadow-sm' : 'border-slate-50 bg-white text-slate-600' }}"
                                >
                                    <span class="h-4 w-4 rounded-full bg-slate-100 text-[8px] flex items-center justify-center font-black">
                                        {{ substr($m->name, 0, 1) }}
                                    </span>
                                    <span class="text-xs font-bold">{{ $m->name }}</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>

                <!-- Amount -->
                <div class="space-y-4">
                    <label class="px-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 block">Settlement Amount</label>
                    <div class="relative">
                        <span class="absolute left-6 top-1/2 -translate-y-1/2 text-lg font-black text-slate-300">RS</span>
                        <input 
                            type="number" 
                            step="0.01"
                            wire:model="amount"
                            placeholder="0.00"
                            class="w-full rounded-[2rem] border-slate-50 bg-white pl-14 pr-6 py-6 text-3xl font-black focus:border-indigo-500 focus:ring-0 outline-none transition-all shadow-sm"
                        >
                    </div>
                    @error('amount') <p class="text-[10px] font-bold text-rose-500 px-1">{{ $message }}</p> @enderror
                </div>

                <button 
                    type="submit"
                    class="w-full rounded-2xl bg-indigo-600 py-6 text-sm font-black text-white shadow-xl shadow-indigo-100 transition-all hover:bg-indigo-700 active:scale-95"
                >
                    Record Payment
                </button>

                <!-- Recent Activity -->
                @if($recentSettlements->count() > 0)
                    <div class="space-y-4 pt-4">
                        <h4 class="px-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400">Recent Settlements</h4>
                        <div class="space-y-3">
                            @foreach($recentSettlements as $s)
                                <div class="group flex items-center justify-between rounded-2xl bg-white p-4 border border-slate-50 shadow-sm">
                                    <div class="flex items-center gap-3">
                                        <div class="flex -space-x-2">
                                            <span class="h-6 w-6 rounded-full bg-slate-100 flex items-center justify-center text-[8px] font-black border-2 border-white">{{ substr($s->from->name, 0, 1) }}</span>
                                            <span class="h-6 w-6 rounded-full bg-indigo-100 flex items-center justify-center text-[8px] font-black border-2 border-white">{{ substr($s->to->name, 0, 1) }}</span>
                                        </div>
                                        <p class="text-[11px] font-bold text-slate-600">
                                            {{ $s->from->name }} paid {{ $s->to->name }}
                                        </p>
                                    </div>
                                    <div class="flex items-center gap-3">
                                        <p class="text-xs font-black text-emerald-600">RS{{ number_format($s->amount, 0) }}</p>
                                        <button 
                                            wire:click="deleteSettlement({{ $s->id }})"
                                            class="opacity-0 group-hover:opacity-100 p-1 text-slate-300 hover:text-rose-500 transition-all"
                                        >
                                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 6h18"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                                        </button>
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