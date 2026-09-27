<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use Illuminate\Support\Str;

/** Sets or clears the app's webhook URL. A secret is generated the first time a URL is set, and kept until the URL is cleared. */
class SetWebhook
{
    /** @return string|null the new secret, shown only this once; null when nothing was generated */
    public function __invoke(Application $app, ?string $url): ?string
    {
        $before = $app->webhook_url;
        $shown = null;

        if ($url === null) {
            $app->update(['webhook_url' => null, 'webhook_secret' => null]);
        } else {
            $data = ['webhook_url' => $url];
            if ($app->webhook_secret === null) {
                $shown = Str::random(40);
                $data['webhook_secret'] = $shown;
            }
            $app->update($data);
        }

        if ($before !== $app->webhook_url) {
            Audit::record('UPDATE', "Set the webhook of app {$app->code}", $app, ['webhook_url' => $before], ['webhook_url' => $app->webhook_url]);
        }

        return $shown;
    }
}
