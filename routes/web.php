<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Phase 0 placeholders: real auth (Phase 1) and data (later phases) replace these props.
Route::get('/login', fn () => Inertia::render('Login'))->name('login');

Route::get('/', fn () => Inertia::render('Dashboard', [
    'kpis' => ['users' => 0, 'apps' => 0, 'pendingApprovals' => 0, 'signInsToday' => 0],
    'signIns' => [],
]))->name('dashboard');
