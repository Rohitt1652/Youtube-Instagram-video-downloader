<?php

namespace App\Exceptions;

class InvalidUrlException extends MediaGrabException
{
    public function __construct(string $message = 'The provided URL is invalid or malformed.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'INVALID_URL', 422, $previous);
    }
}
