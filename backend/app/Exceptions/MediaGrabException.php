<?php

namespace App\Exceptions;

use Exception;
use Symfony\Component\HttpFoundation\Response;

class MediaGrabException extends Exception
{
    protected string $errorCode = 'MEDIA_ERROR';
    protected int $statusCode = Response::HTTP_BAD_REQUEST;

    public function __construct(string $message = '', string $errorCode = 'MEDIA_ERROR', int $statusCode = Response::HTTP_BAD_REQUEST, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous);
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}
