<?php

namespace ManCurl;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\Utils as GuzzleUtils;
use InvalidArgumentException;
use ManCurl\Exception\RequestException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;

final class Request
{
    /**
     * An array of POST params.
     *
     * @var array
     */
    private $posts = [];

    /**
     * An array of HTTP headers to add to the request.
     *
     * @var array
     */
    private $headers = [];

    /**
     * Extra Guzzle client options for this request.
     *
     * @var array
     */
    private $clientOptions = [];

    /**
     * Custom method for this request.
     *
     * @var ?string
     */
    private $method;

    /**
     * Cached HTTP response object.
     *
     * @var ?ResponseInterface
     */
    private $httpResponse;

    /**
     * The constructor.
     *
     * @param string $url endpoint URL for this request
     */
    public function __construct(
        private Client $client,
        private string $url
    ) {
    }

    /**
     * Add query param to request, overwriting any previous value,
     * `addParams()` value will also be overwrite if has same key.
     *
     * @param mixed $value
     */
    public function addParam(string $key, $value): self
    {
        $this->clientOptions['query'][$key] = \is_bool($value) ? var_export($value, true) : $value;

        return $this;
    }

    /**
     * Add query params to request. `addParam()` will overwrite value if has same key.
     */
    public function addParams(array $params): self
    {
        // change to string if has boolean value
        $_params = [];
        foreach ($params as $key => $value) {
            if (\is_bool($value)) {
                $_params[$key] = var_export($value, true);
            } else {
                $_params[$key] = $value;
            }
        }

        $this->clientOptions['query'] = array_merge($_params, $this->clientOptions['query'] ?? []);

        return $this;
    }

    /**
     * Add POST param to request, overwriting any previous value,
     * `addPosts()` value will also be overwrite if has same key,
     * can't use with `addPost()`, `addPostJson()` and `addMultipart()` at the same time.
     *
     * @param mixed $value
     */
    public function addPost(string $key, $value): self
    {
        $this->posts['form_params'][$key] = \is_bool($value) ? var_export($value, true) : $value;

        return $this;
    }

    /**
     * Add query POST params to request. `addPost()` will overwrite value if has same key.
     */
    public function addPosts(array $params): self
    {
        // change to string if has boolean value
        $_params = [];
        foreach ($params as $key => $value) {
            if (\is_bool($value)) {
                $_params[$key] = var_export($value, true);
            } else {
                $_params[$key] = $value;
            }
        }

        $this->posts['form_params'] = array_merge($_params, $this->posts['form_params'] ?? []);

        return $this;
    }

    /**
     * Add POST json data to request. `addPost()` method will be overwrite if exist,
     * can't use with `addPost()`, `addPostJson()` and `addMultipart()` at the same time.
     *
     * @param string|array|object $value json string, array or object
     *
     * @throws RequestException
     */
    public function addPostJson($value): self
    {
        if (!\is_array($value) && !\is_object($value) && !Utils::isJson($value)) {
            throw new RequestException('Only a valid json string, array and object is accepted');
        }

        if (\is_array($value) || \is_object($value)) {
            try {
                $json = \GuzzleHttp\Utils::jsonEncode($value);
            } catch (\InvalidArgumentException $e) {
                throw new RequestException('Cannot encode value.');
            }
        } else {
            $json = $value;
        }

        $this->posts['json'] = $json;

        return $this;
    }

