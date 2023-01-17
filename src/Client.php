<?php

namespace ManCurl;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * This client is used for manage options of request,
 * the method in this class will used in every request made,
 * also we can disable option in Request method.
 */
final class Client
{
    /** Default User-Agent used in this request. */
    public const DEFAULT_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36';

    /**
     * The cookie jar
     */
    private ?CookieJar $cookieJar = null;

    /**
     * Guzzle request options.
     */
    private array $options = [];

    public bool $useDefaultHeaders = true;

    /**
     * Default header for request,
     * this headers can be disable if useDefaultHeaders is false
     */
    public array $defaultHeaders = [];

    /**
     * The Guzzle Client instance
     */
    private ?GuzzleClient $guzzleClient = null;

    /**
     * Set Cookie from file.
     */
    public function setCookieFile(?string $filename): self
    {
        $this->cookieJar = \is_null($filename)
        ? new CookieJar()
        : new FileCookieJar($filename, true);

        return $this;
    }

    /**
     * Get cookies of request.
     */
    public function getCookies(): CookieJar
    {
        if (!$this->cookieJar instanceof CookieJarInterface) {
            $this->cookieJar = new CookieJar();
        }

        return $this->cookieJar;
    }

    /**
     * Set the proxy to use for requests.
     * if we set proxy in this method, all request will used.
     *
     * @param string $proxy string `ip:port` or `null` to disable proxying
     */
    public function setProxy(?string $proxy): self
    {
        $this->addOption('proxy', $proxy);

        return $this;
    }

    /**
     * Set up headers that are required for every request.
     *
     * this headers can be disable with `->request()->useDefaultHeaders(false)`
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = $headers;

        return $this;
    }

    /**
     * Set use default headers or not.
     */
    public function useDefaultHeaders(bool $value): self
    {
        $this->useDefaultHeaders = $value;

        return $this;
    }

    /**
     * Add Guzzle Client Options.
     *
     * Because Guzzle Client is immutable, which means that we cannot
     * change the defaults used by a client after it's created.
     * so, we just map the added option in request and modify that.
     * this option is used on every request, we can disable by set
     * `->request()->addClientOptions(...)` to prevent option
     *
     * @see https://docs.guzzlephp.org/en/stable/request-options.html
     *
     * @psalm-suppress MixedArrayAssignment
     */
    public function addOption(string $key, mixed $value): self
    {
        if ('handler' === $key) {
            $this->options[$key][] = $value;
        } else {
            $this->options[$key] = $value;
        }

        return $this;
    }

    /**
     * Mock response with a custom response without make real request,
     * usefull for development.
     *
     * @param array<string, string|string[]> $headers Response headers
     */
    public function mockResponse(int $code = 200, string $body = '', array $headers = []): MockHandler
    {
        $mockHandler  = new MockHandler([new Response($code, $headers, $body)]);
        $handlerStack = HandlerStack::create($mockHandler);
        $this->addOption('handler', $handlerStack);

        return $mockHandler;
    }

    /**
     * This option will merged with request option.
     *
     * Because Guzzle Client is immutable, which means that we cannot
     * change the defaults used by a client after it's created.
     * so, we just map the added option in request and modify that.
     *
     * @psalm-suppress PossiblyInvalidArgument
     * @psalm-suppress MixedArgument
     * @psalm-suppress MixedAssignment
     */
    public function getClientOptions(): array
    {
        $options = [];

        foreach ($this->options as $key => $value) {
            if ('handler' === $key) {
                $stack = HandlerStack::create();
                $stack->setHandler(new CurlHandler());

                foreach ($value as $handlerStack) {
                    if ($handlerStack instanceof HandlerStack || $handlerStack instanceof MockHandler) {
                        /** @var callable $handlerStack */
                        $stack->setHandler($handlerStack);
                    } else {
                        $stack->push($handlerStack, 'request_handler');
                    }
                }

                $options['handler'] = $stack;
            } else {
                $options[$key] = $value;
            }
        }

        return $options;
    }

    /**
     * Get Guzzle Client instance.
     *
     * Because Guzzle Client is immutable, which means that we cannot
     * change the defaults used by a client after it's created.
     * if we wont to add a another options in this client, we can use
     * `->request()->addClientOptions(...)` in request without
     * make a new guzzle client object
     */
    public function guzzleClient(): GuzzleClient
    {
        if (!$this->guzzleClient instanceof GuzzleClient) {
            /**
             * The default options of guzzle client,
             * this options can be change with `addOption()`
             */
            $defaultOptions = [
                'allow_redirects' => ['max' => 8],
                'connect_timeout' => 30.0, // Give up trying to connect after 30s.
                'timeout'         => 240.0, // Maximum per-request time (seconds).
                'http_errors'     => false,
                'verify'          => false,
                'cookies'         => true, // use a shared cookie session associated with the client
                'headers'         => [
                    'user-agent' => self::DEFAULT_UA,
                ],
            ];

            $this->guzzleClient = new GuzzleClient(
                array_merge($defaultOptions, $this->getClientOptions())
            );
        }

        return $this->guzzleClient;
    }
}
