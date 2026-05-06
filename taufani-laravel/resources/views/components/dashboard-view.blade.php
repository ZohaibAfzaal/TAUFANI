<?php

use Livewire\Volt\Component;
use App\Services\BalanceCalculator;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public function with()
    {
        $user = Auth::user();
        $groupIds = $user->groups()->pluck('groups.id');

        $calculator = new BalanceCalculator();
        $balances   = $calculator->withUsers(
            $calculator->calculate($groupIds, $user->id)
        );

        $youOwe    = collect($balances)->where('from', $user->id)->sum('amount');
        $owedToYou = collect($balances)->where('to', $user->id)->sum('amount');
        $netBalance = $owedToYou - $youOwe;

        return [
            'netBalance' => $netBalance,
            'youOwe'     => $youOwe,
            'owedToYou'  => $owedToYou,
            'details'    => $balances,
            'user'       => $user,
        ];
    }
};
?>

<div class="space-y-6 animate-in fade-in slide-in-from-bottom-4 duration-700">

    <!-- Greeting -->
    <div class="px-1">
        <p class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening') }}</p>
        <h2 class="text-xl font-extrabold text-slate-900 mt-0.5">{{ explode(' ', $user->name)[0] }}</h2>
    </div>

    <!-- Balance Card -->
    <div class="relative overflow-hidden rounded-[2rem] bg-slate-900 p-7 text-white shadow-2xl shadow-slate-900/20">
        <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-indigo-500/20 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-16 bottom-0 h-48 w-48 rounded-full bg-violet-500/10 blur-3xl pointer-events-none"></div>

        <div class="relative">
            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-slate-400">Net Balance</p>
            <div class="mt-1.5 flex items-baseline gap-1.5">
                <span class="text-xl font-light text-slate-400">RS</span>
                <span class="text-5xl font-black tracking-tighter leading-none">{{ number_format(abs($netBalance), 0) }}</span>
                <span class="text-2xl font-black text-slate-400">.{{ substr(number_format(abs($netBalance), 2), -2) }}</span>
            </div>
            <p class="mt-1 text-[11px] font-medium text-slate-500">
                @if($netBalance > 0.01)
                    You are owed overall
                @elseif($netBalance < -0.01)
                    You owe overall
                @else
                    All settled up
                @endif
            </p>

            <div class="mt-6 grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white/5 border border-white/8 p-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-rose-500/20">
                            <x-icon name="arrow-up" class="w-3 h-3 text-rose-400" stroke-width="3" />
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">You Owe</span>
                    </div>
                    <p class="mt-2.5 text-xl font-black">RS{{ number_format($youOwe, 0) }}</p>
                </div>

                <div class="rounded-2xl bg-white/5 border border-white/8 p-4">
                    <div class="flex items-center gap-2">
                        <div class="flex h-6 w-6 items-center justify-center rounded-full bg-emerald-500/20">
                            <x-icon name="arrow-down" class="w-3 h-3 text-emerald-400" stroke-width="3" />
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-400">Owed to You</span>
                    </div>
                    <p class="mt-2.5 text-xl font-black">RS{{ number_format($owedToYou, 0) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Debt List -->
    <div class="space-y-3">
        <div class="flex items-center justify-between px-1">
            <h3 class="text-[11px] font-bold uppercase tracking-widest text-slate-400">Active Debts</h3>
            @if(count($details))
                <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-500">{{ count($details) }}</span>
            @endif
        </div>

        @forelse($details as $item)
            @php
                $isOwed    = $item['to'] === $user->id;
                $otherUser = $isOwed ? $item['fromUser'] : $item['toUser'];
            @endphp
            <div class="group flex items-center justify-between rounded-[1.5rem] bg-white border border-slate-100 p-4 shadow-sm transition-all hover:shadow-md hover:-translate-y-px">
                <div class="flex items-center gap-3.5">
                    <div class="relative shrink-0">
                        <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-slate-50 text-base font-bold text-slate-700 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                            {{ strtoupper(substr($otherUser->name, 0, 1)) }}
                        </div>
                        <div class="absolute -bottom-0.5 -right-0.5 h-3.5 w-3.5 rounded-full border-2 border-white {{ $isOwed ? 'bg-emerald-500' : 'bg-rose-500' }}"></div>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-slate-900">{{ $otherUser->name }}</p>
                        <p class="text-[11px] text-slate-400">{{ $isOwed ? 'owes you' : 'you owe them' }}</p>
                    </div>
                </div>
                <p class="text-base font-black tracking-tight {{ $isOwed ? 'text-emerald-600' : 'text-rose-500' }}">
                    {{ $isOwed ? '+' : '-' }}RS{{ number_format($item['amount'], 0) }}
                </p>
            </div>
        @empty
            <div class="flex flex-col items-center justify-center rounded-[2rem] border-2 border-dashed border-slate-100 py-16 text-center">
                <div class="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-emerald-50">
                    <x-icon name="circle-check" class="w-8 h-8 text-emerald-400" stroke-width="1.5" />
                </div>
                <p class="text-sm font-semibold text-slate-900">All clear!</p>
                <p class="mt-1 text-xs text-slate-400">No pending debts right now.</p>
            </div>
        @endforelse
    </div>
</div>
