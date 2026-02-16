<?php

namespace SparkPost;

use Http\Promise\Promise as HttpPromise;

class SparkPostPromise implements HttpPromise
{
    /**
     * HttpPromise to be wrapped by SparkPostPromise.
     */
    private HttpPromise $promise;

    /**
     * Array with the request values sent.
     */
    private ?array $request;

    /**
     * set the promise to be wrapped.
     *
     * @param HttpPromise $promise
     * @param array|null $request
     */
    public function __construct(HttpPromise $promise, ?array $request = null)
    {
        $this->promise = $promise;
        $this->request = $request;
    }

    /**
     * Hand off the response functions to the original promise and return a custom response or exception.
     *
     * @param callable|null $onFulfilled - function to be called if the promise is fulfilled
     * @param callable|null $onRejected - function to be called if the promise is rejected
     * @return HttpPromise
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): HttpPromise
    {
        $request = $this->request;

        return $this->promise->then(function ($response) use ($onFulfilled, $request): void {
            if (isset($onFulfilled)) {
                $onFulfilled(new SparkPostResponse($response, $request));
            }
        }, function ($exception) use ($onRejected, $request): void {
            if (isset($onRejected)) {
                $onRejected(new SparkPostException($exception, $request));
            }
        });
    }

    /**
     * Hand back the state.
     *
     * @return string $state - returns the state of the promise
     */
    public function getState(): string
    {
        return $this->promise->getState();
    }

    /**
     * Wraps the wait function and returns a custom response or throws a custom exception.
     *
     * @param bool $unwrap
     *
     * @return SparkPostResponse
     *
     * @throws SparkPostException
     */
    public function wait($unwrap = true): SparkPostResponse
    {
        try {
            $response = $this->promise->wait($unwrap);

            return $response ? new SparkPostResponse($response, $this->request) : $response;
        } catch (\Throwable $exception) {
            throw new SparkPostException($exception, $this->request);
        }
    }
}
