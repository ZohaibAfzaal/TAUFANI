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
        <div class="rounded-2xl bg-white border border-slate-100 shadow-sm overflow-hidden">
            @if($expenses->count())
                <div class="divide-y divide-slate-50">
                    @foreach($expenses as $expense)
                        <div class="flex items-center gap-3 px-5 py-4 hover:bg-slate-50/50 transition-colors">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-slate-100">
                                <x-icon name="{{ $expense->category ?? 'receipt' }}" class="w-4 h-4 text-slate-600" stroke-width="2" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-semibold text-slate-900 truncate">{{ $expense->description }}</p>
                                <div class="flex items-center gap-1.5 mt-0.5">
                                    <span class="text-xs text-slate-400">{{ $expense->payer->name }}</span>
                                    <span class="text-slate-200">·</span>
                                    <span class="text-xs text-slate-400">{{ $expense->date->format('M d, Y') }}</span>
                                    <span class="text-slate-200">·</span>
                                    <span class="text-xs text-slate-400">{{ $expense->participants->count() }} people</span>
                                </div>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-bold text-slate-900">RS{{ number_format($expense->amount, 0) }}</p>
                                <p class="text-[10px] font-semibold text-slate-400 mt-0.5">{{ $expense->split_type === 'equal' ? 'Equal' : 'Custom' }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/50">
                    {{ $expenses->links() }}
                </div>
            @else
                <div class="py-14 text-center">
                    <x-icon name="receipt" class="w-10 h-10 text-slate-200 mx-auto mb-3" stroke-width="1.5" />
                    <p class="text-sm font-semibold text-slate-500">No expenses found</p>
                    <p class="text-xs text-slate-400 mt-1">Try adjusting your filters</p>
                </div>
            @endif
        </div>
    </div>
@endif
</div>
