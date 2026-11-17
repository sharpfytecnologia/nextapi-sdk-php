<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Exceptions;

/**
 * Falha numa chamada à NextAPI. A subclasse diz o que fazer; getApiCode() distingue casos com o mesmo status
 * HTTP (405 tanto para "sem créditos" quanto para "plano pago obrigatório").
 */
class NextApiException extends \RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 0,
        public readonly ?int $apiCode = null,
        public readonly mixed $body = null,
        public readonly ?string $requestId = null,
        public readonly ?float $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }
}
