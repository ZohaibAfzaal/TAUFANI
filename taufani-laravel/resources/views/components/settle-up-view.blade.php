<?php

use Livewire\Volt\Component;
use App\Models\Settlement;
use App\Models\User;
use App\Services\GroupContextService;
use App\Services\BalanceCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public ?int $activeGroupId = null;
    public ?int $fromUserId = null;
    public ?int $toUserId = null;
    public float $amount = 0;
    public string $note = '';
    public string $date = '';
    public bool $showForm = false;
    public string $successMessage = '';

    public function mount(GroupContextService $service): void
    {
        $this->activeGroupId = $service->getActiveGroup()?->id;
        $this->date = date('Y-m-d');
    }

    public function with(GroupContextService $service, BalanceCalculator $calculator): array
    {
        if (!$this->activeGroupId) {
            return [
                'activeGroup' => null,
                'members' => collect(),
                'suggestedSettlements' => [],
                'recentSettlements' => collect(),
            ];
        }

        $user = Auth::user();
        $activeGroup = $service->getActiveGroup($user);

        if (!$activeGroup) {
            return ['activeGroup' => null, 'members' => collect(), 'suggestedSettlements' => [], 'recentSettlements' => collect()];
        }

        $members = $activeGroup->members()->orderBy('name')->get();

        $balances = $calculator->calculate(collect([$this->activeGroupId]));
        $balances = $calculator->withUsers($balances);

        // Recent settlements
        $recentSettlements = Settlement::where('group_id', $this->activeGroupId)
            ->with(['from', 'to'])
            ->latest()
            ->limit(10)
            ->get();

        return [
            'activeGroup' => $activeGroup,
            'members' => $members,
            'suggestedSettlements' => array_slice($balances, 0, 5),
            'recentSettlements' => $recentSettlements,
        ];
    }

    public function recordSettlement()
    {
        $this->validate([
            'fromUserId' => 'required|exists:users,id',
            'toUserId' => 'required|exists:users,id|different:fromUserId',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
        ]);

        Settlement::create([
            'group_id' => $this->activeGroupId,
            'from_id' => $this->fromUserId,
            'to_id' => $this->toUserId,
            'amount' => $this->amount,
            'date' => $this->date,
            'note' => $this->note,
        ]);

        $this->successMessage = 'Settlement recorded successfully!';
        $this->reset(['fromUserId', 'toUserId', 'amount', 'note', 'showForm']);
        $this->date = date('Y-m-d');
    }

    public function useSuggestion($fromId, $toId, $amount)
    {
        $this->fromUserId = $fromId;
        $this->toUserId = $toId;
        $this->amount = $amount;
        $this->showForm = true;
    }
};
?>

<div>
@if(!$activeGroup)
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-10 text-center">
        <p class="text-sm font-medium text-slate-400">No group selected</p>
    </div>
