<?php

use Livewire\Volt\Component;
use App\Models\Expense;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

new class extends Component
{
    public function with()
    {
        $user = Auth::user();
        $pairBalances = $this->calculateBalances();

        $youOwe = collect($pairBalances)->where('from', $user->id)->sum('amount');
        $owedToYou = collect($pairBalances)->where('to', $user->id)->sum('amount');
        $netBalance = $owedToYou - $youOwe;

        return [
            'netBalance' => $netBalance,
            'youOwe' => $youOwe,
            'owedToYou' => $owedToYou,
            'details' => $pairBalances,
            'user' => $user,
        ];
    }

    protected function calculateBalances()
    {
        $currentUserId = Auth::id();
        $balanceMap = [];

        $pairKey = function($a, $b) {
            return $a < $b ? "$a|$b" : "$b|$a";
        };
        $pairSign = function($from, $to) {
            return $from < $to ? 1 : -1;
        };

        $groupIds = Auth::user()->groups()->pluck('groups.id');
        $expenses = Expense::whereIn('group_id', $groupIds)->with(['participants'])->get();

        foreach ($expenses as $exp) {
            $n = $exp->participants->count();
            if ($n === 0) continue;

            foreach ($exp->participants as $p) {
                if ($p->id === $exp->paid_by) continue;

                $share = ($exp->split_type === 'custom') 
                    ? ($p->pivot->amount ?? 0) 
                    : ($exp->amount / $n);

                $k = $pairKey($p->id, $exp->paid_by);
                $s = $pairSign($p->id, $exp->paid_by);
                
                $balanceMap[$k] = ($balanceMap[$k] ?? 0) + ($share * $s);
            }
        }

        $settlements = Settlement::whereIn('group_id', $groupIds)->get();
        foreach ($settlements as $stl) {
            $k = $pairKey($stl->from_id, $stl->to_id);
            $s = $pairSign($stl->from_id, $stl->to_id);
            $balanceMap[$k] = ($balanceMap[$k] ?? 0) - ($stl->amount * $s);
        }

        $results = [];
        foreach ($balanceMap as $k => $val) {
            if (abs($val) < 0.5) continue;
            
            [$a, $b] = explode('|', $k);
            if ($val > 0) {
                $results[] = ['from' => (int)$a, 'to' => (int)$b, 'amount' => round($val, 2)];
            } else {
                $results[] = ['from' => (int)$b, 'to' => (int)$a, 'amount' => round(-$val, 2)];
            }
        }

        return array_filter($results, function($b) use ($currentUserId) {
            return $b['from'] === $currentUserId || $b['to'] === $currentUserId;
        });
    }
};
?>

<div class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-700">
    <!-- Premium Balance Card -->
    <div class="relative overflow-hidden rounded-[2.5rem] bg-slate-900 p-8 text-white shadow-2xl shadow-indigo-200">
        <!-- Background Decorative Elements -->
        <div class="absolute -right-12 -top-12 h-64 w-64 rounded-full bg-indigo-500/20 blur-3xl"></div>
        <div class="absolute -left-12 -bottom-12 h-64 w-64 rounded-full bg-rose-500/10 blur-3xl"></div>
        
        <div class="relative">
            <p class="text-xs font-bold uppercase tracking-[0.2em] text-indigo-300/80">Total Net Balance</p>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-light text-indigo-200">RS</span>
                <h1 class="text-5xl font-black tracking-tighter">
                    {{ number_format(abs($netBalance), 0) }}<span class="text-2xl">.{{ substr(number_format(abs($netBalance), 2), -2) }}</span>
                </h1>
            </div>
            
            <div class="mt-8 grid grid-cols-2 gap-4">
                <div class="rounded-3xl bg-white/5 p-4 backdrop-blur-md border border-white/10">
                    <div class="flex items-center gap-2 text-rose-400">
                        <div class="rounded-full bg-rose-400/20 p-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m5 12 7-7 7 7"/><path d="M12 19V5"/></svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider">You Owe</span>
                    </div>
                    <p class="mt-2 text-xl font-bold">RS{{ number_format($youOwe, 0) }}</p>
                </div>
                
                <div class="rounded-3xl bg-white/5 p-4 backdrop-blur-md border border-white/10">
                    <div class="flex items-center gap-2 text-emerald-400">
                        <div class="rounded-full bg-emerald-400/20 p-1">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m19 12-7 7-7-7"/><path d="M12 5v14"/></svg>
                        </div>
                        <span class="text-[10px] font-bold uppercase tracking-wider">Owed to You</span>
                    </div>
                    <p class="mt-2 text-xl font-bold">RS{{ number_format($owedToYou, 0) }}</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Details Section -->
    <div class="space-y-4">
        <div class="flex items-center justify-between px-2">
            <h3 class="text-sm font-black uppercase tracking-widest text-slate-400">Active Debts</h3>
            <span class="rounded-full bg-slate-100 px-3 py-1 text-[10px] font-bold text-slate-500">{{ count($details) }} entries</span>
        </div>
        
        <div class="grid gap-3">
            @forelse($details as $item)
                @php
                    $isOwed = $item['to'] === $user->id;
                    $otherUser = App\Models\User::find($isOwed ? $item['from'] : $item['to']);
                @endphp
                <div class="group relative flex items-center justify-between rounded-[2rem] bg-white p-5 shadow-sm border border-slate-100 transition-all hover:shadow-md hover:-translate-y-0.5">
                    <div class="flex items-center gap-4">
                        <div class="relative">
                            <div class="flex h-12 w-12 items-center justify-center rounded-2xl bg-slate-50 text-xl font-bold text-slate-700 group-hover:bg-indigo-50 group-hover:text-indigo-600 transition-colors">
                                {{ substr($otherUser->name, 0, 1) }}
                            </div>
                            <div class="absolute -bottom-1 -right-1 h-4 w-4 rounded-full border-2 border-white {{ $isOwed ? 'bg-emerald-500' : 'bg-rose-500' }}"></div>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900">{{ $otherUser->name }}</p>
                            <p class="text-[10px] font-medium text-slate-500">{{ $isOwed ? 'is waiting to pay you' : 'is waiting for your payment' }}</p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-lg font-black tracking-tight {{ $isOwed ? 'text-emerald-600' : 'text-rose-600' }}">
                            {{ $isOwed ? '+' : '-' }}RS{{ number_format($item['amount'], 0) }}
                        </p>
                        <p class="text-[9px] font-bold uppercase tracking-tighter text-slate-300">Outstanding</p>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center rounded-[2.5rem] border-2 border-dashed border-slate-100 p-16 text-center">
                    <div class="mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-slate-50">
                        <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" class="text-slate-300"><circle cx="12" cy="12" r="10"/><path d="m9 12 2 2 4-4"/></svg>
                    </div>
                    <h4 class="text-sm font-bold text-slate-900">Crystal Clear!</h4>
                    <p class="mt-1 text-xs text-slate-400">You don't have any pending debts with anyone.</p>
                </div>
            @endforelse
        </div>
    </div>
</div>