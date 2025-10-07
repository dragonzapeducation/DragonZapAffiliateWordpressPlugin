<?php

namespace DragonZap\AffiliateApi\Exceptions;

use Psr\Http\Message\ResponseInterface;
use Throwable;

class ApiException extends \RuntimeException
{
    private ?ResponseInterface $response;

    public function __construct(string $message, int $code = 0, ?ResponseInterface $response = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->response = $response;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
