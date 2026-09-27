<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use Illuminate\Support\Facades\DB;

class UpdateApplication
{
    /** @param array{name: string, environment: string, homepage_url?: string|null, color?: string|null, allowed_scopes: list<string>, redirect_uris: list<string>} $data */
    public function __invoke(Application $app, array $data): void
    {
        DB::transaction(function () use ($app, $data) {
            $client = $app->client;
            $before = $this->snapshot($app, $client->redirect_uris);

            $app->update([
                'name' => $data['name'],
                'environment' => $data['environment'],
                'homepage_url' => $data['homepage_url'] ?? null,
                'color' => $data['color'] ?? null,
                'allowed_scopes' => $data['allowed_scopes'],
            ]);
            $client->forceFill(['name' => $data['name'], 'redirect_uris' => $data['redirect_uris']])->save();

            [$old, $new] = Audit::diff($before, $this->snapshot($app, $data['redirect_uris']));
            if ($new !== []) {
                Audit::record('UPDATE', "Updated app {$app->code}", $app, $old, $new);
            }
        });
    }

    /**
     * @param  list<string>  $redirects
     * @return array<string, mixed>
     */
    private function snapshot(Application $app, array $redirects): array
    {
        return [
            'name' => $app->name, 'environment' => $app->environment->value, 'homepage_url' => $app->homepage_url,
            'color' => $app->color, 'allowed_scopes' => $app->allowed_scopes, 'redirect_uris' => $redirects,
        ];
    }
}
