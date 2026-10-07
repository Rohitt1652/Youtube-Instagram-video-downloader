<?php

namespace App\Exceptions;

class MediaLimitExceededException extends MediaGrabException
{
    public function __construct(string $message = 'This media exceeds the allowed download limit.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'LIMIT_EXCEEDED', 422, $previous);
    }
}
