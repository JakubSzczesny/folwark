<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Wiadomości oznaczone tym interfejsem trafiają do kolejki "async" i są obsługiwane przez worker.
 */
interface AsyncMessage
{
}
