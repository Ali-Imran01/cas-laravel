<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Laravel\Passport\RefreshToken;

class DeleteApplication
{
    /** @throws ValidationException an app must be disabled (all tokens revoked) before it can be removed */
    public function __invoke(Application $app): void
    {
        if ($app->status !== AppStatus::Disabled) {
            throw ValidationException::withMessages(['app' => __('cas.apps.disable_first')]);
        }

        DB::transaction(function () use ($app) {
            $client = $app->client;
            // Removing the client row removes the application and its access rules with it (cascade).
            RefreshToken::query()->whereIn('access_token_id', $client->tokens()->select('id'))->delete();
            $client->tokens()->delete();
            $client->authCodes()->delete();
            $client->delete();
        });

        Audit::record('DELETE', "Deleted app {$app->code}", $app, ['code' => $app->code, 'name' => $app->name]);
    }
}
