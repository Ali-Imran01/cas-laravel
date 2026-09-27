<?php

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Settings\Models\Setting;
use App\Domain\Settings\Support\EmailTemplates;
use Database\Seeders\AccessSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(AccessSeeder::class));

it('is closed to everyone without settings permissions', function (string $role) {
    $this->actingAs(user_with($role))->get('/settings/email')->assertForbidden();
    $this->actingAs(user_with($role))->put('/settings/email/invite', [])->assertForbidden();
})->with(['staff', 'hr_officer', 'dept_head']);

it('lists every template with the shipped wording in both languages until overridden', function () {
    $this->actingAs(user_with('super_admin'))->get('/settings/email')->assertOk()->assertInertia(fn (Assert $p) => $p
        ->component('Settings/EmailTemplates')
        ->has('templates.invite.en.subject')->has('templates.invite.ms.subject')
        ->has('templates', 7)
        ->where('placeholders.invite', ['staff_id', 'days'])
        ->where('can.update', true));
});

it('saves an override for one template without touching the others', function () {
    $admin = user_with('super_admin');
    $data = ['invite' => [
        'en' => ['subject' => 'Welcome aboard', 'body' => "Hi :staff_id.\nYou have :days days."],
        'ms' => ['subject' => 'Selamat datang', 'body' => "Hai :staff_id.\nAnda ada :days hari."],
    ]];

    $this->actingAs($admin)->put('/settings/email/invite', $data)->assertSessionHasNoErrors();

    $stored = Setting::find('email_templates')->value;
    expect($stored['invite']['en']['subject'])->toBe('Welcome aboard');

    $this->actingAs($admin)->get('/settings/email')->assertInertia(fn (Assert $p) => $p
        ->where('templates.invite.en.subject', 'Welcome aboard')
        ->where('templates.mfa_code.en.subject', app(EmailTemplates::class)->current('mfa_code')['en']['subject'])); // untouched
});

it('audits an email template change', function () {
    $admin = user_with('super_admin');

    $this->actingAs($admin)->put('/settings/email/mfa_code', ['mfa_code' => [
        'en' => ['subject' => 'Your code', 'body' => 'Code: :code'],
        'ms' => ['subject' => 'Kod anda', 'body' => 'Kod: :code'],
    ]]);

    $log = AuditLog::where('action', 'UPDATE')->where('description', 'Updated the "mfa_code" email template')->sole();
    expect($log->actor_id)->toBe($admin->id);
});

it('validates every field and rejects an unknown template key', function () {
    $admin = user_with('super_admin');

    $this->actingAs($admin)->put('/settings/email/invite', ['invite' => ['en' => ['subject' => ''], 'ms' => ['subject' => 'x', 'body' => 'y']]])
        ->assertSessionHasErrors(['invite.en.subject', 'invite.en.body']);

    $this->actingAs($admin)->put('/settings/email/not_a_template', ['not_a_template' => ['en' => ['subject' => 'x', 'body' => 'y'], 'ms' => ['subject' => 'x', 'body' => 'y']]])
        ->assertSessionHasErrors('template');
});

it('substitutes placeholders and keeps the action button and comment out of the editable text', function () {
    app(EmailTemplates::class)->save('approval_rejected', [
        'en' => ['subject' => 'No go: :reference', 'body' => 'Because: :summary'],
        'ms' => ['subject' => 'Tidak: :reference', 'body' => 'Sebab: :summary'],
    ]);

    $rendered = app(EmailTemplates::class)->render('approval_rejected', 'en', ['reference' => 'REQ-0007', 'summary' => 'Reactivate STF-1']);

    expect($rendered['subject'])->toBe('No go: REQ-0007')->and($rendered['lines'])->toBe(['Because: Reactivate STF-1']);
});
