<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn() => redirect()->route('dashboard'));

Volt::route('/dashboard', 'dashboard-summary')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Volt::route('/groups', 'group-manager-view')->name('groups');
    Volt::route('/expenses', 'expenses-list')->name('expenses');
    Volt::route('/expenses/create', 'add-expense-form')->name('expenses.create');
    Volt::route('/expenses/{expense}/edit', 'edit-expense-form')->name('expenses.edit');
    Volt::route('/balances', 'balances-overview')->name('balances');
    Volt::route('/settle', 'settle-up-view')->name('settle');
    Volt::route('/settings', 'group-settings-view')->name('settings');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
