<?php

use Livewire\Volt\Component;
use App\Models\Group;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $description = '';
    public $amount = '';
    public $selectedGroupId = '';
    public $paidBy = '';
    public $participants = [];
    public $splitType = 'equal';
    public $customAmounts = [];
    public $date;
    public $step = 'input'; // input, preview, done

    public function mount()
    {
        $this->date = date('Y-m-d');
        $this->paidBy = Auth::id();
    }

    public function with()
    {
        $groups = Auth::user()->groups()->with('members')->get();
        $members = $this->selectedGroupId 
            ? Group::find($this->selectedGroupId)->members 
            : collect();

        return [
            'groups' => $groups,
            'members' => $members,
        ];
    }

    public function updatedSelectedGroupId($value)
    {
        if ($value) {
            $group = Group::find($value);
            if ($group) {
                $this->participants = $group->members->pluck('id')->map(fn($id) => (string)$id)->toArray();
                $this->paidBy = (string)Auth::id();
            }
        }
    }

    public function goToPreview()
    {
        $this->validate([
            'description' => 'required|min:3',
            'amount' => 'required|numeric|min:0.01',
            'selectedGroupId' => 'required|exists:groups,id',
            'paidBy' => 'required|exists:users,id',
            'participants' => 'required|array|min:2',
        ]);

        if ($this->splitType === 'custom') {
            $total = array_sum($this->customAmounts);
            if (abs($total - $this->amount) > 0.01) {
                $this->addError('custom_total', 'Sum of custom amounts (' . $total . ') must equal total amount (' . $this->amount . ')');
                return;
            }
        }

        $this->step = 'preview';
    }

    public function saveExpense()
    {
        $expense = Expense::create([
            'group_id' => $this->selectedGroupId,
            'description' => $this->description,
            'amount' => $this->amount,
            'paid_by' => $this->paidBy,
            'date' => $this->date,
            'split_type' => $this->splitType,
        ]);

        $attachData = [];
        $eachAmount = $this->amount / count($this->participants);

        foreach ($this->participants as $pId) {
            $attachData[$pId] = [
                'amount' => ($this->splitType === 'custom') 
                    ? ($this->customAmounts[$pId] ?? 0) 
                    : $eachAmount
            ];
        }

        $expense->participants()->attach($attachData);
        $this->step = 'done';
    }

    public function resetForm()
    {
        $this->reset(['description', 'amount', 'step', 'customAmounts', 'participants']);
        $this->date = date('Y-m-d');
        $this->paidBy = Auth::id();
    }

    public function getPreviewDebts()
    {
        if (!$this->amount || count($this->participants) < 2) return [];
        
        $debts = [];
        $eachAmount = $this->amount / count($this->participants);

        foreach ($this->participants as $pId) {
            if ($pId == $this->paidBy) continue;
            
            $share = ($this->splitType === 'custom') 
                ? ($this->customAmounts[$pId] ?? 0) 
                : $eachAmount;

            if ($share > 0) {
                $debts[] = [
                    'from' => User::find($pId),
                    'to' => User::find($this->paidBy),
                    'amount' => $share
                ];
            }
        }
        return $debts;
    }
};
?>

