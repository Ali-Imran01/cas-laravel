<?php

use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('renders the dashboard page for a signed-in user', function () {
    $this->actingAs(User::factory()->create())->get('/')
        ->assertInertia(fn (Assert $page) => $page->component('Dashboard')->has('kpis'));
});

it('renders the login page', function () {
    $this->get('/login')->assertInertia(fn (Assert $page) => $page->component('Login'));
});
