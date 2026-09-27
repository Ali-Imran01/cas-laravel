<?php

namespace App\Domain\Apps\Actions;

use App\Domain\Apps\Models\Application;
use App\Domain\Audit\Audit;
use Laravel\Passport\ClientRepository;

class RotateClientSecret
{
    public function __construct(private readonly ClientRepository $clients) {}

    /** @return string the new secret; only the hash is stored, so this is the one chance to read it */
    public function __invoke(Application $app): string
    {
        $client = $app->client;
        $this->clients->regenerateSecret($client);
        $app->forceFill(['secret_rotated_at' => now()])->save();

        Audit::record('ROTATE_SECRET', "Rotated the client secret of app {$app->code}", $app);

        return (string) $client->plainSecret;
    }
}
