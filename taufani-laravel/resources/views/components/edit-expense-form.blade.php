<?php

use Livewire\Volt\Component;
use App\Models\Expense;
use App\Models\Group;
use App\Services\GroupContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public int    $expenseId   = 0;
    public string $description = '';
    public string $category    = 'receipt';
    public float  $amount      = 0;
    public string $paidBy      = '';
    public array  $participants = [];
    public string $splitType   = 'equal';
    public array  $customAmounts = [];
    public string $date        = '';
    public ?int   $activeGroupId = null;

    protected array $categoryMap = [
        'shopping-cart'  => ['grocery','groceries','sabzi','vegetable','fruit','chicken','meat','ration','kiryana','market','supermarket','bazar','bazaar'],
        'utensils'       => ['dinner','lunch','breakfast','food','eat','meal','biryani','pizza','burger','shawarma','dine','dining','restaurant','cafe','hotel','snack','bbq'],
        'coffee'         => ['coffee','chai','tea','drink','juice'],
        'car'            => ['uber','careem','taxi','transport','fuel','petrol','bus','train','rickshaw','ride','vehicle','parking'],
        'plane'          => ['flight','travel','trip','tour','airport','hotel booking','visa'],
        'droplets'       => ['water','paani','drinking water','mineral water'],
        'zap'            => ['electricity','electric','bijli','wapda','power','generator'],
        'flame'          => ['gas','sui gas','lpg','cylinder'],
        'home'           => ['rent','house','flat','apartment','room','hostel','maintenance','repair'],
        'wifi'           => ['internet','wifi','broadband','data','sim','mobile','phone bill'],
        'heart-pulse'    => ['medicine','medical','doctor','hospital','pharmacy','health','dawai','clinic'],
        'graduation-cap' => ['fee','fees','school','college','university','course','tuition','study','book','books','stationery'],
        'film'           => ['movie','cinema','netflix','youtube','streaming','entertainment','show'],
        'music'          => ['music','spotify','concert','party'],
        'shirt'          => ['clothes','clothing','shirt','shoes','shopping','dress','fashion'],
        'smartphone'     => ['phone','laptop','gadget','tech','apple','samsung','charger','accessories'],
        'briefcase'      => ['office','work','business','stationary'],
        'trophy'         => ['sports','game','gym','cricket','football','membership'],
    ];

    public function mount(Expense $expense): void
    {
        abort_unless(
            $expense->created_by === Auth::id() ||
            ($expense->created_by === null && $expense->paid_by === Auth::id()),
            403
        );

        $this->expenseId    = $expense->id;
        $this->activeGroupId = $expense->group_id;
        $this->description  = $expense->description;
        $this->category     = $expense->category ?? 'receipt';
        $this->amount       = (float) $expense->amount;
        $this->paidBy       = (string) $expense->paid_by;
        $this->splitType    = $expense->split_type;
        $this->date         = $expense->date->format('Y-m-d');

        $expense->load('participants');
        foreach ($expense->participants as $p) {
            $this->participants[] = (string) $p->id;
            if ($expense->split_type === 'custom' && $p->pivot->amount !== null) {
                $this->customAmounts[$p->id] = (float) $p->pivot->amount;
            }
        }
    }

    public function with(): array
    {
        $activeGroup = Group::with('members')->find($this->activeGroupId);
        return [
            'activeGroup' => $activeGroup,
            'members'     => $activeGroup?->members ?? collect(),
        ];
    }

    public function updatedDescription(string $value): void
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

    public function toggleParticipant(int $memberId): void
    {
        $id  = (string) $memberId;
        $key = array_search($id, $this->participants, true);
        if ($key !== false) {
            array_splice($this->participants, $key, 1);
        } else {
            $this->participants[] = $id;
        }
    }

    public function saveChanges(): void
    {
        $this->validate([
            'description'  => 'required|min:3',
            'amount'       => 'required|numeric|min:0.01',
            'paidBy'       => 'required|exists:users,id',
            'participants' => 'required|array|min:1',
        ]);

        if ($this->splitType === 'custom') {
            $total = array_sum($this->customAmounts);
            if (abs($total - $this->amount) > 0.01) {
                $this->addError('customAmounts', "Custom amounts total (RS{$total}) must equal RS{$this->amount}");
                return;
            }
        }

        $expense = Expense::findOrFail($this->expenseId);
        abort_unless(
            $expense->created_by === Auth::id() ||
            ($expense->created_by === null && $expense->paid_by === Auth::id()),
            403
        );

        $expense->update([
            'description' => $this->description,
            'category'    => $this->category,
            'amount'      => $this->amount,
            'paid_by'     => $this->paidBy,
            'split_type'  => $this->splitType,
            'date'        => $this->date,
        ]);

        $expense->participants()->detach();
        foreach ($this->participants as $participantId) {
            $customAmount = ($this->splitType === 'custom' && isset($this->customAmounts[$participantId]))
                ? $this->customAmounts[$participantId]
                : null;
            $expense->participants()->attach($participantId, ['amount' => $customAmount]);
        }

        session()->flash('sweetalert', [
            'toast'             => true,
            'icon'              => 'success',
            'title'             => 'Expense updated!',
            'position'          => 'top-end',
            'timer'             => 2500,
            'timerProgressBar'  => true,
            'showConfirmButton' => false,
        ]);

        $this->redirectRoute('expenses', navigate: true);
    }
};
?>

