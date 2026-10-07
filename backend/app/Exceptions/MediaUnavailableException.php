<?php

namespace App\Exceptions;

class MediaUnavailableException extends MediaGrabException
{
    public function __construct(string $message = 'The requested media is unavailable, private, deleted, or requires authorization.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'MEDIA_UNAVAILABLE', 422, $previous);
    }
}
