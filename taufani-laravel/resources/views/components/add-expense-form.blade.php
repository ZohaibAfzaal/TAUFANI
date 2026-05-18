<?php

use Livewire\Volt\Component;
use App\Mail\ExpenseAdded;
use App\Models\Group;
use App\Models\Expense;
use App\Models\User;
use App\Services\GroupContextService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $activeGroupId = null;
    public string $description = '';
    public string $category = 'receipt';
    public float $amount = 0;
    public string $paidBy = '';
    public array $participants = [];
    public string $splitType = 'equal';
    public array $customAmounts = [];
    public string $date = '';
    public string $step = 'input';
    public bool $showSuccess = false;

    protected array $categoryMap = [
        'shopping-cart'  => ['grocery', 'groceries', 'sabzi', 'vegetable', 'fruit', 'chicken', 'meat', 'ration', 'kiryana', 'market', 'supermarket', 'bazar', 'bazaar'],
        'utensils'       => ['dinner', 'lunch', 'breakfast', 'food', 'eat', 'meal', 'biryani', 'pizza', 'burger', 'shawarma', 'dine', 'dining', 'restaurant', 'cafe', 'hotel', 'snack', 'bbq'],
        'coffee'         => ['coffee', 'chai', 'tea', 'drink', 'juice'],
        'car'            => ['uber', 'careem', 'taxi', 'transport', 'fuel', 'petrol', 'bus', 'train', 'rickshaw', 'ride', 'vehicle', 'parking'],
        'plane'          => ['flight', 'travel', 'trip', 'tour', 'airport', 'hotel booking', 'visa'],
        'droplets'       => ['water', 'paani', 'drinking water', 'mineral water'],
        'zap'            => ['electricity', 'electric', 'bijli', 'wapda', 'power', 'generator'],
        'flame'          => ['gas', 'sui gas', 'lpg', 'cylinder'],
        'home'           => ['rent', 'house', 'flat', 'apartment', 'room', 'hostel', 'maintenance', 'repair'],
        'wifi'           => ['internet', 'wifi', 'broadband', 'data', 'sim', 'mobile', 'phone bill'],
        'heart-pulse'    => ['medicine', 'medical', 'doctor', 'hospital', 'pharmacy', 'health', 'dawai', 'clinic'],
        'graduation-cap' => ['fee', 'fees', 'school', 'college', 'university', 'course', 'tuition', 'study', 'book', 'books', 'stationery'],
        'film'           => ['movie', 'cinema', 'netflix', 'youtube', 'streaming', 'entertainment', 'show'],
        'music'          => ['music', 'spotify', 'concert', 'party'],
        'shirt'          => ['clothes', 'clothing', 'shirt', 'shoes', 'shopping', 'dress', 'fashion'],
        'smartphone'     => ['phone', 'laptop', 'gadget', 'tech', 'apple', 'samsung', 'charger', 'accessories'],
        'briefcase'      => ['office', 'work', 'business', 'stationary'],
        'trophy'         => ['sports', 'game', 'gym', 'cricket', 'football', 'membership'],
    ];

    // ─── shared context injected into every log entry ─────────────────────────
    private function logCtx(array $extra = []): array
    {
        return array_merge([
            'user_id'   => Auth::id(),
            'group_id'  => $this->activeGroupId,
            'step'      => $this->step,
        ], $extra);
    }

    // ─── lifecycle ────────────────────────────────────────────────────────────

    public function mount(GroupContextService $service): void
    {
        $group = $service->getActiveGroup();
        $this->activeGroupId = $group?->id;
        $this->date  = date('Y-m-d');
        $this->paidBy = (string) Auth::id();

        if ($group) {
            $this->participants = $group->members()
                ->pluck('users.id')
                ->map(fn($id) => (string) $id)
                ->toArray();

            // Log::info('[AddExpense] mounted', $this->logCtx([
            //     'group_name'      => $group->name,
            //     'members_loaded'  => count($this->participants),
            //     'default_paid_by' => $this->paidBy,
            // ]));
        } else {
            // Log::warning('[AddExpense] mounted with no active group', $this->logCtx());
        }
    }

    public function with(): array
    {
        if (!$this->activeGroupId) {
            // Log::warning('[AddExpense] with() — no activeGroupId, returning empty state', $this->logCtx());
            return ['activeGroup' => null, 'members' => collect()];
        }

        $activeGroup = Group::with('members')->find($this->activeGroupId);
        $members     = $activeGroup?->members ?? collect();

        if (!$activeGroup) {
            // Log::warning('[AddExpense] with() — group not found in DB', $this->logCtx());
        }

        return ['activeGroup' => $activeGroup, 'members' => $members];
    }

    // ─── user interactions ────────────────────────────────────────────────────

    public function updatedDescription(string $value): void
    {
        $lower    = strtolower($value);
        $previous = $this->category;

        foreach ($this->categoryMap as $icon => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($lower, $kw)) {
                    $this->category = $icon;

                    if ($previous !== $icon) {
                        // Log::info('[AddExpense] category auto-detected', $this->logCtx([
                        //     'description' => $value,
                        //     'matched_kw'  => $kw,
                        //     'category'    => $icon,
                        //     'previous'    => $previous,
                        // ]));
                    }

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
            $action = 'removed';
        } else {
            $this->participants[] = $id;
            $action = 'added';
        }

        // Log::info('[AddExpense] participant toggled', $this->logCtx([
        //     'member_id'         => $memberId,
        //     'action'            => $action,
        //     'participants_now'  => $this->participants,
        // ]));
    }

    public function goToPreview(): void
    {
        // Log::info('[AddExpense] goToPreview — validating', $this->logCtx([
        //     'description'  => $this->description,
        //     'amount'       => $this->amount,
        //     'paid_by'      => $this->paidBy,
        //     'participants' => $this->participants,
        //     'split_type'   => $this->splitType,
        // ]));

        try {
            $this->validate([
                'description'   => 'required|min:3',
                'amount'        => 'required|numeric|min:0.01',
                'activeGroupId' => 'required|exists:groups,id',
                'paidBy'        => 'required|exists:users,id',
                'participants'  => 'required|array|min:1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log::warning('[AddExpense] goToPreview — validation failed', $this->logCtx([
            //     'errors' => $e->errors(),
            // ]));
            throw $e;
        }

        if ($this->splitType === 'custom') {
            $total = array_sum($this->customAmounts);
            if (abs($total - $this->amount) > 0.01) {
                // Log::warning('[AddExpense] goToPreview — custom split mismatch', $this->logCtx([
                //     'expected' => $this->amount,
                //     'got'      => $total,
                //     'diff'     => abs($total - $this->amount),
                // ]));
                $this->addError('customAmounts', "Custom amounts total (RS{$total}) must equal RS{$this->amount}");
                return;
            }
        }

        // Log::info('[AddExpense] goToPreview — passed, showing preview', $this->logCtx());
        $this->step = 'preview';
    }

    public function submitExpense(): void
    {       
        // Log::info('[AddExpense] submitExpense — validating before DB insert', $this->logCtx());

        try {
            $this->validate([
                'description'   => 'required|min:3',
                'amount'        => 'required|numeric|min:0.01',
                'activeGroupId' => 'required|exists:groups,id',
                'paidBy'        => 'required|exists:users,id',
                'participants'  => 'required|array|min:1',
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            // Log::warning('[AddExpense] submitExpense — validation failed', $this->logCtx([
            //     'errors' => $e->errors(),
            // ]));
            throw $e;
        }

        try {
            $expense = Expense::create([
                'group_id'    => $this->activeGroupId,
                'description' => $this->description,
                'category'    => $this->category,
                'amount'      => $this->amount,
                'paid_by'     => $this->paidBy,
                'created_by'  => Auth::id(),
                'split_type'  => $this->splitType,
                'date'        => $this->date,
            ]);

            // Log::info('[AddExpense] submitExpense — expense row created', $this->logCtx([
            //     'expense_id' => $expense->id,
            // ]));

            foreach ($this->participants as $participantId) {
                $customAmount = ($this->splitType === 'custom' && isset($this->customAmounts[$participantId]))
                    ? $this->customAmounts[$participantId]
                    : null;
                $expense->participants()->attach($participantId, ['amount' => $customAmount]);

                // Log::info('[AddExpense] submitExpense — participant attached', $this->logCtx([
                //     'expense_id'    => $expense->id,
                //     'participant_id' => $participantId,
                //     'custom_amount' => $customAmount,
                // ]));
            }
        } catch (\Throwable $e) {
            Log::error('[AddExpense] submitExpense — DB error', $this->logCtx([
                'error'   => $e->getMessage(),
                'file'    => $e->getFile() . ':' . $e->getLine(),
            ]));
            throw $e;
        }

        // Log::info('[AddExpense] submitExpense — completed successfully', $this->logCtx([
        //     'expense_id'       => $expense->id,
        //     'total_amount'     => $this->amount,
        //     'participant_count' => count($this->participants),
        // ]));

        // Notify only the expense participants, excluding the person who just added it
        $expense->load(['group', 'payer', 'participants']);
        $members = $expense->participants->filter(fn ($m) => $m->id !== Auth::id());
        // Log::info('[Mail] ExpenseAdded — queuing notifications', [
        //     'expense_id'   => $expense->id,
        //     'group'        => $expense->group->name,
        //     'recipient_count' => $members->count(),
        //     'recipients'   => $members->pluck('email')->all(),
        // ]);
        try {
            $members->each(
                fn ($member) => Mail::to($member)->send(new ExpenseAdded($expense, $member))
            );
            // Log::info('[Mail] ExpenseAdded — sent successfully', ['expense_id' => $expense->id]);
        } catch (\Throwable $e) {
            Log::error('[Mail] ExpenseAdded — failed', [
                'expense_id' => $expense->id,
                'error'      => $e->getMessage(),
            ]);
        }

        session()->flash('sweetalert', [
            'toast'             => true,
            'icon'              => 'success',
            'title'             => 'Expense added!',
            'position'          => 'top-end',
            'timer'             => 2500,
            'timerProgressBar'  => true,
            'showConfirmButton' => false,
        ]);

        $this->redirectRoute('expenses', navigate: true);
    }

    public function goBack(): void
    {
        // Log::info('[AddExpense] goBack — returned to input step', $this->logCtx());
        $this->step = 'input';
    }
};
?>

<div>
@if(!$activeGroup)
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-10 text-center">
        <x-icon name="receipt" class="w-8 h-8 text-slate-200 mx-auto mb-2" stroke-width="1.5" />
        <p class="text-sm font-medium text-slate-400">No group selected</p>
    </div>

@elseif($step === 'input')
    <div class="space-y-4 pb-6">

        {{-- Header --}}
        <div class="px-1">
            <h1 class="text-2xl font-extrabold text-slate-900">Add Expense</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $activeGroup->name }}</p>
        </div>

        {{-- Success banner --}}
        @if($showSuccess)
            <div x-data="{show: true}" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition
                 class="flex items-center gap-3 rounded-2xl bg-emerald-50 border border-emerald-100 px-5 py-4">
                <x-icon name="circle-check" class="w-5 h-5 text-emerald-500 shrink-0" stroke-width="2.5" />
                <p class="text-sm font-semibold text-emerald-700">Expense added successfully!</p>
            </div>
        @endif

        {{-- ① Description + Category ─────────────────────────────────── --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5 space-y-3">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">WHAT WAS IT FOR?</p>
            <div class="flex items-center gap-3">
                {{-- Icon updates live as user types --}}
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
                    'receipt'        => 'General',
                    'shopping-cart'  => 'Groceries',
                    'utensils'       => 'Food',
                    'coffee'         => 'Drinks',
                    'car'            => 'Transport',
                    'plane'          => 'Travel',
                    'home'           => 'Housing',
                    'wifi'           => 'Internet',
                    'zap'            => 'Electric',
                    'flame'          => 'Gas',
                    'droplets'       => 'Water',
                    'heart-pulse'    => 'Health',
                    'graduation-cap' => 'Education',
                    'film'           => 'Fun',
                    'shirt'          => 'Shopping',
                    'music'          => 'Music',
                    'smartphone'     => 'Tech',
                    'trophy'         => 'Sports',
                ];
            @endphp
            <div class="flex gap-2 overflow-x-auto pb-1 -mx-1 px-1" style="scrollbar-width:none">
                @foreach($categoryOptions as $icon => $label)
                    <button type="button"
                            wire:click="$set('category', '{{ $icon }}')"
                            class="flex-shrink-0 flex flex-col items-center gap-1.5 rounded-2xl border px-3 py-2.5 transition-all
                                   {{ $category === $icon
                                        ? 'border-indigo-500 bg-indigo-50 shadow-sm shadow-indigo-100'
                                        : 'border-slate-200 bg-white hover:border-slate-300' }}">
                        <x-icon name="{{ $icon }}" class="w-4 h-4 {{ $category === $icon ? 'text-indigo-600' : 'text-slate-400' }}" stroke-width="2" />
                        <span class="text-[9px] font-bold {{ $category === $icon ? 'text-indigo-700' : 'text-slate-500' }}">{{ $label }}</span>
                    </button>
                @endforeach
            </div>
        </div>

        {{-- ② Amount ───────────────────────────────────────────────────── --}}
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

        {{-- ③ Paid By — wrapped pill grid ─────────────────────────────── --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">PAID BY</p>
            <div class="flex flex-wrap gap-2">
                @foreach($members as $member)
                    @php $isSelected = $paidBy == $member->id; @endphp
                    <button type="button"
                            wire:click="$set('paidBy', '{{ $member->id }}')"
                            class="flex items-center gap-2 rounded-2xl border px-3.5 py-2.5 text-xs font-bold transition-all
                                   {{ $isSelected
                                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700 shadow-sm shadow-indigo-100'
                                        : 'border-slate-200 bg-slate-50 text-slate-600 hover:border-slate-300 hover:bg-white' }}">
                        <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode($member->name) }}"
                             alt="{{ $member->name }}"
                             class="h-6 w-6 rounded-full object-cover {{ $isSelected ? 'ring-2 ring-indigo-400' : '' }}">
                        {{ $member->name }}
                    </button>
                @endforeach
            </div>
            @error('paidBy') <p class="text-xs text-rose-500 mt-2">{{ $message }}</p> @enderror
        </div>

        {{-- ④ Split ────────────────────────────────────────────────────── --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5 space-y-4">

            {{-- Toggle --}}
            <div class="flex items-center gap-3">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 flex-1">SPLIT</p>
                <div class="flex rounded-2xl border border-slate-200 bg-slate-50 p-1 gap-1">
                    <button type="button"
                            wire:click="$set('splitType', 'equal')"
                            class="rounded-xl px-5 py-1.5 text-xs font-bold transition-all
                                   {{ $splitType === 'equal' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                        EQUAL
                    </button>
                    <button type="button"
                            wire:click="$set('splitType', 'custom')"
                            class="rounded-xl px-5 py-1.5 text-xs font-bold transition-all
                                   {{ $splitType === 'custom' ? 'bg-indigo-600 text-white shadow-sm' : 'text-slate-500 hover:text-slate-700' }}">
                        CUSTOM
                    </button>
                </div>
            </div>

            {{-- Equal: 2-col compact grid --}}
            @if($splitType === 'equal')
                <div class="grid grid-cols-2 gap-2">
                    @foreach($members as $member)
                        @php $checked = in_array((string)$member->id, $participants); @endphp
                        <button type="button"
                                wire:click="toggleParticipant({{ $member->id }})"
                                class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-left transition-all
                                       {{ $checked
                                            ? 'border-indigo-200 bg-indigo-50'
                                            : 'border-slate-100 bg-slate-50/50 hover:border-slate-200 hover:bg-white' }}">
                            {{-- Circle check --}}
                            <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all
                                        {{ $checked ? 'bg-indigo-600' : 'border-2 border-slate-300 bg-white' }}">
                                @if($checked)
                                    <x-icon name="check" class="w-3.5 h-3.5 text-white" stroke-width="3" />
                                @endif
                            </div>
                            <span class="flex-1 text-sm font-semibold truncate {{ $checked ? 'text-indigo-700' : 'text-slate-600' }}">
                                {{ $member->name }}
                            </span>
                        </button>
                    @endforeach
                </div>

            {{-- Custom: full-width rows with RS amount input --}}
            @else
                <div class="space-y-2">
                    @foreach($members as $member)
                        @php $checked = in_array((string)$member->id, $participants); @endphp
                        <div class="flex items-center rounded-2xl border transition-all
                                    {{ $checked ? 'border-indigo-200 bg-indigo-50' : 'border-slate-100 bg-white' }}">
                            <button type="button"
                                    wire:click="toggleParticipant({{ $member->id }})"
                                    class="flex flex-1 items-center gap-3 px-4 py-3.5 min-w-0 text-left">
                                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full transition-all
                                            {{ $checked ? 'bg-indigo-600' : 'border-2 border-slate-300 bg-white' }}">
                                    @if($checked)
                                        <x-icon name="check" class="w-3.5 h-3.5 text-white" stroke-width="3" />
                                    @endif
                                </div>
                                <span class="text-sm font-semibold {{ $checked ? 'text-indigo-700' : 'text-slate-600' }}">
                                    {{ $member->name }}
                                </span>
                            </button>
                            {{-- RS amount input --}}
                            <div class="flex shrink-0 items-center gap-1 border-l border-slate-200 px-4 py-3.5">
                                <span class="text-xs font-black text-slate-400 leading-none">RS</span>
                                <input type="number"
                                       step="0.01" min="0"
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

        {{-- ⑤ Date ────────────────────────────────────────────────────── --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-5">
            <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-2">DATE</p>
            <x-text-input wire:model="date" type="date" />
        </div>

        {{-- Submit ─────────────────────────────────────────────────────── --}}
        <button wire:click="goToPreview"
                class="w-full rounded-2xl bg-indigo-600 px-6 py-4 text-sm font-bold text-white shadow-lg shadow-indigo-200 transition-all hover:bg-indigo-700 hover:shadow-indigo-300 active:scale-[0.98]">
            Review Expense →
        </button>
    </div>

@else
    {{-- ── Preview step ───────────────────────────────────────────────── --}}
    <div class="space-y-4">
        <div class="flex items-center gap-3 px-1">
            <button wire:click="goBack"
                    class="flex h-9 w-9 items-center justify-center rounded-2xl bg-white border border-slate-100 shadow-sm text-slate-500 hover:text-slate-900 transition-colors">
                <x-icon name="chevron-left" class="w-5 h-5" stroke-width="2.5" />
            </button>
            <h1 class="text-2xl font-extrabold text-slate-900">Review</h1>
        </div>

        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
            {{-- Hero --}}
            <div class="relative overflow-hidden bg-gradient-to-br from-slate-900 to-indigo-950 p-6 text-white">
                <div class="absolute -right-6 -top-6 h-24 w-24 rounded-full bg-indigo-500/20 blur-2xl pointer-events-none"></div>
                <div class="flex items-center gap-3 mb-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-white/10">
                        <x-icon name="{{ $category }}" class="w-5 h-5 text-white" stroke-width="2" />
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Total Amount</p>
                        <p class="text-sm font-semibold text-slate-300">{{ $description }}</p>
                    </div>
                </div>
                <p class="text-4xl font-black">Rs{{ number_format($amount, 2) }}</p>
            </div>

            {{-- Detail rows --}}
            <div class="divide-y divide-slate-50">
                <div class="flex items-center justify-between px-5 py-3.5">
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-400">Paid by</span>
                    <span class="text-sm font-semibold text-slate-900">{{ collect($members)->firstWhere('id', $paidBy)?->name }}</span>
                </div>
                <div class="flex items-center justify-between px-5 py-3.5">
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-400">Split</span>
                    <span class="text-sm font-semibold text-slate-900">{{ ucfirst($splitType) }}</span>
                </div>
                <div class="flex items-center justify-between px-5 py-3.5">
                    <span class="text-xs font-bold uppercase tracking-widest text-slate-400">Date</span>
                    <span class="text-sm font-semibold text-slate-900">{{ date('M d, Y', strtotime($date)) }}</span>
                </div>
            </div>

            {{-- Split breakdown --}}
            @php $shareCount = count($participants); @endphp
            <div class="px-5 py-4 border-t border-slate-100">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400 mb-3">Split Among ({{ $shareCount }})</p>
                <div class="space-y-3">
                    @foreach($participants as $participantId)
                        @php
                            $pm    = collect($members)->firstWhere('id', $participantId);
                            $share = $splitType === 'custom'
                                ? ($customAmounts[$participantId] ?? 0)
                                : ($shareCount > 0 ? $amount / $shareCount : 0);
                        @endphp
                        @if($pm)
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2.5">
                                    <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode($pm->name) }}"
                                         alt="{{ $pm->name }}"
                                         class="h-8 w-8 shrink-0 rounded-full bg-indigo-50 object-cover">
                                    <span class="text-sm font-semibold text-slate-700">{{ $pm->name }}</span>
                                </div>
                                <span class="text-sm font-bold text-slate-900">RS{{ number_format($share, 2) }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            </div>

            {{-- Actions --}}
            <div class="flex gap-3 px-5 pb-5">
                <button wire:click="goBack"
                        class="flex-1 rounded-2xl border border-slate-200 py-3.5 text-sm font-bold text-slate-700 hover:bg-slate-50 transition-all active:scale-[0.98]">
                    Back
                </button>
                <button wire:click="submitExpense"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-75 cursor-not-allowed"
                        wire:target="submitExpense"
                        class="flex-1 flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all active:scale-[0.98]">
                    <svg wire:loading wire:target="submitExpense" class="animate-spin h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    <span wire:loading.remove wire:target="submitExpense">Confirm & Save</span>
                    <span wire:loading wire:target="submitExpense">Saving…</span>
                </button>
            </div>
        </div>
    </div>
@endif
</div>
