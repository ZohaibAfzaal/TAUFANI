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

        // 7-day spending trend
        $last7Days = collect();
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $total = $activeGroup->expenses()->whereDate('date', $date)->sum('amount');
            $last7Days->push([
                'day'   => now()->subDays($i)->format('D'),
                'date'  => $date,
                'total' => (float) $total,
            ]);
        }
        $maxDay = $last7Days->max('total') ?: 1;

        // Member spending (who has paid the most)
        $memberSpending = $activeGroup->expenses()
            ->selectRaw('paid_by, SUM(amount) as total')
            ->groupBy('paid_by')
            ->orderByDesc('total')
            ->limit(5)
            ->get()
            ->map(fn($row) => (object)[
                'total' => $row->total,
                'payer' => \App\Models\User::find($row->paid_by),
            ]);
        $maxMember = $memberSpending->max('total') ?: 1;

        return [
            'activeGroup' => $activeGroup,
            'netBalance' => $netBalance,
            'youOwe' => $youOwe,
            'owedToYou' => $owedToYou,
            'recentExpenses' => $recentExpenses,
            'activeDebts' => $activeDebts,
            'categorySpending' => $categorySpending,
            'maxCategory' => $maxCategory,
            'last7Days' => $last7Days,
            'maxDay' => $maxDay,
            'memberSpending' => $memberSpending,
            'maxMember' => $maxMember,
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
    $last7Days        = $last7Days        ?? collect();
    $maxDay           = $maxDay           ?? 1;
    $memberSpending   = $memberSpending   ?? collect();
    $maxMember        = $maxMember        ?? 1;
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

        {{-- ── Chart.js: 7-Day Spending Trend ─────────────────────────── --}}
        @php
            $trendLabels = $last7Days->pluck('day')->toArray();
            $trendData   = $last7Days->pluck('total')->toArray();
            $weekTotal   = $last7Days->sum('total');
        @endphp
        @if($weekTotal > 0)
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">7-Day Trend</h3>
                    <p class="text-xs text-slate-400 mt-0.5">RS{{ number_format($weekTotal, 0) }} this week</p>
                </div>
                {{-- data-* attrs use single quotes; JSON_HEX_APOS escapes any ' inside safely --}}
                <div class="px-4 pb-4 pt-3"
                     x-data="{ chart: null,
                         initChart() {
                             if (this.chart) { this.chart.destroy(); this.chart = null; }
                             const labels = JSON.parse(this.$el.getAttribute('data-labels'));
                             const values = JSON.parse(this.$el.getAttribute('data-values'));
                             const ctx = this.$refs.canvas.getContext('2d');
                             const grad = ctx.createLinearGradient(0, 0, 0, 130);
                             grad.addColorStop(0, 'rgba(99,102,241,0.3)');
                             grad.addColorStop(1, 'rgba(99,102,241,0.02)');
                             this.chart = new window.Chart(ctx, {
                                 type: 'bar',
                                 data: { labels, datasets: [{ data: values, backgroundColor: grad,
                                     borderColor: '#6366f1', borderWidth: 2,
                                     borderRadius: 8, borderSkipped: false }] },
                                 options: { responsive: true, maintainAspectRatio: false,
                                     plugins: { legend: { display: false },
                                         tooltip: { backgroundColor: '#1e293b', titleColor: '#94a3b8',
                                             bodyColor: '#f8fafc', padding: 10, cornerRadius: 10,
                                             callbacks: { label: c => ' RS ' + c.parsed.y.toLocaleString() } } },
                                     scales: {
                                         x: { grid: { display: false }, border: { display: false },
                                              ticks: { font: { size: 10, weight: '700' }, color: '#94a3b8' } },
                                         y: { display: false } } }
                             });
                         }
                     }"
                     x-init="initChart(); $cleanup(() => { if (chart) chart.destroy(); })"
                     data-labels='@json($trendLabels)'
                     data-values='@json($trendData)'>
                    <canvas x-ref="canvas" style="height:130px"></canvas>
                </div>
            </div>
        @endif

        {{-- ── Chart.js: Category Doughnut ─────────────────────────────── --}}
        @if($categorySpending->count() > 1)
            @php
                $catLabelMap = [
                    'receipt'=>'General','shopping-cart'=>'Groceries','utensils'=>'Food',
                    'coffee'=>'Drinks','car'=>'Transport','plane'=>'Travel','home'=>'Housing',
                    'wifi'=>'Internet','zap'=>'Electric','flame'=>'Gas','droplets'=>'Water',
                    'heart-pulse'=>'Health','graduation-cap'=>'Education','film'=>'Fun',
                    'shirt'=>'Shopping','music'=>'Music','smartphone'=>'Tech','trophy'=>'Sports',
                ];
                $donutLabels = $categorySpending->map(fn($r) => $catLabelMap[$r->category ?? 'receipt'] ?? 'General')->toArray();
                $donutData   = $categorySpending->pluck('total')->map(fn($v) => (float)$v)->toArray();
                $donutColors = ['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981'];
                $slicedColors = array_slice($donutColors, 0, count($donutLabels));
            @endphp
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Category Breakdown</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Where the money goes</p>
                </div>
                <div class="flex items-center gap-4 px-5 py-5"
                     x-data="{ chart: null,
                         initChart() {
                             if (this.chart) { this.chart.destroy(); this.chart = null; }
                             const labels = JSON.parse(this.$el.getAttribute('data-labels'));
                             const values = JSON.parse(this.$el.getAttribute('data-values'));
                             const colors = JSON.parse(this.$el.getAttribute('data-colors'));
                             this.chart = new window.Chart(this.$refs.canvas.getContext('2d'), {
                                 type: 'doughnut',
                                 data: { labels, datasets: [{ data: values, backgroundColor: colors,
                                     borderWidth: 3, borderColor: '#fff', hoverOffset: 6 }] },
                                 options: { responsive: false, cutout: '70%',
                                     plugins: { legend: { display: false },
                                         tooltip: { backgroundColor: '#1e293b', titleColor: '#94a3b8',
                                             bodyColor: '#f8fafc', padding: 10, cornerRadius: 10,
                                             callbacks: { label: c => ' RS ' + c.parsed.toLocaleString() } } } }
                             });
                         }
                     }"
                     x-init="initChart(); $cleanup(() => { if (chart) chart.destroy(); })"
                     data-labels='@json($donutLabels)'
                     data-values='@json($donutData)'
                     data-colors='@json($slicedColors)'>
                    <canvas x-ref="canvas" width="110" height="110" class="shrink-0"></canvas>
                    <div class="flex-1 space-y-2.5 min-w-0">
                        @foreach($donutLabels as $i => $lbl)
                            @php $color = $donutColors[$i % count($donutColors)]; @endphp
                            <div class="flex items-center gap-2">
                                <div class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:{{ $color }}"></div>
                                <span class="flex-1 text-xs text-slate-500 truncate">{{ $lbl }}</span>
                                <span class="text-xs font-bold text-slate-900">RS{{ number_format($donutData[$i], 0) }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        @endif

        {{-- ── Chart.js: Member Contributions (horizontal bar) ─────────── --}}
        @if($memberSpending->count() > 1)
            @php
                $memberNames    = $memberSpending->map(fn($r) => explode(' ', $r->payer->name ?? 'Unknown')[0])->toArray();
                $memberTotals   = $memberSpending->pluck('total')->map(fn($v) => (float)$v)->toArray();
                $memberColors   = array_slice(['#6366f1','#8b5cf6','#ec4899','#f59e0b','#10b981'], 0, count($memberNames));
                $memberBarH     = count($memberNames) * 44;
            @endphp
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-slate-100">
                    <h3 class="text-sm font-bold text-slate-900">Member Contributions</h3>
                    <p class="text-xs text-slate-400 mt-0.5">Who has paid the most</p>
                </div>
                <div class="px-4 py-4"
                     x-data="{ chart: null,
                         initChart() {
                             if (this.chart) { this.chart.destroy(); this.chart = null; }
                             const labels = JSON.parse(this.$el.getAttribute('data-labels'));
                             const values = JSON.parse(this.$el.getAttribute('data-values'));
                             const colors = JSON.parse(this.$el.getAttribute('data-colors'));
                             this.chart = new window.Chart(this.$refs.canvas.getContext('2d'), {
                                 type: 'bar',
                                 data: { labels, datasets: [{ data: values, backgroundColor: colors,
                                     borderRadius: 8, borderSkipped: false }] },
                                 options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                                     plugins: { legend: { display: false },
                                         tooltip: { backgroundColor: '#1e293b', titleColor: '#94a3b8',
                                             bodyColor: '#f8fafc', padding: 10, cornerRadius: 10,
                                             callbacks: { label: c => ' RS ' + c.parsed.x.toLocaleString() } } },
                                     scales: { x: { display: false },
                                         y: { grid: { display: false }, border: { display: false },
                                              ticks: { font: { size: 11, weight: '700' }, color: '#475569' } } } }
                             });
                         }
                     }"
                     x-init="initChart(); $cleanup(() => { if (chart) chart.destroy(); })"
                     data-labels='@json($memberNames)'
                     data-values='@json($memberTotals)'
                     data-colors='@json($memberColors)'>
                    <canvas x-ref="canvas" style="height:{{ $memberBarH }}px"></canvas>
                </div>
            </div>
        @endif

        {{-- Spending by category (icon bars) --}}
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
