<?php

namespace App\Exceptions;

class SsrfDetectedException extends MediaGrabException
{
    public function __construct(string $message = 'Access to this host or address is prohibited for security reasons.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'SECURITY_VIOLATION', 403, $previous);
    }
}
