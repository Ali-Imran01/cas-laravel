<?php

namespace App\Domain\Audit;

/** Per-request (scoped) override of who counts as the actor, for work that runs outside a web session. */
final class AuditContext
{
    public ?int $actorId = null;
}
