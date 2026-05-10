<?php

namespace App\Console\Commands;

use App\Mail\MonthlySummary;
use App\Models\Group;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendMonthlyExpenseSummary extends Command
{
    protected $signature = 'emails:monthly-summary {--month= : Target month in YYYY-MM format (defaults to previous month)}';
    protected $description = 'Send monthly expense summary emails to all group members';

    public function handle(): void
    {
        $monthArg = $this->option('month');
        $month = $monthArg
            ? Carbon::createFromFormat('Y-m', $monthArg)->startOfMonth()
            : now()->subMonth()->startOfMonth();

        $start = $month->copy()->startOfMonth();
        $end   = $month->copy()->endOfMonth();
        $label = $month->format('F Y');

        $this->info("Sending {$label} summaries…");

        $groups = Group::with('members')->get();
        $queued = 0;

        foreach ($groups as $group) {
            $hasActivity = $group->expenses()
                ->whereBetween('date', [$start, $end])
                ->exists();

            if (! $hasActivity) {
                continue;
            }

            foreach ($group->members as $member) {
                $stats = $this->buildStats($group, $member, $start, $end);
                try {
                    Mail::to($member)->queue(new MonthlySummary($member, $group, $stats, $label));
                    Log::info('[Mail] MonthlySummary — queued', [
                        'month'   => $label,
                        'group'   => $group->name,
                        'to'      => $member->email,
                    ]);
                    $queued++;
                } catch (\Throwable $e) {
                    Log::error('[Mail] MonthlySummary — queue failed', [
                        'month'   => $label,
                        'group'   => $group->name,
                        'to'      => $member->email,
                        'error'   => $e->getMessage(),
                    ]);
                    $this->error("Failed to queue for {$member->email}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Queued {$queued} email(s) for {$label}.");
        Log::info('[Mail] MonthlySummary — batch complete', ['month' => $label, 'queued' => $queued]);
    }

    private function buildStats(Group $group, User $member, Carbon $start, Carbon $end): array
    {
        $groupTotal = (float) $group->expenses()
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        $paidByMember = (float) $group->expenses()
            ->where('paid_by', $member->id)
            ->whereBetween('date', [$start, $end])
            ->sum('amount');

        // Member's share across all expenses they participated in
        $memberShare = $group->expenses()
            ->whereBetween('date', [$start, $end])
            ->whereHas('participants', fn ($q) => $q->where('users.id', $member->id))
            ->with(['participants' => fn ($q) => $q->where('users.id', $member->id)])
            ->get()
            ->sum(function ($expense) use ($member) {
                $pivot = $expense->participants->first()?->pivot;
                if ($pivot && $pivot->amount !== null) {
                    return (float) $pivot->amount;
                }
                return (float) $expense->amount / max($expense->participants->count(), 1);
            });

        // Net balance: positive = others owe you, negative = you owe others
        $netBalance = $paidByMember - $memberShare;

        $categories = $group->expenses()
            ->whereBetween('date', [$start, $end])
            ->selectRaw('category, SUM(amount) as total, COUNT(*) as cnt')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $recent = $group->expenses()
            ->whereBetween('date', [$start, $end])
            ->with('payer')
            ->latest('date')
            ->limit(5)
            ->get();

        $expenseCount = $group->expenses()
            ->whereBetween('date', [$start, $end])
            ->count();

        return compact('groupTotal', 'paidByMember', 'memberShare', 'netBalance', 'categories', 'recent', 'expenseCount');
    }
}
