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
                'netBalance' => 0,
                'youOwe' => 0,
                'owedToYou' => 0,
                'recentExpenses' => collect(),
                'activeDebts' => [],
            ];
        }

        $balances = $calculator->withUsers(
            $calculator->calculate(collect([$activeGroup->id]), $user->id)
        );

        $youOwe = collect($balances)->where('from', $user->id)->sum('amount');
        $owedToYou = collect($balances)->where('to', $user->id)->sum('amount');
        $netBalance = $owedToYou - $youOwe;

        // Recent expenses (last 5)
        $recentExpenses = $activeGroup->expenses()
            ->with(['payer', 'participants'])
            ->latest()
            ->limit(5)
            ->get();

        // Active debts (only this user's)
        $activeDebts = collect($balances)->take(5);

        // Category spending for chart
        $categorySpending = $activeGroup->expenses()
            ->selectRaw('category, SUM(amount) as total')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();
        $maxCategory = $categorySpending->max('total') ?: 1;

        return [
            'activeGroup' => $activeGroup,
            'netBalance' => $netBalance,
            'youOwe' => $youOwe,
            'owedToYou' => $owedToYou,
            'recentExpenses' => $recentExpenses,
            'activeDebts' => $activeDebts,
            'categorySpending' => $categorySpending,
            'maxCategory' => $maxCategory,
            'user' => $user,
        ];
    }
};
?>

@php
    $activeGroup      = $activeGroup      ?? null;
    $netBalance       = $netBalance       ?? 0;
    $youOwe           = $youOwe           ?? 0;
    $owedToYou        = $owedToYou        ?? 0;
    $recentExpenses   = $recentExpenses   ?? collect();
    $categorySpending = $categorySpending ?? collect();
    $maxCategory      = $maxCategory      ?? 1;
    $user             = $user             ?? Auth::user();
@endphp

<div>
@if(!$activeGroup)
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-10 text-center">
        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100">
            <x-icon name="users" class="w-6 h-6 text-slate-400" stroke-width="2" />
        </div>
        <h3 class="text-base font-bold text-slate-900">No group selected</h3>
        <p class="mt-1 text-sm text-slate-400">Use the dropdown above to select or create a group</p>
        <a href="{{ route('groups') }}" wire:navigate class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all">
            <x-icon name="plus" class="w-4 h-4" stroke-width="3" />
            Get Started
        </a>
    </div>
