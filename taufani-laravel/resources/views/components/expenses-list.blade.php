<?php

use Livewire\Volt\Component;
use Livewire\WithPagination;
use App\Models\Expense;
use App\Services\GroupContextService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;

new #[Layout('layouts.app')] class extends Component
{
    use WithPagination;

    public ?int $activeGroupId = null;
    public string $filterCategory = '';
    public string $filterMember = '';
    public string $sortBy = 'latest';
    public string $search = '';

    public function mount(GroupContextService $service): void
    {
        $this->activeGroupId = $service->getActiveGroup()?->id;
    }

    public function with(GroupContextService $service): array
    {
        if (!$this->activeGroupId) {
            return [
                'expenses' => collect(),
                'categories' => [],
                'members' => collect(),
                'activeGroup' => null,
            ];
        }

        $user = Auth::user();
        $activeGroup = $service->getActiveGroup($user);

        if (!$activeGroup) {
            return ['expenses' => collect(), 'categories' => [], 'members' => collect(), 'activeGroup' => null];
        }

        $query = Expense::where('group_id', $this->activeGroupId)
            ->with(['payer', 'participants']);

        // Search by description
        if ($this->search) {
            $query->where('description', 'like', '%' . $this->search . '%');
        }

        // Filter by category
        if ($this->filterCategory) {
            $query->where('category', $this->filterCategory);
        }

        // Filter by payer
        if ($this->filterMember) {
            $query->where('paid_by', $this->filterMember);
        }

        // Sort
        match ($this->sortBy) {
            'oldest' => $query->oldest(),
            'highest' => $query->orderBy('amount', 'desc'),
            'lowest' => $query->orderBy('amount', 'asc'),
            default => $query->latest(),
        };

        $expenses = $query->paginate(15);

        // Get unique categories in this group
        $categories = Expense::where('group_id', $this->activeGroupId)
            ->distinct()
            ->pluck('category')
            ->filter()
            ->sort()
            ->values();

        $members = $activeGroup->members()->orderBy('name')->get();

        return [
            'expenses' => $expenses,
            'categories' => $categories,
            'members' => $members,
            'activeGroup' => $activeGroup,
        ];
    }

    public function updatedSearch()
    {
        $this->resetPage();
    }

    public function updatedFilterCategory()
    {
        $this->resetPage();
    }

    public function updatedFilterMember()
    {
        $this->resetPage();
    }

    public function updatedSortBy()
    {
        $this->resetPage();
    }

    public function clearFilters()
    {
        $this->search = '';
        $this->filterCategory = '';
        $this->filterMember = '';
        $this->sortBy = 'latest';
        $this->resetPage();
    }

    public function deleteExpense(int $id): void
    {
        $expense = Expense::findOrFail($id);
        $group = \App\Models\Group::find($this->activeGroupId);
        abort_unless($group && $group->members()->where('users.id', Auth::id())->exists(), 403);
        $expense->participants()->detach();
        $expense->delete();
        $this->resetPage();
    }
};
?>

<div>
@if(!$activeGroup)
    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-10 text-center">
        <x-icon name="receipt" class="w-8 h-8 text-slate-200 mx-auto mb-2" stroke-width="1.5" />
        <p class="text-sm font-medium text-slate-400">No group selected</p>
    </div>
@else
    <div class="space-y-4">
        {{-- Header --}}
        <div class="flex items-center justify-between px-1">
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900">Expenses</h1>
                <p class="text-xs text-slate-400 mt-0.5">{{ $activeGroup->name }}</p>
            </div>
            <a href="{{ route('expenses.create') }}" wire:navigate class="flex items-center gap-1.5 rounded-2xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg shadow-indigo-200 hover:bg-indigo-700 transition-all">
                <x-icon name="plus" class="w-4 h-4" stroke-width="3" />
                Add
            </a>
        </div>

        {{-- Filters --}}
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm p-4 space-y-3">
            {{-- Search --}}
            <input type="text" wire:model.live="search" placeholder="Search expenses…"
                   class="w-full rounded-2xl border border-slate-200 bg-slate-50/50 px-4 py-3 text-sm font-medium text-slate-900 placeholder-slate-400 focus:border-indigo-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500/20 transition-all">

            {{-- Filter row --}}
            <div class="grid grid-cols-3 gap-2">
                <select wire:model.live="filterCategory"
                        class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    <option value="">All categories</option>
                    @foreach($categories as $cat)
                        <option value="{{ $cat }}">{{ ucfirst(str_replace('-', ' ', $cat)) }}</option>
                    @endforeach
                </select>
                <select wire:model.live="filterMember"
                        class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    <option value="">Everyone</option>
                    @foreach($members as $member)
                        <option value="{{ $member->id }}">{{ $member->name }}</option>
                    @endforeach
                </select>
                <select wire:model.live="sortBy"
                        class="rounded-xl border border-slate-200 bg-slate-50/50 px-3 py-2.5 text-xs font-semibold text-slate-700 focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-500/20">
                    <option value="latest">Latest</option>
                    <option value="oldest">Oldest</option>
                    <option value="highest">Highest</option>
                    <option value="lowest">Lowest</option>
                </select>
            </div>

            @if($search || $filterCategory || $filterMember || $sortBy !== 'latest')
                <div class="flex justify-end">
                    <button wire:click="clearFilters" class="text-xs font-bold text-indigo-600 hover:text-indigo-700">Clear filters</button>
                </div>
            @endif
        </div>

        {{-- Expense list --}}
        @if($expenses->count())
            <div class="space-y-3">
                @foreach($expenses as $expense)
                    <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
                        <div class="flex items-start gap-3 px-4 pt-4 pb-3">
                            {{-- Category icon --}}
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-indigo-50">
                                <x-icon name="{{ $expense->category ?? 'receipt' }}" class="w-5 h-5 text-indigo-600" stroke-width="2" />
                            </div>
                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold text-slate-900 truncate">{{ $expense->description }}</p>
                                <p class="text-xs text-slate-400 mt-0.5">
                                    Paid by <span class="font-semibold text-slate-600">{{ $expense->payer->name }}</span>
                                    · {{ $expense->date->format('M d') }}
                                </p>
                            </div>
                            {{-- Amount + delete --}}
                            <div class="flex flex-col items-end gap-2 shrink-0">
                                <p class="text-sm font-black text-slate-900">RS{{ number_format($expense->amount, 0) }}</p>
                                <button wire:click="deleteExpense({{ $expense->id }})"
                                        onclick="return confirm('Delete \'{{ addslashes($expense->description) }}\'?')"
                                        class="text-slate-300 hover:text-rose-500 transition-colors">
                                    <x-icon name="trash" class="w-4 h-4" stroke-width="2" />
                                </button>
                            </div>
                        </div>
                        {{-- Participant chips --}}
                        @if($expense->participants->count() > 0)
                            <div class="flex flex-wrap gap-1.5 px-4 pb-3">
                                @foreach($expense->participants as $p)
                                    <span class="rounded-full bg-slate-100 px-2.5 py-1 text-[10px] font-semibold text-slate-500">
                                        {{ explode(' ', $p->name)[0] }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="pt-2">
                {{ $expenses->links() }}
            </div>
        @else
            <div class="rounded-2xl bg-white border border-slate-100 shadow-sm py-14 text-center">
                <x-icon name="receipt" class="w-10 h-10 text-slate-200 mx-auto mb-3" stroke-width="1.5" />
                <p class="text-sm font-semibold text-slate-500">No expenses found</p>
                <p class="text-xs text-slate-400 mt-1">Try adjusting your filters</p>
            </div>
        @endif
    </div>
@endif
</div>
