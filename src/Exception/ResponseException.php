<?php

namespace ManCurl\Exception;

use ManCurl\Utils;

class ResponseException extends \Exception
{
    public function __construct(
        string $message = '',
        private string $rawError = '',
        int $code = 0,
        private ?string $errorType = null
    ) {
        parent::__construct($message, $code);
    }

    /**
     * Get array of response
     */
    public function toArray(): array
    {
        return '' !== $this->getRawError() ? Utils::toArray($this->getRawError()) : [];
    }

    /**
     * Get error type
     */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    /**
     * Get raw response if hit unknown error by `ResponseHandler`,
     * this method used for logging of what the error
     */
    public function getRawError(): string
    {
        return $this->rawError;
    }
}
