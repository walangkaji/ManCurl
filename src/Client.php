<?php

namespace ManCurl;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\CookieJarInterface;
use GuzzleHttp\Cookie\FileCookieJar;
use GuzzleHttp\Cookie\SetCookie;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

final class Client
{
    public const DEFAULT_UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/98.0.4758.102 Safari/537.36';

    private ?string $proxy = null;

    private bool $useDefaultHeaders = false;

    private ?CookieJar $cookieJar = null;

    private array $tempCookie = [];

    private bool $useCookie = true;

    private array $options = [];

    /**
     * @var string[]
     */
    private $defaultHeaders = [];

    /**
     * Set Cookie from file.
     */
    public function setCookieFile(?string $filename): self
    {
        $this->cookieJar = !\is_null($filename)
        ? new FileCookieJar($filename, true)
        : new CookieJar();

        return $this;
    }

    /**
     * Add custom cookies for request.
     * This cookie marked as temporary and will remove after request.
     *
     * @throws \RuntimeException
     */
    public function addCookie(string $name, ?string $value, ?string $domain): self
    {
        $tempCookie = [
            'Name'   => $name,
            'Value'  => $value,
            'Domain' => $domain,
        ];

        $this->tempCookie[] = $tempCookie;

        $this->getCookies()->setCookie(new SetCookie($tempCookie));

        return $this;
    }

    /**
     * Get cookies.
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
     *
     * @param string $proxy string `ip:port` or `null` to disable proxying
     */
    public function setProxy(?string $proxy): self
    {
        $this->proxy = $proxy;

        return $this;
    }

    /**
     * Set up headers that are required for every request.
     *
     * After this set, we can use `->request()->useDefaultHeaders()`
     * for request if we want to use the default headers
     */
    public function setDefaultHeaders(array $headers): self
    {
        $this->defaultHeaders = $headers;

        return $this;
    }

    public function useDefaultHeaders(bool $value): self
    {
        $this->useDefaultHeaders = $value;

        return $this;
    }

    /**
     * Remove temporary cookie setelah tambah cookie manual pada request.
     */
    private function removeTempCookie(): void
    {
        if (!empty($this->tempCookie)) {
            foreach ($this->tempCookie as $value) {
                $getCookie = $this->getCookies()->getCookieByName($value['Name']);

                if (null !== $getCookie) {
                    $this->getCookies()->clear(
                        $getCookie->getDomain(),
                        $getCookie->getPath(),
                        $getCookie->getName()
                    );
                }
            }

            $this->tempCookie = [];
        }
    }

    /**
     * Disable cookie for request.
     *
     * The cookie is used again after make request, so if we want to disable the cookie
     * we need calling this method every make request
     */
    public function withoutCookie(): void
    {
        $this->useCookie = false;
    }

    /**
     * Add temporary Client Options, the option will reset after make request.
     *
     * @param mixed $value
     *
     * @see https://docs.guzzlephp.org/en/stable/request-options.html
     */
    public function addOption(string $key, $value): self
    {
        if ('handler' === $key) {
            $this->options[$key][] = $value;
        } else {
            $this->options[$key] = $value;
        }

        return $this;
    }

    /**
     * Mock response with a custom response without make real request.
     */
    public function mockResponse(string $body, int $code = 200, array $headers = []): self
    {
        $mock         = new MockHandler([new Response($code, $headers, $body)]);
        $handlerStack = HandlerStack::create($mock);
        $this->addOption('handler', $handlerStack);

        return $this;
    }

    /**
     * Final client options
     *
     * @return array<mixed>
     */
    private function finalClientOptions(): array
    {
        $defaultOptions = [
            'allow_redirects' => ['max' => 8],
            'connect_timeout' => 30.0, // Give up trying to connect after 30s.
            'timeout'         => 240.0, // Maximum per-request time (seconds).
            'http_errors'     => false,
            'verify'          => false,
            'headers'         => [
                'user-agent' => self::DEFAULT_UA,
            ],
        ];

        if (!empty($this->options)) {
            foreach ($this->options as $key => $value) {
                if ('handler' === $key) {
                    $stack = HandlerStack::create();
                    $stack->setHandler(new CurlHandler());
                    foreach ($value as $handlerStack) {
                        if ($handlerStack instanceof HandlerStack) {
                            $stack = $handlerStack;
                        } else {
                            /** @var callable $handlerStack */
                            $stack->push($handlerStack, 'request_handler');
                        }
                    }

                    $defaultOptions['handler'] = $stack;
                } else {
                    $defaultOptions[$key] = $value;
                }
            }
        }

        // don't use cookie if disableCookie() is called
        if ($this->useCookie) {
            $defaultOptions['cookies'] = $this->getCookies();
        }

        // Add default headers if request set useDefaultHeaders
        if ($this->useDefaultHeaders) {
            $defaultOptions['headers'] = $this->defaultHeaders;
        }

        if (!\is_null($this->proxy)) {
            $defaultOptions['proxy'] = $this->proxy;
        }

        return $defaultOptions;
    }

    /**
     * Wraps Guzzle's request and adds special error handling and options.
     *
     * @param RequestInterface $request        HTTP request to send
     * @param array            $requestOptions extra Guzzle options for this request
     *
     * @throws GuzzleException
     */
    public function guzzleRequest(RequestInterface $request, array $requestOptions): ResponseInterface
    {
        // Default request options (immutable after client creation).
        $guzzleClient = new GuzzleClient($this->finalClientOptions());
        $response     = $guzzleClient->send($request, $requestOptions);

        // Remove temporary cookie yang ditambahkan pada addCookie request
        $this->removeTempCookie();
        $this->useDefaultHeaders(false);
        // $this->cookieJar = null;
        $this->useCookie = true;
        $this->options   = [];

        return $response;
    }
}
