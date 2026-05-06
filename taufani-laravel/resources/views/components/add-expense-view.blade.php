<?php

use Livewire\Volt\Component;
use App\Models\Group;
use App\Models\Expense;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public $description = '';
    public $category    = 'receipt';
    public $amount      = '';
    public $selectedGroupId = '';
    public $paidBy      = '';
    public $participants = [];
    public $splitType   = 'equal';
    public $customAmounts = [];
    public $date;
    public $step = 'input';

    // keyword → icon map (first match wins)
    protected array $categoryMap = [
        'shopping-cart' => ['grocery', 'groceries', 'sabzi', 'vegetable', 'fruit', 'chicken', 'meat', 'ration', 'kiryana', 'market', 'supermarket', 'bazar', 'bazaar'],
        'utensils'      => ['dinner', 'lunch', 'breakfast', 'food', 'eat', 'meal', 'biryani', 'pizza', 'burger', 'shawarma', 'dine', 'dining', 'restaurant', 'cafe', 'hotel', 'snack', 'bbq'],
        'coffee'        => ['coffee', 'chai', 'tea', 'drink', 'juice'],
        'car'           => ['uber', 'careem', 'taxi', 'transport', 'fuel', 'petrol', 'bus', 'train', 'rickshaw', 'ride', 'vehicle', 'parking'],
        'plane'         => ['flight', 'travel', 'trip', 'tour', 'airport', 'hotel booking', 'visa'],
        'droplets'      => ['water', 'paani', 'drinking water', 'mineral water'],
        'zap'           => ['electricity', 'electric', 'bijli', 'wapda', 'power', 'generator'],
        'flame'         => ['gas', 'sui gas', 'lpg', 'cylinder'],
        'home'          => ['rent', 'house', 'flat', 'apartment', 'room', 'hostel', 'maintenance', 'repair'],
        'wifi'          => ['internet', 'wifi', 'broadband', 'data', 'sim', 'mobile', 'phone bill'],
        'heart-pulse'   => ['medicine', 'medical', 'doctor', 'hospital', 'pharmacy', 'health', 'dawai', 'clinic'],
        'graduation-cap'=> ['fee', 'fees', 'school', 'college', 'university', 'course', 'tuition', 'study', 'book', 'books', 'stationery'],
        'film'          => ['movie', 'cinema', 'netflix', 'youtube', 'streaming', 'entertainment', 'show'],
        'music'         => ['music', 'spotify', 'concert', 'party'],
        'shirt'         => ['clothes', 'clothing', 'shirt', 'shoes', 'shopping', 'dress', 'fashion'],
        'smartphone'    => ['phone', 'laptop', 'gadget', 'tech', 'apple', 'samsung', 'charger', 'accessories'],
        'briefcase'     => ['office', 'work', 'business', 'stationary'],
        'trophy'        => ['sports', 'game', 'gym', 'cricket', 'football', 'membership'],
    ];

    public function mount()
    {
        $this->date   = date('Y-m-d');
        $this->paidBy = Auth::id();
    }

    public function updatedDescription($value)
    {
        $lower = strtolower($value);
        foreach ($this->categoryMap as $icon => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $this->category = $icon;
                    return;
                }
            }
        }
        $this->category = 'receipt';
    }

    public function with()
    {
        $groups  = Auth::user()->groups()->with('members')->get();
        $members = $this->selectedGroupId
            ? Group::find($this->selectedGroupId)->members
            : collect();

        return ['groups' => $groups, 'members' => $members];
    }

    public function updatedSelectedGroupId($value)
    {
        if ($value) {
            $group = Group::find($value);
            if ($group) {
                $this->participants = $group->members->pluck('id')->map(fn($id) => (string)$id)->toArray();
                $this->paidBy       = (string) Auth::id();
            }
        }
    }

    public function goToPreview()
    {
        $this->validate([
            'description'     => 'required|min:3',
            'amount'          => 'required|numeric|min:0.01',
            'selectedGroupId' => 'required|exists:groups,id',
            'paidBy'          => 'required|exists:users,id',
            'participants'    => 'required|array|min:2',
        ]);

        if ($this->splitType === 'custom') {
            $total = array_sum($this->customAmounts);
            if (abs($total - $this->amount) > 0.01) {
                $this->addError('custom_total', "Custom amounts ({$total}) must equal total ({$this->amount})");
                return;
            }
        }

        $this->step = 'preview';
    }

    public function saveExpense()
    {
        $expense = Expense::create([
            'group_id'    => $this->selectedGroupId,
            'description' => $this->description,
            'category'    => $this->category,
            'amount'      => $this->amount,
            'paid_by'     => $this->paidBy,
            'date'        => $this->date,
            'split_type'  => $this->splitType,
        ]);

        $eachAmount = $this->amount / count($this->participants);
        $attachData = [];
        foreach ($this->participants as $pId) {
            $attachData[$pId] = [
                'amount' => $this->splitType === 'custom'
                    ? ($this->customAmounts[$pId] ?? 0)
                    : $eachAmount,
            ];
        }
        $expense->participants()->attach($attachData);
        $this->step = 'done';
    }

    public function resetForm()
    {
        $this->reset(['description', 'category', 'amount', 'step', 'customAmounts', 'participants']);
        $this->category = 'receipt';
        $this->date     = date('Y-m-d');
        $this->paidBy   = Auth::id();
    }

    public function getPreviewDebts()
    {
        if (!$this->amount || count($this->participants) < 2) return [];

        $eachAmount = $this->amount / count($this->participants);
        $debts = [];
        foreach ($this->participants as $pId) {
            if ($pId == $this->paidBy) continue;
            $share = $this->splitType === 'custom'
                ? ($this->customAmounts[$pId] ?? 0)
                : $eachAmount;
            if ($share > 0) {
                $debts[] = [
                    'from'   => User::find($pId),
                    'to'     => User::find($this->paidBy),
                    'amount' => $share,
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
                <x-icon name="check" class="w-12 h-12 text-emerald-500" stroke-width="3" />
            </div>
            <h2 class="text-2xl font-extrabold text-slate-900">Expense Added!</h2>
            <p class="text-sm text-slate-400 mt-2">Debts have been distributed across members.</p>
            <button
                wire:click="resetForm"
                class="mt-10 rounded-2xl bg-slate-900 px-8 py-4 text-sm font-bold text-white shadow-xl hover:bg-black transition-all active:scale-95"
            >
                Add Another
            </button>
        </div>

    @elseif($step === 'preview')
        <div class="space-y-6 animate-in slide-in-from-right-4 fade-in duration-500">
            <div class="flex items-center gap-4">
                <button wire:click="$set('step', 'input')"
                    class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white border border-slate-100 shadow-sm text-slate-600 hover:text-slate-900 transition-colors">
                    <x-icon name="chevron-left" class="w-5 h-5" stroke-width="2.5" />
                </button>
                <h3 class="text-lg font-extrabold text-slate-900">Visual Check</h3>
            </div>

            <!-- Summary card -->
            <div class="rounded-[2rem] bg-indigo-600 p-6 text-white shadow-xl shadow-indigo-200">
                <div class="flex items-center gap-3 mb-4">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/15">
                        <x-icon :name="$category" class="w-5 h-5 text-white" stroke-width="2" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-indigo-200">Recording Expense</p>
                        <h4 class="text-lg font-extrabold capitalize">{{ $description }}</h4>
                    </div>
                </div>
                <div class="flex items-center justify-between border-t border-indigo-500/50 pt-4">
                    <div>
                        <p class="text-[10px] font-bold uppercase text-indigo-300">Total Amount</p>
                        <p class="text-xl font-black">RS{{ number_format($amount, 2) }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold uppercase text-indigo-300">Paid By</p>
                        <p class="text-xl font-black">{{ User::find($paidBy)->name }}</p>
                    </div>
                </div>
            </div>

            <!-- Debt breakdown -->
            <div class="space-y-2">
                <p class="px-1 text-[11px] font-bold uppercase tracking-widest text-slate-400">Debt Shares</p>
                @foreach($this->getPreviewDebts() as $debt)
                    <div class="flex items-center justify-between rounded-2xl bg-white border border-slate-100 p-4 shadow-sm">
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 items-center justify-center rounded-2xl bg-slate-50 text-[11px] font-bold text-slate-600 border border-slate-100">
                                {{ strtoupper(substr($debt['from']->name, 0, 1)) }}
                            </span>
                            <span class="text-xs font-medium text-slate-600">
                                <strong>{{ $debt['from']->name }}</strong> owes <strong>{{ $debt['to']->name }}</strong>
                            </span>
                        </div>
                        <p class="text-sm font-bold text-rose-500">RS{{ number_format($debt['amount'], 2) }}</p>
                    </div>
                @endforeach
            </div>

            <button
                wire:click="saveExpense"
                class="w-full rounded-2xl bg-indigo-600 py-4 text-sm font-bold text-white shadow-xl shadow-indigo-100 transition-all hover:bg-indigo-700 active:scale-[0.98]"
            >
                Confirm & Record
            </button>
        </div>

    @else
        <div class="space-y-7 animate-in fade-in duration-500">
            <div class="text-center">
                <h3 class="text-xl font-extrabold text-slate-900">Add Expense</h3>
                <p class="text-xs text-slate-400 mt-1">Split with your group</p>
            </div>

            <form wire:submit.prevent="goToPreview" class="space-y-6 pb-20">

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
                    <div class="space-y-6 animate-in slide-in-from-top-4 duration-300">

                        <!-- Description with category icon -->
                        <div class="space-y-2.5">
                            <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">What was it for?</label>
                            <div class="relative">
                                <!-- Category icon badge -->
                                <div class="absolute left-4 top-1/2 -translate-y-1/2 flex h-8 w-8 items-center justify-center rounded-xl bg-indigo-50 text-indigo-600 transition-all">
                                    <x-icon :name="$category" class="w-4 h-4" stroke-width="2" />
                                </div>
                                <input
                                    type="text"
                                    wire:model.live="description"
                                    placeholder="e.g. Dinner, Grocery, Rent..."
                                    class="w-full rounded-2xl border border-slate-200 bg-white pl-14 pr-5 py-3.5 text-sm font-medium placeholder-slate-400 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-sm"
                                >
                            </div>
                            @if($category !== 'receipt')
                                <p class="px-1 text-[11px] font-medium text-indigo-500">
                                    Detected category — icon auto-set
                                </p>
                            @endif
                            @error('description') <p class="text-[11px] font-medium text-rose-500 px-1">{{ $message }}</p> @enderror
                        </div>

                        <!-- Amount -->
                        <div class="relative">
                            <span class="absolute left-5 top-1/2 -translate-y-1/2 text-lg font-bold text-slate-300 pointer-events-none">RS</span>
                            <input
                                type="number"
                                step="0.01"
                                wire:model.live="amount"
                                placeholder="0.00"
                                class="w-full rounded-[1.75rem] border border-slate-200 bg-white pl-14 pr-6 py-5 text-3xl font-black focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all shadow-sm"
                            >
                        </div>
                        @error('amount') <p class="text-[11px] font-medium text-rose-500 px-1">{{ $message }}</p> @enderror

                        <!-- Paid By -->
                        <div class="space-y-2.5">
                            <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">Paid By</label>
                            <div class="flex flex-wrap gap-2">
                                @foreach($members as $m)
                                    <button
                                        type="button"
                                        wire:click="$set('paidBy', {{ $m->id }})"
                                        class="flex items-center gap-2 rounded-full border-2 px-4 py-2 text-xs font-semibold transition-all {{ $paidBy == $m->id ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm' : 'border-slate-100 bg-white text-slate-600 hover:border-slate-200' }}"
                                    >
                                        <span class="flex h-4 w-4 items-center justify-center rounded-full bg-slate-200 text-[8px] font-black">
                                            {{ strtoupper(substr($m->name, 0, 1)) }}
                                        </span>
                                        {{ $m->name }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <!-- Split Strategy -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <label class="block text-[11px] font-bold uppercase tracking-widest text-slate-400">Split</label>
                                <div class="flex bg-slate-100 p-1 rounded-xl">
                                    <button type="button" wire:click="$set('splitType', 'equal')"
                                        class="px-4 py-1.5 text-[10px] font-bold uppercase rounded-lg transition-all {{ $splitType === 'equal' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-400' }}">
                                        Equal
                                    </button>
                                    <button type="button" wire:click="$set('splitType', 'custom')"
                                        class="px-4 py-1.5 text-[10px] font-bold uppercase rounded-lg transition-all {{ $splitType === 'custom' ? 'bg-white text-indigo-600 shadow-sm' : 'text-slate-400' }}">
                                        Custom
                                    </button>
                                </div>
                            </div>

                            @if($splitType === 'equal')
                                <div class="rounded-[1.75rem] bg-white border border-slate-100 shadow-sm p-2 grid grid-cols-2 gap-1">
                                    @foreach($members as $m)
                                        <label class="flex items-center gap-3 rounded-2xl px-4 py-3 cursor-pointer transition-colors {{ in_array($m->id, $participants) ? 'bg-indigo-50/60' : '' }}">
                                            <input type="checkbox" wire:model.live="participants" value="{{ $m->id }}"
                                                class="h-5 w-5 rounded-lg border-slate-200 text-indigo-600 focus:ring-0">
                                            <span class="text-xs font-semibold text-slate-700">{{ $m->name }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            @else
                                <div class="space-y-2">
                                    @foreach($members as $m)
                                        <div class="flex items-center justify-between rounded-2xl bg-white border border-slate-100 p-4 shadow-sm">
                                            <div class="flex items-center gap-3">
                                                <input type="checkbox" wire:model.live="participants" value="{{ $m->id }}"
                                                    class="h-5 w-5 rounded-lg border-slate-200 text-indigo-600 focus:ring-0">
                                                <span class="text-xs font-semibold text-slate-700">{{ $m->name }}</span>
                                            </div>
                                            @if(in_array($m->id, $participants))
                                                <div class="relative w-28">
                                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[10px] font-bold text-slate-300">RS</span>
                                                    <input type="number" wire:model.live="customAmounts.{{ $m->id }}"
                                                        class="w-full rounded-xl border border-slate-100 bg-slate-50 pl-8 pr-3 py-2 text-xs font-bold focus:border-indigo-500 focus:ring-0 outline-none"
                                                        placeholder="0.00">
                                                </div>
                                            @endif
                                        </div>
                                    @endforeach
                                    @error('custom_total') <p class="text-[11px] font-medium text-rose-500 px-1">{{ $message }}</p> @enderror
                                </div>
                            @endif
                        </div>

                        <button type="submit"
                            class="w-full rounded-2xl bg-indigo-600 py-4 text-sm font-bold text-white shadow-xl shadow-indigo-100 transition-all hover:bg-indigo-700 active:scale-[0.98]">
                            Preview Split
                        </button>
                    </div>
                @endif
            </form>
        </div>
    @endif
</div>
