<?php

use Livewire\Volt\Component;
use App\Services\GroupContextService;
use App\Services\BalanceCalculator;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    public function with(GroupContextService $service, BalanceCalculator $calculator): array
    {
        $user = Auth::user();
        $activeGroup = $service->getActiveGroup($user);

        if (!$activeGroup) {
            return [
                'activeGroup' => null,
                'balances' => [],
                'members' => collect(),
                'summary' => [],
            ];
        }

        $rawBalances = $calculator->calculate(collect([$activeGroup->id]));
        $balances = $calculator->withUsers($rawBalances);

        // Group by member
        $members = $activeGroup->members()->withCount('expenses')->get();
        $memberBalances = [];

        foreach ($members as $member) {
            $memberOwes = collect($balances)->where('from', $member->id)->sum('amount');
            $memberOwed = collect($balances)->where('to', $member->id)->sum('amount');
            $netBalance = $memberOwed - $memberOwes;

            $memberBalances[$member->id] = [
                'member' => $member,
                'owes' => $memberOwes,
                'owed' => $memberOwed,
                'net' => $netBalance,
                'status' => match (true) {
                    $netBalance > 0.01 => 'creditor',
                    $netBalance < -0.01 => 'debtor',
                    default => 'settled',
                },
            ];
        }

        // Sort by status and net balance
        usort($memberBalances, function($a, $b) {
            $statusOrder = ['debtor' => 0, 'creditor' => 1, 'settled' => 2];
            $statusDiff = $statusOrder[$a['status']] - $statusOrder[$b['status']];
            if ($statusDiff !== 0) return $statusDiff;
            return abs($b['net']) - abs($a['net']);
        });

        return [
            'activeGroup' => $activeGroup,
            'balances' => $balances,
            'memberBalances' => $memberBalances,
            'members' => $members,
            'user' => $user,
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

        {{-- Member balance cards --}}
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
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Debt Details</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Exact amounts to settle</p>
                </div>
                <div class="divide-y divide-slate-50">
                    @foreach($balances as $balance)
                        <div class="flex items-center justify-between px-5 py-4 hover:bg-slate-50/50 transition-colors">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="text-sm font-semibold text-slate-900 truncate">{{ $balance['fromUser']->name }}</span>
                                <x-icon name="arrow-right" class="w-4 h-4 text-slate-300 shrink-0" stroke-width="2.5" />
                                <span class="text-sm font-semibold text-slate-900 truncate">{{ $balance['toUser']->name }}</span>
                            </div>
                            <span class="ml-3 shrink-0 text-sm font-bold text-slate-900">RS{{ number_format($balance['amount'], 2) }}</span>
                        </div>
                    @endforeach
                </div>
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
