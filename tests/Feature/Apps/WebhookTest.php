<?php

use App\Domain\Identity\Models\User;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

function webhook_app(): array
{
    $admin = User::factory()->create();
    $admin->assignRole('super_admin');
    [$app] = sso_app();

    return [$admin, $app];
}

it('sets a webhook and generates a signing secret the first time, shown only once', function () {
    [$admin, $app] = webhook_app();

    $this->actingAs($admin)->put("/apps/$app->id/webhook", ['webhook_url' => 'https://app.example.com/hooks/cas'])
        ->assertSessionHasNoErrors()->assertSessionHas('webhookSecret');

    expect($app->refresh()->webhook_url)->toBe('https://app.example.com/hooks/cas')->and($app->webhook_secret)->not->toBeNull();
});

it('keeps the same secret across an ordinary URL update', function () {
    [$admin, $app] = webhook_app();
    $this->actingAs($admin)->put("/apps/$app->id/webhook", ['webhook_url' => 'https://app.example.com/hooks/cas']);
    $secret = $app->refresh()->webhook_secret;

    $this->actingAs($admin)->put("/apps/$app->id/webhook", ['webhook_url' => 'https://app.example.com/hooks/cas/v2'])
        ->assertSessionHasNoErrors()->assertSessionMissing('webhookSecret');

    expect($app->refresh()->webhook_secret)->toBe($secret);
});

it('clears the url and the secret together', function () {
    [$admin, $app] = webhook_app();
    $this->actingAs($admin)->put("/apps/$app->id/webhook", ['webhook_url' => 'https://app.example.com/hooks/cas']);

    $this->actingAs($admin)->put("/apps/$app->id/webhook", ['webhook_url' => null])->assertSessionHasNoErrors();

    expect($app->refresh()->webhook_url)->toBeNull()->and($app->webhook_secret)->toBeNull();
});

it('rejects a webhook url like it rejects a redirect uri', function (string $url) {
    [$admin, $app] = webhook_app();

    $this->actingAs($admin)->put("/apps/$app->id/webhook", ['webhook_url' => $url])->assertSessionHasErrors('webhook_url');
})->with([
    'plain http' => ['http://app.example.com/hooks'],
    'credentials' => ['https://user:pass@app.example.com/hooks'],
    'fragment' => ['https://app.example.com/hooks#x'],
    'wildcard' => ['https://*.example.com/hooks'],
]);

it('mints a new webhook secret on request, invalidating the old one', function () {
    [$admin, $app] = webhook_app();
    $this->actingAs($admin)->put("/apps/$app->id/webhook", ['webhook_url' => 'https://app.example.com/hooks/cas']);
    $old = $app->refresh()->webhook_secret;

    $this->actingAs($admin)->post("/apps/$app->id/webhook/secret")->assertSessionHasNoErrors()->assertSessionHas('webhookSecret');

    expect($app->refresh()->webhook_secret)->not->toBe($old);
});

it('refuses to rotate a webhook secret before a url is set', function () {
    [$admin, $app] = webhook_app();

    $this->actingAs($admin)->post("/apps/$app->id/webhook/secret")->assertSessionHasErrors('app');
});

it('is closed to anyone who cannot manage the app', function () {
    [, $app] = webhook_app();
    $staff = User::factory()->create();

    $this->actingAs($staff)->put("/apps/$app->id/webhook", ['webhook_url' => 'https://app.example.com/hooks'])->assertForbidden();
    $this->actingAs($staff)->post("/apps/$app->id/webhook/secret")->assertForbidden();
});