@else
    <div class="space-y-4 animate-in fade-in slide-in-from-bottom-4 duration-500">

        {{-- Greeting --}}
        <div class="px-1">
            <h1 class="text-2xl font-extrabold text-slate-900">Hey, {{ explode(' ', $user->name)[0] }} 👋</h1>
            <p class="text-xs text-slate-400 mt-0.5">Here's your overview for {{ $activeGroup->name }}</p>
        </div>

        {{-- Net balance hero card --}}
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 to-indigo-950 p-6 text-white shadow-xl shadow-slate-900/20">
            <div class="absolute -right-8 -top-8 h-32 w-32 rounded-full bg-indigo-500/20 blur-2xl pointer-events-none"></div>
            <div class="absolute -left-4 bottom-0 h-24 w-24 rounded-full bg-violet-500/10 blur-xl pointer-events-none"></div>
            <div class="relative">
                <p class="text-[10px] font-bold uppercase tracking-widest text-slate-400">Net Balance</p>
                <div class="mt-1.5 flex items-baseline gap-1">
                    <span class="self-start mt-1.5 text-[11px] font-bold text-slate-400">RS</span>
                    <span class="text-4xl font-black tracking-tight">{{ number_format(abs($netBalance), 0) }}</span>
                </div>
                <p class="mt-2 text-xs font-semibold">
                    @if($netBalance > 0.01)
                        <span class="text-emerald-400">You are owed overall</span>
                    @elseif($netBalance < -0.01)
                        <span class="text-rose-400">You owe overall</span>
                    @else
                        <span class="text-slate-400">All settled up ✓</span>
                    @endif
                </p>
            </div>
        </div>

        {{-- You Owe / Owed to You --}}
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl bg-rose-50 border border-rose-100 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-widest text-rose-500">You Owe</p>
                <p class="mt-2 text-xl font-black text-rose-700">RS{{ number_format($youOwe, 0) }}</p>
            </div>
            <div class="rounded-2xl bg-emerald-50 border border-emerald-100 p-4 shadow-sm">
                <p class="text-[10px] font-bold uppercase tracking-widest text-emerald-600">Owed to You</p>
                <p class="mt-2 text-xl font-black text-emerald-700">RS{{ number_format($owedToYou, 0) }}</p>
            </div>
        </div>

        {{-- Quick actions --}}
        <div class="grid grid-cols-2 gap-3">
            <a href="{{ route('expenses.create') }}" wire:navigate class="flex items-center justify-center gap-2 rounded-2xl bg-indigo-600 px-4 py-3.5 text-sm font-bold text-white shadow-lg shadow-indigo-200 transition-all hover:bg-indigo-700 active:scale-[0.97]">
                <x-icon name="plus" class="w-4 h-4" stroke-width="3" />
                Add Expense
            </a>
            <a href="{{ route('settle') }}" wire:navigate class="flex items-center justify-center gap-2 rounded-2xl bg-white border border-slate-200 px-4 py-3.5 text-sm font-bold text-slate-700 shadow-sm transition-all hover:bg-slate-50 active:scale-[0.97]">
                <x-icon name="arrow-right-left" class="w-4 h-4 text-slate-500" stroke-width="2.5" />
                Settle Up
            </a>
        </div>

        {{-- Spending by category chart --}}
        @if($categorySpending->count() > 0)
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Spending by Category</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Top categories this group</p>
                </div>
                <div class="px-5 py-4 space-y-3.5">
                    @foreach($categorySpending as $row)
                        @php
                            $pct = $maxCategory > 0 ? ($row->total / $maxCategory) * 100 : 0;
                            $label = match($row->category ?? 'receipt') {
                                'shopping-cart'  => 'Groceries',
                                'utensils'       => 'Food & Dining',
                                'coffee'         => 'Beverages',
                                'car'            => 'Transport',
                                'plane'          => 'Travel',
                                'home'           => 'Housing',
                                'wifi'           => 'Internet',
                                'zap'            => 'Electricity',
                                'flame'          => 'Gas',
                                'heart-pulse'    => 'Health',
                                'graduation-cap' => 'Education',
                                'film'           => 'Entertainment',
                                'shirt'          => 'Shopping',
                                default          => 'General',
                            };
                        @endphp
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-indigo-50">
                                <x-icon name="{{ $row->category ?? 'receipt' }}" class="w-4 h-4 text-indigo-600" stroke-width="2" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between mb-1">
                                    <span class="text-xs font-semibold text-slate-700">{{ $label }}</span>
                                    <span class="text-xs font-bold text-slate-900">RS{{ number_format($row->total, 0) }}</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-slate-100">
                                    <div class="h-full rounded-full bg-gradient-to-r from-indigo-500 to-violet-500 transition-all duration-500"
                                         style="width: {{ $pct }}%"></div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Recent expenses --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
            <div class="flex items-center justify-between px-5 py-4 border-b border-slate-100">
                <h3 class="text-sm font-bold text-slate-900">Recent Expenses</h3>
                <a href="{{ route('expenses') }}" wire:navigate class="text-xs font-bold text-indigo-600 hover:text-indigo-700">View all →</a>
            </div>

            @forelse($recentExpenses as $expense)
                <div class="flex items-center gap-3 px-5 py-4 border-b border-slate-50 last:border-0 hover:bg-slate-50/50 transition-colors">
                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100">
                        <x-icon name="{{ $expense->category ?? 'receipt' }}" class="w-4 h-4 text-slate-600" stroke-width="2" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-slate-900 truncate">{{ $expense->description }}</p>
                        <p class="text-xs text-slate-400 mt-0.5">{{ $expense->payer->name }} · {{ $expense->date->format('M d') }} · {{ $expense->participants->count() }} people</p>
                    </div>
                    <p class="shrink-0 text-sm font-bold text-slate-900">RS{{ number_format($expense->amount, 0) }}</p>
                </div>
            @empty
                <div class="py-10 text-center">
                    <x-icon name="receipt" class="w-8 h-8 text-slate-200 mx-auto mb-2" stroke-width="1.5" />
                    <p class="text-sm font-medium text-slate-400">No expenses yet</p>
                    <a href="{{ route('expenses.create') }}" wire:navigate class="mt-2 inline-block text-xs font-bold text-indigo-600 hover:text-indigo-700">Add the first one →</a>
                </div>
            @endforelse
        </div>

    </div>
@endif
</div>
