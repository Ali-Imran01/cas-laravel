<?php

namespace App\Domain\Approvals\Handlers;

/** Maps a workflow code to what its approval does. Codes without a handler are "external": approved or rejected, then reported back. */
class HandlerRegistry
{
    /** @var list<class-string<WorkflowHandler>> */
    private const HANDLERS = [
        RoleChangeHandler::class,
        NewAccountHandler::class,
        AppAccessHandler::class,
        ReactivationHandler::class,
        TransferHandler::class,
        NewRoleHandler::class,
    ];

    public function for(string $code): ?WorkflowHandler
    {
        foreach (self::HANDLERS as $class) {
            $handler = app($class);

            if ($handler->code() === $code) {
                return $handler;
            }
        }

        return null;
    }

    /** @return list<string> */
    public function codes(): array
    {
        return array_map(fn (string $class) => app($class)->code(), self::HANDLERS);
    }
}