<div>
@if(!$activeGroup)
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-10 text-center">
        <x-icon name="receipt" class="w-8 h-8 text-slate-200 mx-auto mb-2" stroke-width="1.5" />
        <p class="text-sm font-medium text-slate-400">No group found</p>
    </div>
@else
<div class="space-y-4 pb-6">

    {{-- Header --}}
    <div class="flex items-center gap-3 px-1">
        <a href="{{ route('expenses') }}" wire:navigate
           class="flex h-9 w-9 items-center justify-center rounded-2xl bg-white border border-slate-100 shadow-sm text-slate-500 hover:text-slate-900 transition-colors">
            <x-icon name="chevron-left" class="w-5 h-5" stroke-width="2.5" />
        </a>
        <div>
            <h1 class="text-2xl font-extrabold text-slate-900">Edit Expense</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $activeGroup->name }}</p>
        </div>
    </div>

    {{-- ① Description + Category --}}
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5 space-y-3">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">WHAT WAS IT FOR?</p>
        <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 border border-indigo-100">
                <x-icon name="{{ $category }}" class="w-5 h-5 text-indigo-600" stroke-width="2" />
            </div>
            <input type="text"
                   wire:model.live.debounce.400ms="description"
                   placeholder="e.g. Dinner at restaurant"
                   class="flex-1 rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm font-semibold text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all" />
        </div>
        @error('description') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
        @php
            $categoryOptions = [
                'receipt'        => 'General',    'shopping-cart'  => 'Groceries',
                'utensils'       => 'Food',        'coffee'         => 'Drinks',
                'car'            => 'Transport',   'plane'          => 'Travel',
                'home'           => 'Housing',     'wifi'           => 'Internet',
                'zap'            => 'Electric',    'flame'          => 'Gas',
                'droplets'       => 'Water',       'heart-pulse'    => 'Health',
                'graduation-cap' => 'Education',   'film'           => 'Fun',
                'shirt'          => 'Shopping',    'music'          => 'Music',
                'smartphone'     => 'Tech',        'trophy'         => 'Sports',
            ];
        @endphp
        <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1" style="scrollbar-width:none">
            @foreach($categoryOptions as $icon => $label)
                <button type="button"
                        wire:click="$set('category', '{{ $icon }}')"
                        class="flex-shrink-0 flex flex-col items-center gap-1.5 rounded-2xl border px-3 py-2.5 transition-all
                               {{ $category === $icon ? 'border-indigo-500 bg-indigo-50 shadow-sm shadow-indigo-100' : 'border-slate-200 bg-white hover:border-slate-300' }}">
                    <x-icon name="{{ $icon }}" class="w-4 h-4 {{ $category === $icon ? 'text-indigo-600' : 'text-slate-400' }}" stroke-width="2" />
                    <span class="text-[9px] font-bold {{ $category === $icon ? 'text-indigo-700' : 'text-slate-500' }}">{{ $label }}</span>
                </button>
            @endforeach
        </div>
    </div>

    {{-- ② Amount --}}
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">AMOUNT</p>
        <div class="flex items-baseline gap-2">
            <span class="text-2xl font-black text-slate-300">RS</span>
            <input type="number"
                   wire:model.blur="amount"
                   step="0.01" min="0.01"
                   placeholder="0"
                   class="flex-1 bg-transparent text-4xl font-black text-slate-900 placeholder-slate-200 border-0 p-0 focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
        </div>
        @error('amount') <p class="text-xs text-rose-500 mt-2">{{ $message }}</p> @enderror
    </div>

    {{-- ③ Paid By --}}
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">PAID BY</p>
        <div class="flex flex-wrap gap-2">
            @foreach($members as $member)
                @php $isSelected = $paidBy == $member->id; @endphp
                <button type="button"
                        wire:click="$set('paidBy', '{{ $member->id }}')"
                        class="flex items-center gap-2 rounded-2xl border px-3.5 py-2.5 text-xs font-bold transition-all
                               {{ $isSelected ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm shadow-indigo-100' : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:bg-white' }}">
                    <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode($member->name) }}"
                         alt="{{ $member->name }}"
                         class="h-6 w-6 rounded-full object-cover {{ $isSelected ? 'ring-2 ring-indigo-400' : '' }}">
                    {{ $member->name }}
                </button>
            @endforeach
        </div>
        @error('paidBy') <p class="text-xs text-rose-500 mt-2">{{ $message }}</p> @enderror
    </div>

    {{-- ④ Split --}}
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5 space-y-4">
        <div class="flex items-center gap-3">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 flex-1">SPLIT</p>
            <div class="flex rounded-2xl border border-slate-200 bg-slate-50 p-1 gap-1">
                <button type="button" wire:click="$set('splitType', 'equal')"
                        class="rounded-xl px-5 py-1.5 text-xs font-bold transition-all {{ $splitType === 'equal' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    EQUAL
                </button>
                <button type="button" wire:click="$set('splitType', 'custom')"
                        class="rounded-xl px-5 py-1.5 text-xs font-bold transition-all {{ $splitType === 'custom' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                    CUSTOM
                </button>
            </div>
        </div>

        @if($splitType === 'equal')
            <div class="grid grid-cols-2 gap-2">
                @foreach($members as $member)
                    @php $checked = in_array((string)$member->id, $participants); @endphp
                    <button type="button" wire:click="toggleParticipant({{ $member->id }})"
                            class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-left transition-all {{ $checked ? 'border-indigo-200 bg-indigo-50' : 'border-slate-100 bg-slate-50/50 hover:border-slate-200 hover:bg-white' }}">
                        <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all {{ $checked ? 'bg-indigo-600' : 'border-2 border-slate-300 bg-white' }}">
                            @if($checked)<x-icon name="check" class="w-3.5 h-3.5 text-white" stroke-width="3" />@endif
                        </div>
                        <span class="flex-1 text-sm font-semibold truncate {{ $checked ? 'text-indigo-700' : 'text-slate-600' }}">
                            {{ $member->name }}
                        </span>
                    </button>
                @endforeach
            </div>
        @else
            <div class="space-y-2">
                @foreach($members as $member)
                    @php $checked = in_array((string)$member->id, $participants); @endphp
                    <div class="flex items-center rounded-2xl border transition-all {{ $checked ? 'border-indigo-200 bg-indigo-50' : 'border-slate-100 bg-white' }}">
                        <button type="button" wire:click="toggleParticipant({{ $member->id }})"
                                class="flex flex-1 items-center gap-3 px-4 py-3.5 min-w-0 text-left">
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all {{ $checked ? 'bg-indigo-600' : 'border-2 border-slate-300 bg-white' }}">
                                @if($checked)<x-icon name="check" class="w-3.5 h-3.5 text-white" stroke-width="3" />@endif
                            </div>
                            <span class="text-sm font-semibold {{ $checked ? 'text-indigo-700' : 'text-slate-600' }}">{{ $member->name }}</span>
                        </button>
                        <div class="flex shrink-0 items-center gap-1 border-l border-slate-200 px-4 py-3.5">
                            <span class="text-xs font-black text-slate-400 leading-none">RS</span>
                            <input type="number" step="0.01" min="0"
                                   wire:model.blur="customAmounts.{{ $member->id }}"
                                   placeholder="0"
                                   class="w-16 border-0 bg-transparent text-sm font-bold text-slate-900 placeholder-slate-300 focus:outline-none focus:ring-0 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none" />
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
        @error('participants')   <p class="text-xs text-rose-500 pt-1">{{ $message }}</p> @enderror
        @error('customAmounts') <p class="text-xs text-rose-500 pt-1">{{ $message }}</p> @enderror
    </div>

    {{-- ⑤ Date --}}
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5">
        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">DATE</p>
        <x-text-input wire:model="date" type="date" />
    </div>

    {{-- Save --}}
    <button wire:click="saveChanges"
            wire:loading.attr="disabled"
            wire:loading.class="opacity-75 cursor-not-allowed"
            wire:target="saveChanges"
            class="w-full flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-6 py-4 text-sm font-bold text-white shadow-lg shadow-indigo-200 transition-all hover:bg-indigo-700 hover:shadow-indigo-300 active:scale-[0.98]">
        <svg wire:loading wire:target="saveChanges" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
        </svg>
        <span wire:loading.remove wire:target="saveChanges">Save Changes</span>
        <span wire:loading wire:target="saveChanges">Saving…</span>
    </button>

</div>
@endif
</div>