@else
    <div class="space-y-4">
        {{-- Header --}}
        <div class="px-1">
            <h1 class="text-2xl font-extrabold text-slate-900">Settle Up</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $activeGroup->name }} · Record debt payments</p>
        </div>

        {{-- Success message --}}
        @if($successMessage)
            <div x-data="{show: true}" x-init="setTimeout(() => show = false, 3000)" x-show="show" x-transition
                 class="flex items-center gap-3 rounded-2xl bg-emerald-50 border border-emerald-100 px-5 py-4">
                <x-icon name="circle-check" class="w-5 h-5 text-emerald-500 shrink-0" stroke-width="2.5" />
                <p class="text-sm font-semibold text-emerald-700">{{ $successMessage }}</p>
            </div>
        @endif

        {{-- Quick suggestions --}}
        @if(count($suggestedSettlements) > 0)
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Suggested Settlements</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Tap Use to pre-fill the form</p>
                </div>
                <div class="divide-y divide-slate-50">
                    @foreach($suggestedSettlements as $settlement)
                        <div class="flex items-center justify-between px-5 py-4">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-sm font-semibold text-slate-900 truncate">{{ $settlement['fromUser']->name }}</span>
                                <x-icon name="arrow-right" class="w-4 h-4 text-slate-300 shrink-0" stroke-width="2.5" />
                                <span class="text-sm font-semibold text-slate-900 truncate">{{ $settlement['toUser']->name }}</span>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="text-sm font-bold text-slate-900">RS{{ number_format($settlement['amount'], 2) }}</span>
                                <button wire:click="useSuggestion({{ $settlement['from'] }}, {{ $settlement['to'] }}, {{ $settlement['amount'] }})"
                                        class="rounded-xl bg-indigo-50 border border-indigo-100 px-3 py-1.5 text-xs font-bold text-indigo-600 hover:bg-indigo-100 transition-colors">
                                    Use
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Settlement form --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
            <button @click="$wire.showForm = !$wire.showForm"
                    class="w-full flex items-center justify-between px-5 py-4 border-b border-slate-100 hover:bg-slate-50/50 transition-colors">
                <h3 class="text-sm font-bold text-slate-900">{{ $showForm ? 'Hide Form' : 'Record Settlement' }}</h3>
                <x-icon name="chevron-down" class="w-4 h-4 text-slate-400 transition-transform duration-200" x-bind:class="$wire.showForm ? 'rotate-180' : ''" stroke-width="2.5" />
            </button>

            @if($showForm)
                <form wire:submit="recordSettlement" class="divide-y divide-slate-100">
                    <div class="p-5 space-y-1.5">
                        <x-input-label value="From (who pays)" />
                        <select wire:model="fromUserId" required
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            <option value="">Select person…</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                        @error('fromUserId') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="p-5 space-y-1.5">
                        <x-input-label value="To (who receives)" />
                        <select wire:model="toUserId" required
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm font-medium text-slate-900 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            <option value="">Select person…</option>
                            @foreach($members as $member)
                                <option value="{{ $member->id }}">{{ $member->name }}</option>
                            @endforeach
                        </select>
                        @error('toUserId') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="p-5 space-y-1.5">
                        <x-input-label value="Amount (RS)" />
                        <x-text-input wire:model="amount" type="number" step="0.01" min="0.01" required />
                        @error('amount') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="p-5 space-y-1.5">
                        <x-input-label value="Date" />
                        <x-text-input wire:model="date" type="date" required />
                        @error('date') <p class="text-xs text-rose-500">{{ $message }}</p> @enderror
                    </div>

                    <div class="p-5 space-y-1.5">
                        <x-input-label value="Note (optional)" />
                        <x-text-input wire:model="note" type="text" placeholder="e.g. Cash payment" />
                    </div>

                    <div class="flex gap-3 p-5">
                        <x-primary-button>Record Settlement</x-primary-button>
                        <button type="button" @click="$wire.showForm = false"
                                class="flex-1 rounded-2xl border border-slate-200 py-3.5 text-sm font-bold text-slate-700 hover:bg-slate-50 transition-all active:scale-[0.98]">
                            Cancel
                        </button>
                    </div>
                </form>
            @endif
        </div>

        {{-- Recent settlements --}}
        @if($recentSettlements->count() > 0)
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Recent Settlements</h3>
                </div>
                <div class="divide-y divide-slate-50">
                    @foreach($recentSettlements as $settlement)
                        <div class="flex items-center justify-between px-5 py-4 hover:bg-slate-50/50 transition-colors">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-900 truncate">{{ $settlement->from->name }}</span>
                                    <x-icon name="arrow-right" class="w-4 h-4 text-slate-300 shrink-0" stroke-width="2.5" />
                                    <span class="text-sm font-semibold text-slate-900 truncate">{{ $settlement->to->name }}</span>
                                </div>
                                <div class="mt-0.5 flex items-center gap-1.5 text-xs text-slate-400">
                                    <span>{{ $settlement->date->format('M d, Y') }}</span>
                                    @if($settlement->note)
                                        <span>·</span>
                                        <span>{{ $settlement->note }}</span>
                                    @endif
                                </div>
                            </div>
                            <p class="ml-4 shrink-0 text-sm font-bold text-slate-900">RS{{ number_format($settlement->amount, 2) }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm py-12 text-center">
                <x-icon name="arrow-right-left" class="w-8 h-8 text-slate-200 mx-auto mb-2" stroke-width="1.5" />
                <p class="text-sm font-medium text-slate-400">No settlements recorded yet</p>
            </div>
        @endif
    </div>
@endif
</div>
