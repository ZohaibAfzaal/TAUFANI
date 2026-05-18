<?php

use Livewire\Volt\Component;
use App\Services\GroupContextService;
use App\Services\BalanceCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public string $debtFilterMember = '';
    public string $debtSortBy       = 'highest';

    public function with(GroupContextService $service, BalanceCalculator $calculator): array
    {
        $user = Auth::user();
        $activeGroup = $service->getActiveGroup($user);

        if (!$activeGroup) {
            return [
                'activeGroup'     => null,
                'balances'        => [],
                'filteredBalances'=> [],
                'myBalances'      => [],
                'memberBalances'  => [],
                'members'         => collect(),
                'user'            => $user,
            ];
        }

        $rawBalances = $calculator->calculate(collect([$activeGroup->id]));
        $balances    = $calculator->withUsers($rawBalances);

        // Balances filtered to only those involving the logged-in user
        $myRawBalances = $calculator->calculate(collect([$activeGroup->id]), $user->id);
        $myBalances    = $calculator->withUsers($myRawBalances);

        // ── Debt Details filters ──────────────────────────────────────────────
        $filtered = collect($balances);

        if ($this->debtFilterMember !== '') {
            $memberId = (int) $this->debtFilterMember;
            $filtered = $filtered->filter(
                fn ($b) => $b['from'] === $memberId || $b['to'] === $memberId
            );
        }

        $filtered = match ($this->debtSortBy) {
            'lowest'  => $filtered->sortBy('amount'),
            'highest' => $filtered->sortByDesc('amount'),
            default   => $filtered,
        };

        $filteredBalances = $filtered->values()->all();
        // ─────────────────────────────────────────────────────────────────────

        // Group by member
        $members = $activeGroup->members()->orderBy('name')->get();
        $memberBalances = [];

        foreach ($members as $member) {
            $memberOwes = collect($balances)->where('from', $member->id)->sum('amount');
            $memberOwed = collect($balances)->where('to', $member->id)->sum('amount');
            $netBalance = $memberOwed - $memberOwes;

            $memberBalances[$member->id] = [
                'member' => $member,
                'owes'   => $memberOwes,
                'owed'   => $memberOwed,
                'net'    => $netBalance,
                'status' => match (true) {
                    $netBalance > 0.01  => 'creditor',
                    $netBalance < -0.01 => 'debtor',
                    default             => 'settled',
                },
            ];
        }

        usort($memberBalances, function ($a, $b) {
            $statusOrder = ['debtor' => 0, 'creditor' => 1, 'settled' => 2];
            $diff = $statusOrder[$a['status']] - $statusOrder[$b['status']];
            return $diff !== 0 ? $diff : abs($b['net']) - abs($a['net']);
        });

        return [
            'activeGroup'     => $activeGroup,
            'balances'        => $balances,
            'filteredBalances'=> $filteredBalances,
            'myBalances'      => $myBalances,
            'memberBalances'  => $memberBalances,
            'members'         => $members,
            'user'            => $user,
        ];
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
            <h1 class="text-2xl font-extrabold text-slate-900">Balances</h1>
            <p class="text-xs text-slate-400 mt-0.5">{{ $activeGroup->name }} · Who owes whom</p>
        </div>

        {{-- Your personal balance summary --}}
        @if(count($myBalances) > 0)
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Your Balances</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Who you owe · Who owes you</p>
                </div>
                <div class="divide-y divide-slate-50">
                    @foreach($myBalances as $b)
                        @php
                            $youOwe = $b['from'] === Auth::id();
                            $other  = $youOwe ? $b['toUser'] : $b['fromUser'];
                            $rowCls = $youOwe ? 'bg-rose-50/40' : 'bg-emerald-50/40';
                            $amtCls = $youOwe ? 'text-rose-600' : 'text-emerald-600';
                            $label  = $youOwe ? 'You owe' : 'Owes you';
                            $pillCls = $youOwe ? 'bg-rose-100 text-rose-700' : 'bg-emerald-100 text-emerald-700';
                        @endphp
                        <div class="flex items-center justify-between px-5 py-4 {{ $rowCls }}">
                            <div class="flex items-center gap-3 min-w-0">
                                <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode($other->name) }}"
                                     alt="{{ $other->name }}"
                                     class="h-9 w-9 shrink-0 rounded-xl bg-indigo-50 object-cover">
                                <div class="min-w-0">
                                    <p class="text-sm font-bold text-slate-900 truncate">{{ $other->name }}</p>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $pillCls }}">{{ $label }}</span>
                                </div>
                            </div>
                            <span class="ml-3 shrink-0 text-base font-black {{ $amtCls }}">RS{{ number_format($b['amount'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        @else
            <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-6 text-center shadow-sm">
                <x-icon name="circle-check-big" class="w-8 h-8 text-emerald-500 mx-auto mb-2" stroke-width="2" />
                <p class="text-sm font-bold text-emerald-900">You're all settled up!</p>
                <p class="text-xs text-emerald-600 mt-0.5">No outstanding debts involving you</p>
            </div>
        @endif

        {{-- Group member balance cards --}}
        <div class="px-1 pt-2">
            <p class="text-xs font-bold uppercase tracking-widest text-slate-400">Group Overview</p>
        </div>
        <div class="space-y-3">
            @forelse($memberBalances as $data)
                @php
                    $cardCls = match($data['status']) {
                        'debtor'   => 'bg-rose-50 border-rose-100',
                        'creditor' => 'bg-emerald-50 border-emerald-100',
                        default    => 'bg-white border-slate-100',
                    };
                    $netCls = match($data['status']) {
                        'debtor'   => 'text-rose-600',
                        'creditor' => 'text-emerald-600',
                        default    => 'text-slate-400',
                    };
                    [$badgeCls, $badgeLabel] = match($data['status']) {
                        'debtor'   => ['text-rose-700 bg-rose-100', 'Owes'],
                        'creditor' => ['text-emerald-700 bg-emerald-100', 'Owed'],
                        default    => ['text-slate-500 bg-slate-100', 'Settled'],
                    };
                @endphp
                <div class="rounded-2xl border {{ $cardCls }} p-5 shadow-sm">
                    <div class="flex items-center gap-3">
                        <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode($data['member']->name) }}"
                             alt="{{ $data['member']->name }}"
                             class="h-11 w-11 shrink-0 rounded-2xl bg-indigo-50 object-cover shadow-md shadow-indigo-100">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <p class="text-sm font-bold text-slate-900 truncate">{{ $data['member']->name }}</p>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $badgeCls }}">{{ $badgeLabel }}</span>
                            </div>
                            <div class="mt-1 flex items-center gap-3 text-xs text-slate-400">
                                <span>Paid RS{{ number_format($data['owed'], 0) }}</span>
                                <span>·</span>
                                <span>Owes RS{{ number_format($data['owes'], 0) }}</span>
                            </div>
                        </div>
                        <p class="shrink-0 text-lg font-black {{ $netCls }}">
                            @if($data['net'] > 0.01)+@elseif($data['net'] < -0.01)-@endif
                            RS{{ number_format(abs($data['net']), 0) }}
                        </p>
                    </div>
                </div>
            @empty
                <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-10 text-center">
                    <p class="text-sm text-slate-400">No members in this group</p>
                </div>
            @endforelse
        </div>

        {{-- Debt details --}}
        @if(count($balances) > 0)
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                {{-- Header + filters --}}
                <div class="px-5 py-4 border-b border-slate-100 space-y-3">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Debt Details</h3>
                            <p class="text-xs text-slate-400 mt-0.5">Exact amounts to settle</p>
                        </div>
                        @if($debtFilterMember !== '' || $debtSortBy !== 'highest')
                            <button wire:click="$set('debtFilterMember', ''); $set('debtSortBy', 'highest')"
                                    class="text-xs font-bold text-indigo-600 hover:text-indigo-700 transition-colors">
                                Clear
                            </button>
                        @endif
                    </div>

                    {{-- Filter row --}}
                    <div class="grid grid-cols-2 gap-2">
                        {{-- Member filter --}}
                        <select wire:model.live="debtFilterMember"
                                class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            <option value="">All members</option>
                            @foreach($members as $m)
                                <option value="{{ $m->id }}">{{ $m->name }}</option>
                            @endforeach
                        </select>

                        {{-- Sort --}}
                        <select wire:model.live="debtSortBy"
                                class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">
                            <option value="highest">Highest first</option>
                            <option value="lowest">Lowest first</option>
                        </select>
                    </div>
                </div>

                {{-- Rows --}}
                @if(count($filteredBalances) > 0)
                    <div class="divide-y divide-slate-50">
                        @foreach($filteredBalances as $balance)
                            @php
                                $isYouFrom = $balance['from'] === Auth::id();
                                $isYouTo   = $balance['to']   === Auth::id();
                                $rowCls    = $isYouFrom ? 'bg-rose-50/30' : ($isYouTo ? 'bg-emerald-50/30' : '');
                                $amtCls    = $isYouFrom ? 'text-rose-600' : ($isYouTo ? 'text-emerald-600' : 'text-slate-900');
                            @endphp
                            <div class="flex items-center justify-between px-5 py-4 {{ $rowCls }} hover:opacity-80 transition-colors">
                                <div class="flex items-center gap-2 min-w-0">
                                    <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode($balance['fromUser']->name) }}"
                                         alt="{{ $balance['fromUser']->name }}"
                                         class="h-7 w-7 shrink-0 rounded-lg bg-slate-100 object-cover">
                                    <span class="text-sm font-semibold text-slate-900 truncate">
                                        {{ $isYouFrom ? 'You' : $balance['fromUser']->name }}
                                    </span>
                                    <x-icon name="arrow-right" class="w-4 h-4 text-slate-300 shrink-0" stroke-width="2.5" />
                                    <img src="https://api.dicebear.com/9.x/bottts-neutral/svg?seed={{ urlencode($balance['toUser']->name) }}"
                                         alt="{{ $balance['toUser']->name }}"
                                         class="h-7 w-7 shrink-0 rounded-lg bg-slate-100 object-cover">
                                    <span class="text-sm font-semibold text-slate-900 truncate">
                                        {{ $isYouTo ? 'You' : $balance['toUser']->name }}
                                    </span>
                                </div>
                                <span class="ml-3 shrink-0 text-sm font-bold {{ $amtCls }}">RS{{ number_format($balance['amount'], 2) }}</span>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="py-10 text-center">
                        <x-icon name="search-x" class="w-8 h-8 text-slate-200 mx-auto mb-2" stroke-width="1.5" />
                        <p class="text-sm font-medium text-slate-400">No debts match the filter</p>
                        <button wire:click="$set('debtFilterMember', '')"
                                class="mt-2 text-xs font-bold text-indigo-600 hover:text-indigo-700">Clear filter</button>
                    </div>
                @endif
            </div>

            <a href="{{ route('settle') }}" wire:navigate class="flex items-center justify-center gap-2 w-full rounded-2xl bg-indigo-600 px-6 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 active:scale-[0.98] transition-all">
                <x-icon name="arrow-right-left" class="w-4 h-4" stroke-width="2.5" />
                Record a Settlement
            </a>
        @else
            <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-8 text-center shadow-sm">
                <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-2xl bg-emerald-100">
                    <x-icon name="circle-check-big" class="w-6 h-6 text-emerald-600" stroke-width="2.5" />
                </div>
                <h3 class="text-sm font-bold text-emerald-900">All Settled Up!</h3>
                <p class="mt-1 text-xs text-emerald-600">No outstanding debts in this group</p>
            </div>
        @endif
    </div>
@endif
</div>