<div class="max-w-lg mx-auto">
    @if($step === 'done')
        <div class="flex flex-col items-center justify-center py-20 text-center animate-in zoom-in fade-in duration-500">
            <div class="h-24 w-24 rounded-full bg-emerald-50 flex items-center justify-center mb-6">
                <svg xmlns="http://www.w3.org/2000/svg" width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" class="text-emerald-500"><path d="M20 6 9 17l-5-5"/></svg>
            </div>
            <h2 class="text-2xl font-black text-slate-900">Expense Added!</h2>
            <p class="text-sm font-bold text-slate-400 mt-2">The debts have been distributed.</p>
            <button 
                wire:click="resetForm"
                class="mt-10 rounded-2xl bg-slate-900 px-8 py-4 text-sm font-black text-white shadow-xl hover:bg-black transition-all active:scale-95"
            >
                Add Another Expense
            </button>
        </div>
    @elseif($step === 'preview')
        <div class="space-y-8 animate-in slide-in-from-right-4 fade-in duration-500">
            <div class="flex items-center gap-4">
                <button wire:click="$set('step', 'input')" class="h-10 w-10 flex items-center justify-center rounded-xl bg-white border border-slate-100 shadow-sm text-slate-900">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m15 18-6-6 6-6"/></svg>
                </button>
                <h3 class="text-lg font-black text-slate-900">Visual Check</h3>
            </div>

            <div class="rounded-[2rem] bg-indigo-600 p-6 text-white shadow-xl shadow-indigo-100">
                <p class="text-[10px] font-black uppercase tracking-widest text-indigo-200">Recording Expense</p>
                <h4 class="text-xl font-black mt-1 capitalize">{{ $description }}</h4>
                <div class="mt-6 flex items-center justify-between border-t border-indigo-500/50 pt-4">
                    <div>
                        <p class="text-[10px] font-black uppercase text-indigo-300">Total Amount</p>
                        <p class="text-lg font-black">RS{{ number_format($amount, 2) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-black uppercase text-indigo-300">Paid By</p>
                        <p class="text-lg font-black">{{ User::find($paidBy)->name }}</p>
                    </div>
                </div>
            </div>

            <div class="space-y-3">
                <p class="px-2 text-[10px] font-black uppercase tracking-widest text-slate-400">Calculated Debt Shares</p>
                @foreach($this->getPreviewDebts() as $debt)
                    <div class="flex items-center justify-between rounded-2xl bg-white p-4 border border-slate-100 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="h-8 w-8 rounded-full bg-slate-50 flex items-center justify-center text-[10px] font-black text-slate-600 border border-slate-100">
                                {{ substr($debt['from']->name, 0, 1) }}
                            </span>
                            <span class="text-xs font-bold text-slate-600"><strong>{{ $debt['from']->name }}</strong> owes <strong>{{ $debt['to']->name }}</strong></span>
                        </div>
                        <p class="text-sm font-black text-rose-500">RS{{ number_format($debt['amount'], 2) }}</p>
                    </div>
                @endforeach
            </div>

            <button 
                wire:click="saveExpense"
                class="w-full rounded-2xl bg-indigo-600 py-5 text-sm font-black text-white shadow-xl shadow-indigo-100 transition-all hover:bg-indigo-700 active:scale-95"
            >
                Confirm & Record Expense
            </button>
        </div>
    @else
        <div class="space-y-8 animate-in fade-in duration-500">
            <header class="text-center">
                <h3 class="text-2xl font-black text-slate-900 tracking-tight">Add Expense</h3>
                <p class="text-xs font-bold text-slate-400 mt-1 uppercase tracking-widest">Split with your tribe</p>
            </header>

            <form wire:submit.prevent="goToPreview" class="space-y-6 pb-20">
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
                    <div class="space-y-6 animate-in slide-in-from-top-4 duration-500">
                        <!-- Description & Amount -->
                        <div class="space-y-4">
                            <div class="space-y-3">
                                <label class="px-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 block">Expense Details</label>
                                <input 
                                    type="text" 
                                    wire:model="description"
                                    placeholder="What was it for?"
                                    class="w-full rounded-2xl border-slate-50 bg-white px-5 py-4 text-sm font-bold focus:border-indigo-500 focus:ring-0 outline-none transition-all shadow-sm"
                                >
                                @error('description') <p class="text-[10px] font-bold text-rose-500 px-1">{{ $message }}</p> @enderror
                            </div>

                            <div class="relative">
                                <span class="absolute left-6 top-1/2 -translate-y-1/2 text-lg font-black text-slate-300">RS</span>
                                <input 
                                    type="number" 
                                    step="0.01"
                                    wire:model.live="amount"
                                    placeholder="0.00"
                                    class="w-full rounded-[2rem] border-slate-50 bg-white pl-14 pr-6 py-6 text-3xl font-black focus:border-indigo-500 focus:ring-0 outline-none transition-all shadow-sm"
                                >
                            </div>
                            @error('amount') <p class="text-[10px] font-bold text-rose-500 px-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Paid By -->
                        <div class="space-y-3">
                            <label class="px-1 text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 block">Paid By</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($members as $m)
                                    <button 
                                        type="button"
                                        wire:click="$set('paidBy', {{ $m->id }})"
                                        class="flex items-center gap-2 rounded-full border-2 px-4 py-2 transition-all {{ $paidBy == $m->id ? 'border-indigo-600 bg-indigo-50 text-indigo-700 shadow-sm' : 'border-slate-50 bg-white text-slate-600' }}"
                                    >
                                        <span class="h-4 w-4 rounded-full bg-slate-100 text-[8px] flex items-center justify-center font-black">
                                            {{ substr($m->name, 0, 1) }}
                                        </span>
                                        <span class="text-xs font-bold">{{ $m->name }}</span>
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Split Strategy -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between px-1">
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-slate-400 block">Split Strategy</label>
                                <div class="flex bg-slate-100 p-1 rounded-xl">
                                    <button 
                                        type="button" 
                                        wire:click="$set('splitType', 'equal')"
                                        class="px-4 py-1.5 text-[10px] font-black uppercase rounded-lg transition-all {{ $splitType === 'equal' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-400' }}"
                                    >
                                        Equal
                                    </button>
                                    <button 
                                        type="button" 
                                        wire:click="$set('splitType', 'custom')"
                                        class="px-4 py-1.5 text-[10px] font-black uppercase rounded-lg transition-all {{ $splitType === 'custom' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-400' }}"
                                    >
                                        Custom
                                    </button>
                                </div>
                            </div>

                            @if($splitType === 'equal')
                                <div class="rounded-[2rem] bg-white p-2 border border-slate-50 shadow-sm grid grid-cols-2 gap-1 overflow-hidden">
                                    @foreach($members as $m)
                                        <label class="flex items-center gap-3 rounded-2xl px-4 py-3 transition-colors cursor-pointer {{ in_array($m->id, $participants) ? 'bg-indigo-50/50' : '' }}">
                                            <input 
                                                type="checkbox" 
                                                wire:model.live="participants" 
                                                value="{{ $m->id }}"
                                                class="h-5 w-5 rounded-lg border-slate-200 text-indigo-600 focus:ring-0"
                                            >
                                            <span class="text-xs font-bold text-slate-700">{{ $m->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="space-y-2">
                                    @foreach($members as $m)
                                        <div class="flex items-center justify-between rounded-2xl bg-white p-4 border border-slate-50 shadow-sm">
                                            <div class="flex items-center gap-3">
                                                <input 
                                                    type="checkbox" 
                                                    wire:model.live="participants" 
                                                    value="{{ $m->id }}"
                                                    class="h-5 w-5 rounded-lg border-slate-200 text-indigo-600 focus:ring-0"
                                                >
                                                <span class="text-xs font-bold text-slate-700">{{ $m->name }}</span>
                                            </div>
                                            @if(in_array($m->id, $participants))
                                                <div class="relative w-28">
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-black text-slate-300">RS</span>
                                                    <input 
                                                        type="number" 
                                                        wire:model.live="customAmounts.{{ $m->id }}"
                                                        class="w-full rounded-xl border-slate-100 bg-slate-50 pl-8 pr-4 py-2 text-xs font-black focus:border-indigo-500 focus:ring-0 outline-none"
                                                        placeholder="0.00"
                                                    >
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                    @error('custom_total') <p class="text-[10px] font-bold text-rose-500 px-2 mt-1">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>

                        <button 
                            type="submit"
                            class="w-full rounded-2xl bg-indigo-600 py-5 text-sm font-black text-white shadow-xl shadow-indigo-100 transition-all hover:bg-indigo-700 active:scale-95"
                        >
                            Preview Split
                        </button>
                    </div>
                @endif
            </form>
        </div>
    @endif
</div>