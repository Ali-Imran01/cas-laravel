<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Settings\Support\PolicySettings;

class UpdatePolicySettings
{
    public function __construct(private readonly PolicySettings $settings) {}

    /** @param array<string, int> $data */
    public function __invoke(array $data): void
    {
        $before = $this->settings->current();
        $this->settings->save($data);
        $after = $this->settings->current();

        [$old, $new] = Audit::diff($before, $after);
        if ($new !== []) {
            Audit::record('UPDATE', 'Updated security policy settings', null, $old, $new);
        }
    }
}
