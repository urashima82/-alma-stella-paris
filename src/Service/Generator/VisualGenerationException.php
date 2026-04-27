<?php

declare(strict_types=1);

namespace App\Service\Generator;

class VisualGenerationException extends \RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatusCode = 0,
        private readonly ?string $requestId = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatusCode, $previous);
    }

    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
