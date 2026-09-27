<?php

namespace App\Domain\Settings\Actions;

use App\Domain\Audit\Audit;
use App\Domain\Settings\Support\EmailTemplates;

class UpdateEmailTemplate
{
    public function __construct(private readonly EmailTemplates $templates) {}

    /** @param array<string, array{subject: string, body: string}> $data one entry per locale */
    public function __invoke(string $key, array $data): void
    {
        $before = $this->templates->current($key);
        $this->templates->save($key, $data);
        $after = $this->templates->current($key);

        [$old, $new] = Audit::diff($before, $after);
        if ($new !== []) {
            Audit::record('UPDATE', "Updated the \"$key\" email template", null, $old, $new);
        }
    }
}
