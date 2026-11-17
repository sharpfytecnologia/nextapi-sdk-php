<?php

declare(strict_types=1);

namespace Sharpfy\NextApi;

/**
 * Faz uma requisição HTTP e devolve [status, headers (nomes em minúsculas), corpo], seja qual for o status.
 * Falha de rede vira \RuntimeException com o código TRANSPORT_TIMEOUT quando o tempo se esgotou.
 */
interface Transport
{
    public const TRANSPORT_TIMEOUT = 28;

    /** @param array<string, string> $headers @return array{0: int, 1: array<string, string>, 2: string} */
    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array;
}
