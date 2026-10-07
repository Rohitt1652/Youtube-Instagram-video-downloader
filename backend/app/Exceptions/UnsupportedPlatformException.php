<?php

namespace App\Exceptions;

class UnsupportedPlatformException extends MediaGrabException
{
    public function __construct(string $message = 'This URL is not supported. Only authorized YouTube and Instagram URLs are supported.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'UNSUPPORTED_URL', 422, $previous);
    }
}
