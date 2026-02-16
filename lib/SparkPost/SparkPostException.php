<?php

namespace SparkPost;

use Http\Client\Exception\HttpException as HttpException;

class SparkPostException extends \Exception
{
    /**
     * Variable to hold json decoded body from http response.
     */
    private ?array $body = null;

    /**
     * Array with the request values sent.
     */
    private ?array $request;

    /**
     * Sets up the custom exception and copies over original exception values.
     *
     * @param \Throwable $exception - the exception to be wrapped
     * @param null $request
     */
    public function __construct(\Throwable $exception, $request = null)
    {
        $this->request = $request;

        $message = $exception->getMessage();
        $code = $exception->getCode();
        if ($exception instanceof HttpException) {
            $message = $exception->getResponse()->getBody()->__toString();
            $this->body = json_decode($message, true);
            $code = $exception->getResponse()->getStatusCode();
        }

        parent::__construct($message, $code, $exception);
    }

    /**
     * Returns the request values sent.
     *
     * @return array $request
     */
    public function getRequest(): ?array
    {
        return $this->request;
    }

    /**
     * Returns the body.
     *
     * @return array $body - the json decoded body from the http response
     */
    public function getBody(): ?array
    {
        return $this->body;
    }
}
