<?php

namespace ManCurl;

use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\MultipartStream;
use ManCurl\Exception\RequestException;
use ManCurl\Exception\ResponseModelException;
use ManCurl\ResponseInterface as ManCurlResponseInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Request
{
    /**
     * Extra Guzzle client options for this request.
     */
    private array $options = [];

    /**
     * Identifier for we want use cookie for this request or not.
     */
    private bool $useCookie = true;

    /**
     * Http method for request.
     */
    private ?string $method = null;

    /**
     * @param string $url endpoint URL for this request
     */
    public function __construct(private Client $client, private string $url)
    {
    }

    /**
     * Add query param to request,
     * overwriting any previous value if has same key.
     */
    public function addParam(string $key, mixed $value): self
    {
        $this->options['query'][$key] = Utils::paramParser($value);

        return $this;
    }

    /**
     * Add query param to request,
     * overwriting any previous value if has same key.
     */
    public function addParams(array $params): self
    {
        foreach ($params as $key => $value) {
            $this->options['query'][$key] = Utils::paramParser($value);
        }

        return $this;
    }

    /**
     * Add POST param to request,
     * overwriting any previous value if has same key.
     *
     * can't use with `addPostJson()` and `addMultipart()` at the same time.
     */
    public function addPost(string $key, mixed $value): self
    {
        $this->options['form_params'][$key] = Utils::paramParser($value);

        return $this;
    }

    /**
     * Add POST param to request,
     * overwriting any previous value if has same key.
     *
     * can't use with `addPostJson()` and `addMultipart()` at the same time.
     */
    public function addPosts(array $params): self
    {
        /** @var mixed $value */
        foreach ($params as $key => $value) {
            $this->options['form_params'][$key] = Utils::paramParser($value);
        }

        return $this;
    }

    /**
     * Add POST json data to request.
     * can't use with `addPost()` and `addMultipart()` at the same time.
     *
     * @throws RequestException
     */
    public function addPostJson(string|array|object $value): self
    {
        if (!\is_array($value) && !\is_object($value) && !Utils::isJson($value)) {
            throw new RequestException('Only a valid json string, array and object is accepted');
        }

        if (Utils::isJson($value) && \is_string($value)) {
            $value = \GuzzleHttp\Utils::jsonDecode($value);
        }

        $this->options['json'] = $value;

        return $this;
    }

    /**
     * Add a multipart data.
     * can't use with `addPost()` and `addPostJson()` at the same time.
     *
     * @param string                          $name     the form field name
     * @param StreamInterface|resource|string $contents the data to use in the form element
     * @param array                           $headers  associative array of custom headers to use with the form element
     * @param string|null                     $filename string to send as the filename in the part
     */
    public function addMultipart(
        string $name,
        $contents,
        array $headers = [],
        ?string $filename = null
    ): self {
        $this->options['multipart'][] = [
            'name'     => $name,
            'contents' => $contents,
            'headers'  => $headers,
            'filename' => $filename,
        ];

        return $this;
    }

    /**
     * Set custom method to this request. Like `DELETE`, `PATCH`, etc.
     * The default method is `GET` and `POST` (if post data is assigned).
     */
    public function setMethod(string $value): self
    {
        $this->method = $value;

        return $this;
    }

    /**
     * Add header to request, if the input arrays have the same string keys,
     * then the later value for that key will overwrite the previous one.
     *
     * @param string|bool|int $value
     */
    public function addHeader(string $key, $value): self
    {
        $this->options['headers'][$key] = $value;

        return $this;
    }

    /**
     * Add header to request, if the input arrays have the same string keys,
     * then the later value for that key will overwrite the previous one.
     */
    public function addHeaders(array $headers): self
    {
        /** @var mixed $value */
        foreach ($headers as $key => $value) {
            $this->options['headers'][$key] = $value;
        }

        return $this;
    }

    /**
     * Use default headers for request, default headers will
     * overwritten by `->addHeader()` or `->addHeaders()` if assigned.
     */
    public function useDefaultHeaders(bool $value): self
    {
        $this->client->useDefaultHeaders($value);

        return $this;
    }

    /**
     * Add cookie to request.
     */
    public function addCookie(string $name, ?string $value, ?string $domain): self
    {
        $cookies = $this->client->getCookies();
        $cookies->setCookie(new SetCookie([
            'Name'   => $name,
            'Value'  => $value,
            'Domain' => $domain,
        ]));

        return $this;
    }

    /**
     * Disable cookie for request.
     */
    public function disableCookies(): self
    {
        $this->useCookie = false;

        return $this;
    }

    /**
     * Set the extra Guzzle client options for this single request.
     *
     * @see https://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function addClientOption(string $key, mixed $value): self
    {
        $this->options[$key] = $value;

        return $this;
    }

    /**
     * Filtered request body of request and set content-type.
     *
     * for `multipart` post body we use psr7 with custom boundary,
     * for `json` and `form_params` is use client body options.
     */
    private function getRequestBody(array $headers): ?StreamInterface
    {
        $body = null;

        if (!empty($this->options['form_params']) && !Utils::contentTypeMatch($headers)) {
            $this->addHeader('content-type', 'application/x-www-form-urlencoded; charset=UTF-8');
        }

        if (!empty($this->options['json']) && !Utils::contentTypeMatch($headers)) {
            $this->addHeader('content-type', 'application/json; charset=UTF-8');
        }

        if (!empty($this->options['multipart'])) {
            $multipart = (array) $this->options['multipart'];
            $body      = new MultipartStream($multipart, Utils::generateMultipartBoundary());
            // Remove the option so that they are not doubly-applied.
            unset($this->options['multipart']);
        }

        return $body;
    }

    /**
     * Build HTTP request to pass into guzzle request client.
     *
     * @throws RequestException
     */
    private function buildHttpRequest(): RequestInterface
    {
        // Merge client options and request option.
        $this->options = Utils::mergeCaseless(
            $this->client->getClientOptions(),
            $this->options
        );

        $multiPost = ['json', 'form_params', 'multipart'];

        if (\count(array_intersect(array_keys($this->options), $multiPost)) > 1) {
            throw new RequestException(
                'You cannot use form_params, json and multipart at the same time.'
            );
        }

        // use setMethod() if defined
        if (null === $this->method) {
            if (
                isset($this->options['form_params'])
                || isset($this->options['json'])
                || isset($this->options['multipart'])
            ) {
                $this->method = 'POST';
            } else {
                $this->method = 'GET';
            }
        }

        /** @var array $headers */
        $headers = $this->options['headers'] ?? [];

        // merge with client default headers if use default headers
        if ($this->client->useDefaultHeaders) {
            $headers = Utils::mergeCaseless(
                $this->client->defaultHeaders,
                $headers
            );
        }

        // set header options with new one
        $this->options['headers'] = $headers;
        $this->options['cookies'] = $this->useCookie ? $this->client->getCookies() : false;

        $body = $this->getRequestBody($headers);

        return new \GuzzleHttp\Psr7\Request(
            method: $this->method,
            uri: $this->url,
            body: $body,
            version: '2.0' // Protocol version
        );
    }

    /**
     * Add guzzle middleware stack
     *
     * @param callable $middleware Middleware function
     */
    public function middleware(callable $middleware): self
    {
        $this->client->addOption('handler', $middleware);

        return $this;
    }

    /**
     * Make request with guzzle client.
     */
    public function makeRequest(): ResponseInterface
    {
        $response = $this->client->guzzleClient()->send(
            $this->buildHttpRequest(),
            $this->options,
        );

        // reset temporary client options to make use default headers always true,
        // after (maybe) in request we has set disable default headers
        $this->client->useDefaultHeaders(true);

        return $response;
    }

    /**
     * Make request with guzzle client.
     */
    public function makeAsyncRequest(): PromiseInterface
    {
        $promise = $this->client->guzzleClient()->sendAsync(
            $this->buildHttpRequest(),
            $this->options,
        );

        $this->client->useDefaultHeaders(true);

        return $promise;
    }

    /**
     * Perform the request and get its raw HTTP response.
     */
    public function getHttpResponse(): ResponseInterface
    {
        return $this->makeRequest();
    }

    /**
     * Get raw response body.
     */
    public function getRawResponse(): string
    {
        return (string) $this->makeRequest()->getBody();
    }

    /**
     * Get direct response body / tidak menggunakan mapping.
     *
     * @param bool $assoc when FALSE, decode to object instead of associative array
     *
     * @throws \JsonException
     *
     * @return mixed If the response content-type is json:
     *               Returns the json decoder's return value
     *               If the response content-type is something else:
     *               Returns the original raw response.
     *               If the response content-type cannot be determined:
     *               Returns the original raw response.
     */
    public function getResponse(bool $assoc = false): mixed
    {
        $httpResponse = $this->makeRequest();
        $response     = (string) $httpResponse->getBody();

        if ($httpResponse->hasHeader('content-type')) {
            if (preg_match(Utils::JSON_PATTERN, $httpResponse->getHeaderLine('content-type'))) {
                return Utils::jsonDecode($response, $assoc);
            }

            return $response;
        }

        return $response;
    }

    /**
     * Perform the request and map to `Response` with specific method
     *
     * i.e: type hint `TestResponse`
     *
     * ```php
     * mapResponse(function(TestResponse $response) {
     *      return $response->testMethod();
     * });
     * ```
     *
     * @template T of object
     * @template U of ManCurlResponseInterface
     *
     * @param \Closure(U): T $fn
     *
     * @psalm-return T
     *
     * @phpstan-return T
     *
     * @throws RequestException
     * @throws ResponseModelException
     */
    public function mapResponse(\Closure $fn): object
    {
        $reflectionFunction = new \ReflectionFunction($fn);
        $reflectionParams   = $reflectionFunction->getParameters()[0];
        $callerType         = $reflectionParams->getType();

        if (!$callerType instanceof \ReflectionNamedType) {
            throw new RequestException('Response destination class not found.');
        }

        // string of namespace class
        $responseClass = $callerType->getName();

        $response = new Response();
        // initialize `TheResponse`
        $responseObject = new $responseClass($response);

        // checking `TheResponse` is a child of `object`
        if (!$responseObject instanceof ManCurlResponseInterface) {
            throw new RequestException('Response destination class is not exists.');
        }

        // make request and set the HttpResponse to `TheResponse`,
        $httpResponse = $this->makeRequest();
        $response->setHttpResponse($httpResponse);

        try {
            /** @var U $responseObject */
            $dataTransferObject = $fn($responseObject);
        } catch (\TypeError $e) {
            throw new ResponseModelException($e);
        }

        // return the method of `TheResponse`
        return $dataTransferObject;
    }
}
