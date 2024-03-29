<?php

namespace ManCurl;

use Psr\Http\Message\ResponseInterface;

/**
 * Collect the response from Guzzle Request
 */
class Response
{
    private ?ResponseInterface $httpResponse;

    public function __construct()
    {
    }

    /**
     * Gets the response reason phrase associated with the status code.
     */
    public function isOk(): bool
    {
        $httpResponse = $this->getHttpResponse();

        if (!$httpResponse instanceof ResponseInterface) {
            return false;
        }

        return 'OK' === $httpResponse->getReasonPhrase();
    }

    /**
     * Gets the response status code.
     */
    public function getCode(): int
    {
        $httpResponse = $this->getHttpResponse();

        if (!$httpResponse instanceof ResponseInterface) {
            return 0;
        }

        return $httpResponse->getStatusCode();
    }

    /**
     * Representation of an outgoing, server-side response.
     */
    public function getHttpResponse(): ?ResponseInterface
    {
        if (!$this->httpResponse instanceof ResponseInterface) {
            return null;
        }

        return $this->httpResponse;
    }

    /**
     * Set the HTTP response.
     */
    public function setHttpResponse(ResponseInterface $response): self
    {
        $this->httpResponse = $response;

        return $this;
    }

    /**
     * Create response as object.
     *
     * @throws \JsonException
     */
    public function getObjectResponse(): ?object
    {
        $httpResponse = $this->getHttpResponse();

        if (!$httpResponse instanceof ResponseInterface) {
            return null;
        }

        if (
            $httpResponse->hasHeader('content-type')
            && preg_match(Utils::JSON_PATTERN, $httpResponse->getHeaderLine('content-type'))
        ) {
            $decode = Utils::jsonDecode($this->getRawResponse());

            if (\is_object($decode)) {
                return $decode;
            }
        }

        return null;
    }

    /**
     * Get raw response.
     */
    public function getRawResponse(): string
    {
        $httpResponse = $this->getHttpResponse();

        if (!$httpResponse instanceof ResponseInterface) {
            return '';
        }

        return (string) $httpResponse->getBody();
    }

    /**
     * Get response as array
     */
    public function getArrayResponse(): array
    {
        return Utils::toArray($this->getRawResponse());
    }
}
