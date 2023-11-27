<?php

namespace App\Middleware;

use Predis\Client as Predis;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class LimitsRequests
{
    /**
     * Requests that can be made as per the time limit.
     *
     * @var integer
     */
    protected $requests = 30;

    /**
     * The time limit that the defined requests can be made within.
     *
     * @var integer
     */
    protected $perSecond = 60;

    /**
     * The unique identifier to use within the storage key.
     *
     * @var string
     */
    protected $identifier;

    /**
     * The limit exceeded handler, obviously. ¯\_(ツ)_/¯
     *
     * @var callable|\Closure
     */
    protected $limitExceededHandler;

    /**
     * The storage key used for the Redis store.
     *
     * @var string
     */
    protected $storageKey = 'rate:%s:requests';

    /**
     * Inject Predis client and the identifier used for tracking.
     *
     * @param Predis\Client $redis
     *
     * @return void
     */
    public function __construct(Predis $redis)
    {
        $this->redis = $redis;
        $this->identifier = $this->getIdentifier();
    }

    /**
     * Call the middleware.
     *
     * @param  Psr\Http\Message\ServerRequestInterface $request
     * @param  Psr\Http\Message\ResponseInterface $response
     * @param  Closure $next
     *
     * @return Psr\Http\Message\ResponseInterface
     */
    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, $next)
    {
        if ($this->hasExceededRateLimit()) {
            return $this->getLimitExceededHandler()($request, $response, $next);
        }

        $this->incrementRequestCount();

        return $next($request, $response);
    }

    /**
     * Check if the rate limit has been exceeded.
     *
     * @return boolean
     */
    protected function hasExceededRateLimit()
    {
        if ($this->redis->get($this->getStorageKey()) >= $this->requests) {
            return true;
        }

        return false;
    }

    /**
     * Increment the request count.
     *
     * @return void
     */
    protected function incrementRequestCount()
    {
        // Increment the amount of requests made at the storage key.
        $this->redis->incr($this->getStorageKey());

        // Set the expiry for the storage key. This will automatically
        // delete the key after x seconds. If a request is blocked,
        // this won't be called again and again, as we handle the
        // checking when the middleware is invoked.
        $this->redis->expire($this->getStorageKey(), $this->perSecond);
    }

    /**
     * Set the limitations.
     *
     * @param integer $requests
     * @param integer $perSecond
     *
     * @return $this
     */
    public function setRateLimit($requests, $perSecond)
    {
        $this->requests = $requests;
        $this->perSecond = $perSecond;

        return $this;
    }

    /**
     * Set the storage key to be used for Redis.
     *
     * @param string $key
     *
     * @return $this
     */
    public function setStorageKey($storageKey)
    {
        $this->storageKey = $storageKey;

        return $this;
    }

    /**
     * Get the identifier for the Redis storage key.
     *
     * @return string
     */
    protected function getStorageKey()
    {
        return sprintf($this->storageKey, $this->identifier);
    }

    /**
     * Set the handler for the limit being exceeded.
     *
     * @param callable|\Closure $handler
     *
     * @return $this
     */
    public function setLimitExeededHandler(callable $handler)
    {
        $this->limitExceededHandler = $handler;

        return $this;
    }

    /**
     * Get the rate limit response.
     *
     * @return callable|\Closure
     */
    public function getLimitExceededHandler()
    {
        // Return our own default handler if one hasn't explicitly been set.
        if ($this->limitExceededHandler === null) {
            return $this->defaultLimitExceededHandler();
        }

        return $this->limitExceededHandler;
    }

    /**
     * The default limit exceeded handler.
     *
     * @return \Closure
     */
    protected function defaultLimitExceededHandler()
    {
        return function (ServerRequestInterface $request, ResponseInterface $response, $next) {
            return $response->withStatus(429)->write('Rate limit exceeded');
        };
    }

    /**
     * Set the identifier used for checking requests.
     *
     * @param mixed $identifier
     * @return $this
     */
    public function setIdentifier($identifier)
    {
        $this->identifier = $identifier;

        return $this;
    }

    /**
     * Resolve the identifier used for checking requests.
     *
     * @param  mixed $identifier
     * @return mixed
     */
    protected function getIdentifier($identifier = null)
    {
        // Return the current user's IP address as an identifier if
        // no itentifier has been specified.
        if ($identifier === null) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return $identifier;
    }
}