    /**
     * Add a multipart data.
     * can't use with `addPost()`, `addPostJson()` and `addMultipart()` at the same time.
     *
     * @param string                          $name     the form field name
     * @param StreamInterface|resource|string $contents the data to use in the form element
     * @param array                           $headers  optional associative array of custom headers to use with the form element
     * @param string|null                     $filename optional string to send as the filename in the part
     */
    public function addMultipart(string $name, $contents, array $headers = [], ?string $filename = null): self
    {
        $this->posts['multipart'][] = [
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
     * Add custom header to request, overwriting any previous or default header value,
     * `addHeaders()` value will also be overwrite if has same key.
     *
     * @param string|bool|int $value
     */
    public function addHeader(string $key, $value): self
    {
        $this->headers[$key] = $value;

        return $this;
    }

    /**
     * Add custom header to request, `addHeader()` will overwrite value if has same key.
     */
    public function addHeaders(array $headers): self
    {
        $this->headers = array_merge($headers, $this->headers);

        return $this;
    }

    /**
     * Use default headers for request.
     * value will overwrite if we use `->addHeader()` or `->addHeaders()` on this request
     */
    public function useDefaultHeaders(): self
    {
        $this->client->useDefaultHeaders(true);

        return $this;
    }

    /**
     * Add cookie to request.
     */
    public function addCookie(string $name, ?string $value, ?string $domain): self
    {
        $this->client->addCookie($name, $value, $domain);

        return $this;
    }

    /**
     * Disable cookie for request.
     */
    public function withoutCookie(): self
    {
        $this->client->withoutCookie();

        return $this;
    }

    /**
     * Set the extra Guzzle client options for this single request.
     *
     * @param mixed $value
     *
     * @see https://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function addClientOption(string $key, $value): self
    {
        $this->clientOptions[$key] = $value;

        return $this;
    }

    /**
     * Perform the request and get its raw HTTP response.
     *
     * @throws \InvalidArgumentException
     * @throws GuzzleException
     */
    public function getHttpResponse(): ResponseInterface
    {
        return $this->makeRequest();
    }

    /**
     * Get raw response body.
     *
     * @throws \InvalidArgumentException
     * @throws GuzzleException
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
     * @throws \InvalidArgumentException — if the JSON cannot be decoded
     * @throws InvalidArgumentException
     * @throws GuzzleException
     *
     * @return mixed If the response content-type is json:
     *               Returns the json decoder's return value
     *               If the response content-type is something else:
     *               Returns the original raw response.
     *               If the response content-type cannot be determined:
     *               Returns the original raw response.
     */
    public function getResponse(bool $assoc = false)
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
     * Convert the request's data into its HTTP POST body contents.
     *
     * @throws RequestException
     *
     * @return StreamInterface|resource|string|null `null` if GET request
     */
    private function getRequestBody()
    {
        $posts = $this->posts;

        if (\count($posts) > 1) {
            throw new RequestException('You cannot use '
            . 'form_params, json and multipart at the same time.');
        }

        // We have no POST data and no files.
        $body = null;

        if (isset($posts['form_params'])) {
            if (!Utils::contentTypeMatch($this->headers)) {
                $this->addHeader('content-type', 'application/x-www-form-urlencoded; charset=UTF-8');
            }

            $body = GuzzleUtils::streamFor(http_build_query($posts['form_params']));
            unset($posts['form_params']);
        }

        if (isset($posts['json'])) {
            if (!Utils::contentTypeMatch($this->headers)) {
                $this->addHeader('content-type', 'application/json; charset=UTF-8');
            }

            $body = $posts['json'];
            unset($posts['json']);
        }

        if (isset($posts['multipart'])) {
            $body = new MultipartStream(
                $posts['multipart'],
                Utils::generateMultipartBoundary()
            );
            unset($posts['multipart']);
        }

        return $body;
    }

    /**
     * Get request method.
     */
    private function getHttpMethod(): string
    {
        // Get method from setMethod()
        if (null !== $this->method) {
            return $this->method;
        }

        if (
            !isset($this->posts['form_params']) && !isset($this->posts['json']) && !isset($this->posts['multipart'])
        ) {
            $this->method = 'GET';
        } else {
            $this->method = 'POST';
        }

        return $this->method;
    }

    /**
     * Build HTTP request to pass into guzzle request client.
     *
     * @throws RequestException
     */
    private function buildHttpRequest(): RequestInterface
    {
        // Call this body to perform set headers
        $body = $this->getRequestBody();

        return new \GuzzleHttp\Psr7\Request(
            $this->getHttpMethod(),
            $this->url,
            $this->headers,
            $body,
            '2.0' // Protocol version
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
     * Make request to guzzle client.
     *
     * @throws RequestException
     * @throws GuzzleException
     */
    public function makeRequest(): ResponseInterface
    {
        if (!$this->httpResponse instanceof ResponseInterface) {
            $this->httpResponse = $this->client->guzzleRequest(
                $this->buildHttpRequest(),
                $this->clientOptions,
            );
        }

        return $this->httpResponse;
    }
}
