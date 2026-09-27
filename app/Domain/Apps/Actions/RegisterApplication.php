<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Enums\AppEnvironment;
use App\Domain\Apps\Enums\AppStatus;
use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Laravel\Passport\ClientRepository;

class RegisterApplication
{
    public function __construct(private readonly ClientRepository $clients) {}

    /**
     * @param  array{code: string, name: string, environment: string, homepage_url?: string|null, color?: string|null, allowed_scopes: list<string>, redirect_uris: list<string>}  $data
     * @return array{0: Application, 1: string} the app and its client secret, which is only ever available now
     */
    public function __invoke(User $actor, array $data): array
    {
        return DB::transaction(function () use ($actor, $data) {
            $client = $this->clients->createAuthorizationCodeGrantClient($data['name'], $data['redirect_uris'], confidential: true);
            $secret = (string) $client->plainSecret;

            $app = Application::create([
                'oauth_client_id' => $client->getKey(),
                'code' => $data['code'],
                'name' => $data['name'],
                'environment' => $data['environment'],
                'status' => $data['environment'] === AppEnvironment::Sandbox->value ? AppStatus::Sandbox : AppStatus::Active,
                'homepage_url' => $data['homepage_url'] ?? null,
                'color' => $data['color'] ?? null,
                'allowed_scopes' => $data['allowed_scopes'],
                'owner_user_id' => $actor->id,
            ]);

            Audit::record('CREATE', "Registered app {$app->code}", $app, [], [
                'code' => $app->code, 'name' => $app->name, 'environment' => $data['environment'],
                'allowed_scopes' => $app->allowed_scopes, 'redirect_uris' => $data['redirect_uris'], 'client_id' => $client->getKey(),
            ]);

            return [$app, $secret];
        });
    }
}
