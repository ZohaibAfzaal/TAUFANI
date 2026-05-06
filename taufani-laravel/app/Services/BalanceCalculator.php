<?php

namespace App\Services;

use App\Models\Expense;
use App\Models\Settlement;
use App\Models\User;
use Illuminate\Support\Collection;

class BalanceCalculator
{
    const ZERO_THRESHOLD = 0.01;

    /**
     * Calculate pairwise debt balances across the given group IDs.
     * Returns an array of ['from' => int, 'to' => int, 'amount' => float].
     * Optionally filter results to only debts involving $forUserId.
     */
    public function calculate(Collection $groupIds, ?int $forUserId = null): array
    {
        $balanceMap = $this->buildBalanceMap($groupIds);

        $results = [];
        foreach ($balanceMap as $k => $val) {
            if (abs($val) < self::ZERO_THRESHOLD) {
                continue;
            }

            [$a, $b] = explode('|', $k);
            if ($val > 0) {
                $results[] = ['from' => (int) $a, 'to' => (int) $b, 'amount' => round($val, 2)];
            } else {
                $results[] = ['from' => (int) $b, 'to' => (int) $a, 'amount' => round(-$val, 2)];
            }
        }

        if ($forUserId !== null) {
            $results = array_values(array_filter($results, fn ($r) => $r['from'] === $forUserId || $r['to'] === $forUserId));
        }

        return $results;
    }

    /**
     * Hydrate balance results with User models, avoiding N+1 queries.
     * Returns the same array with 'fromUser' and 'toUser' User objects added.
     */
    public function withUsers(array $balances): array
    {
        if (empty($balances)) {
            return [];
        }

        $ids = collect($balances)->flatMap(fn ($b) => [$b['from'], $b['to']])->unique()->values();
        $users = User::whereIn('id', $ids)->get()->keyBy('id');

        return array_map(fn ($b) => array_merge($b, [
            'fromUser' => $users->get($b['from']),
            'toUser'   => $users->get($b['to']),
        ]), $balances);
    }

    private function buildBalanceMap(Collection $groupIds): array
    {
        $balanceMap = [];

        $expenses = Expense::whereIn('group_id', $groupIds)
            ->with('participants')
            ->get();

        foreach ($expenses as $exp) {
            $n = $exp->participants->count();
            if ($n === 0) {
                continue;
            }

            foreach ($exp->participants as $p) {
                if ($p->id === $exp->paid_by) {
                    continue;
                }

                $share = $exp->split_type === 'custom'
                    ? ($p->pivot->amount ?? 0)
                    : ($exp->amount / $n);

                $k = $this->pairKey($p->id, $exp->paid_by);
                $s = $this->pairSign($p->id, $exp->paid_by);
                $balanceMap[$k] = ($balanceMap[$k] ?? 0) + ($share * $s);
            }
        }

        $settlements = Settlement::whereIn('group_id', $groupIds)->get();
        foreach ($settlements as $stl) {
            $k = $this->pairKey($stl->from_id, $stl->to_id);
            $s = $this->pairSign($stl->from_id, $stl->to_id);
            $balanceMap[$k] = ($balanceMap[$k] ?? 0) - ($stl->amount * $s);
        }

        return $balanceMap;
    }

    private function pairKey(int $a, int $b): string
    {
        return $a < $b ? "$a|$b" : "$b|$a";
    }

    private function pairSign(int $from, int $to): int
    {
        return $from < $to ? 1 : -1;
    }
}
