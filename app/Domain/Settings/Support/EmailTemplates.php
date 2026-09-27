<?php

namespace App\Domain\Settings\Support;

use App\Domain\Settings\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * The subject and body an administrator may rewrite for each transactional email, per language. A
 * template's action button (its label and URL) and, for an approval outcome, the reviewer's own comment
 * are never part of the editable text: they are appended by the notification itself, after the body.
 */
class EmailTemplates
{
    private const CACHE_KEY = 'settings:email_templates';

    public const LOCALES = ['en', 'ms'];

    /** @var array<string, list<string>> template key => the :placeholders its body/subject may use */
    public const PLACEHOLDERS = [
        'invite' => ['staff_id', 'days'],
        'mfa_code' => ['code', 'minutes'],
        'approval_needed' => ['reference', 'summary', 'requester'],
        'approval_overdue' => ['reference', 'summary', 'requester'],
        'approval_approved' => ['reference', 'summary'],
        'approval_rejected' => ['reference', 'summary'],
        'approval_info_requested' => ['reference', 'summary'],
    ];

    /** @var array<string, array<string, array{subject: string, body: string}>> template key => locale => shipped wording */
    private const DEFAULTS = [
        'invite' => [
            'en' => ['subject' => 'You have been invited to CAS', 'body' => "An account was created for you (staff ID :staff_id). Set your password to activate it.\nThis link expires in :days days."],
            'ms' => ['subject' => 'Anda dijemput ke CAS', 'body' => "Satu akaun telah dicipta untuk anda (ID staf :staff_id). Tetapkan kata laluan anda untuk mengaktifkannya.\nPautan ini tamat dalam :days hari."],
        ],
        'mfa_code' => [
            'en' => ['subject' => 'Your sign-in code', 'body' => "Your sign-in code is :code. It expires in :minutes minutes.\nIf this was not you, change your password."],
            'ms' => ['subject' => 'Kod log masuk anda', 'body' => "Kod log masuk anda ialah :code. Kod ini tamat dalam :minutes minit.\nJika ini bukan anda, tukar kata laluan anda."],
        ],
        'approval_needed' => [
            'en' => ['subject' => 'Approval needed: :reference', 'body' => ":summary\nRequested by :requester."],
            'ms' => ['subject' => 'Kelulusan diperlukan: :reference', 'body' => ":summary\nDimohon oleh :requester."],
        ],
        'approval_overdue' => [
            'en' => ['subject' => 'Overdue: :reference is waiting for you', 'body' => ":summary\nRequested by :requester."],
            'ms' => ['subject' => 'Tertunggak: :reference menunggu anda', 'body' => ":summary\nDimohon oleh :requester."],
        ],
        'approval_approved' => [
            'en' => ['subject' => 'Approved: :reference', 'body' => ':summary'],
            'ms' => ['subject' => 'Diluluskan: :reference', 'body' => ':summary'],
        ],
        'approval_rejected' => [
            'en' => ['subject' => 'Rejected: :reference', 'body' => ':summary'],
            'ms' => ['subject' => 'Ditolak: :reference', 'body' => ':summary'],
        ],
        'approval_info_requested' => [
            'en' => ['subject' => 'More information needed: :reference', 'body' => ':summary'],
            'ms' => ['subject' => 'Maklumat tambahan diperlukan: :reference', 'body' => ':summary'],
        ],
    ];

    /** @return list<string> */
    public function keys(): array
    {
        return array_keys(self::DEFAULTS);
    }

    /** The shipped wording, layered with whatever an administrator has overridden, for every language.
     *
     * @return array<string, array{subject: string, body: string}>
     */
    public function current(string $key): array
    {
        $overrides = $this->stored()[$key] ?? [];

        return collect(self::LOCALES)->mapWithKeys(fn (string $locale) => [$locale => $overrides[$locale] ?? self::DEFAULTS[$key][$locale]])->all();
    }

    /** @param array<string, array{subject: string, body: string}> $data one entry per locale */
    public function save(string $key, array $data): void
    {
        if (! array_key_exists($key, self::DEFAULTS)) {
            return;
        }

        $all = $this->stored();
        $all[$key] = collect(self::LOCALES)->mapWithKeys(fn (string $locale) => [$locale => [
            'subject' => (string) ($data[$locale]['subject'] ?? self::DEFAULTS[$key][$locale]['subject']),
            'body' => (string) ($data[$locale]['body'] ?? self::DEFAULTS[$key][$locale]['body']),
        ]])->all();

        Setting::query()->updateOrCreate(['key' => 'email_templates'], ['value' => $all]);
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * The subject and body lines to actually send, with every :placeholder replaced.
     *
     * @param  array<string, string|int>  $vars
     * @return array{subject: string, lines: list<string>}
     */
    public function render(string $key, string $locale, array $vars): array
    {
        $template = $this->current($key)[$locale] ?? $this->current($key)['en'];
        $replace = collect($vars)->mapWithKeys(fn ($value, $name) => [":$name" => (string) $value])->all();

        $lines = explode("\n", strtr($template['body'], $replace));

        return [
            'subject' => strtr($template['subject'], $replace),
            'lines' => array_values(array_filter($lines, fn (string $line) => trim($line) !== '')),
        ];
    }

    /** @return array<string, array<string, mixed>> only the templates an administrator has actually overridden */
    private function stored(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $setting = Setting::query()->where('key', 'email_templates')->first();

            return $setting === null ? [] : ($setting->value ?? []);
        });
    }
}
