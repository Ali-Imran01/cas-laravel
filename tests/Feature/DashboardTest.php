<?php

use App\Domain\Apps\Models\Application;
use App\Domain\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

it('counts connected apps as active and sandbox ones, never a disabled one', function () {
    [$active] = sso_app(['code' => 'a']);
    [$sandbox] = sso_app(['code' => 'b', 'status' => 'sandbox']);
    [$disabled] = sso_app(['code' => 'c', 'status' => 'disabled']);

    $this->actingAs(User::factory()->create())->get('/')
        ->assertInertia(fn (Assert $p) => $p->where('kpis.apps', 2));

    expect(Application::count())->toBe(3); // sanity: all three exist, only 2 count as connected
});
