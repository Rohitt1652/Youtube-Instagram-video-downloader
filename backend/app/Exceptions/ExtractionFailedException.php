<?php

namespace App\Exceptions;

class ExtractionFailedException extends MediaGrabException
{
    public function __construct(string $message = 'Unable to extract media information from the provided URL.', ?\Throwable $previous = null)
    {
        parent::__construct($message, 'EXTRACTION_FAILED', 422, $previous);
    }
}
