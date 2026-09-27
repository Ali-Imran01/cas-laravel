<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Enums\AppEnvironment;
use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\Token;

/** Disabling an app also ends every session it holds: its client is revoked and all its tokens die. */
class SetApplicationStatus
{
    /** @throws ValidationException */
    public function disable(Application $app): void
    {
        if ($app->status === AppStatus::Disabled) {
            throw ValidationException::withMessages(['app' => __('cas.apps.already_disabled')]);
        }

        $from = $app->status;

        DB::transaction(function () use ($app) {
            $app->forceFill(['status' => AppStatus::Disabled, 'disabled_at' => now()])->save();
            $app->client->forceFill(['revoked' => true])->save();

            $app->client->tokens()->with('refreshToken')->each(function (Token $token): void {
                $token->refreshToken?->revoke();
                $token->revoke();
            });
        });

        Audit::record('DISABLE', "Disabled app {$app->code}", $app, ['status' => $from->value], ['status' => 'disabled']);
    }

    /** @throws ValidationException */
    public function enable(Application $app): void
    {
        if ($app->status !== AppStatus::Disabled) {
            throw ValidationException::withMessages(['app' => __('cas.apps.not_disabled')]);
        }

        $status = $app->environment === AppEnvironment::Sandbox ? AppStatus::Sandbox : AppStatus::Active;

        DB::transaction(function () use ($app, $status) {
            $app->forceFill(['status' => $status, 'disabled_at' => null])->save();
            $app->client->forceFill(['revoked' => false])->save();
        });

        Audit::record('ENABLE', "Enabled app {$app->code}", $app, ['status' => 'disabled'], ['status' => $status->value]);
    }
}
