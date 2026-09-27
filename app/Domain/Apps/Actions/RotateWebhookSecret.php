<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RotateWebhookSecret
{
    /** @return string the new secret; only the encrypted value is kept, so this is the one chance to read it
     *
     * @throws ValidationException
     */
    public function __invoke(Application $app): string
    {
        if ($app->webhook_url === null) {
            throw ValidationException::withMessages(['app' => __('cas.apps.no_webhook')]);
        }

        $secret = Str::random(40);
        $app->update(['webhook_secret' => $secret]);
        Audit::record('ROTATE_SECRET', "Rotated the webhook secret of app {$app->code}", $app);

        return $secret;
    }
}
