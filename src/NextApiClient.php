<?php

declare(strict_types=1);

namespace Sharpfy\NextApi;

use Sharpfy\NextApi\Exceptions\NextApiAuthenticationException;
use Sharpfy\NextApi\Exceptions\NextApiConnectionException;
use Sharpfy\NextApi\Exceptions\NextApiException;
use Sharpfy\NextApi\Exceptions\NextApiInsufficientCreditsException;
use Sharpfy\NextApi\Exceptions\NextApiNotFoundException;
use Sharpfy\NextApi\Exceptions\NextApiPaidPlanRequiredException;
use Sharpfy\NextApi\Exceptions\NextApiRateLimitException;
use Sharpfy\NextApi\Exceptions\NextApiServerException;
use Sharpfy\NextApi\Exceptions\NextApiValidationException;
use Sharpfy\NextApi\Resources;

/**
 * Cliente da NextAPI. Única exigência: ext-curl e ext-json (já presentes nas instalações comuns de PHP).
 *
 *     $nextapi = new NextApiClient();               // lê NEXTAPI_KEY
 *     $empresa = $nextapi->cnpj->consultar('11.222.333/0001-81');
 *
 * As respostas vêm como arrays associativos, com os nomes de campo da API.
 */
final class NextApiClient
{
    public const VERSION = '0.2.0';
    public const DEFAULT_BASE_URL = 'https://api.nextapi.com.br';
    private const RETRYABLE = [429, 502, 503, 504];

    /** "sandbox" para chaves nxa_test_, "production" para as demais. O endereço é o mesmo. */
    public readonly string $environment;
    public readonly string $baseUrl;

    public readonly Resources\Cnpj $cnpj;
    public readonly Resources\Socios $socios;
    public readonly Resources\Ceps $ceps;
    public readonly Resources\Empresas $empresas;
    public readonly Resources\Cnaes $cnaes;
    public readonly Resources\Estados $estados;
    public readonly Resources\Municipios $municipios;
    public readonly Resources\Mercado $mercado;
    public readonly Resources\Creditos $creditos;

    private readonly \Closure $key;
    private readonly string $userAgent;
    private readonly string $clientInfo;
    private readonly Transport $transport;
    /** @var \Closure(float): void */
    private readonly \Closure $sleep;

    /**
     * @param string|null $apiKey  nxa_live_… (produção) ou nxa_test_… (sandbox). Padrão: NEXTAPI_KEY.
     * @param string      $baseUrl só https (http apenas para localhost).
     * @param float       $timeout segundos por tentativa.
     * @param int         $maxRetries novas tentativas em falha transitória (429, 502, 503, 504, rede), só em GET.
     * @param string|null $appInfo complemento do User-Agent (ex.: "minha-app/1.2").
     */
    public function __construct(
        #[\SensitiveParameter] ?string $apiKey = null,
        string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly float $timeout = 30.0,
        private readonly int $maxRetries = 2,
        ?string $appInfo = null,
        ?Transport $transport = null,
        ?\Closure $sleep = null,
    ) {
        $key = trim($apiKey ?? (string) getenv('NEXTAPI_KEY'));
        if ($key === '') {
            throw new NextApiAuthenticationException('Chave de API não informada: passe apiKey ou defina NEXTAPI_KEY.');
        }
        // A chave fica numa closure: não aparece em var_dump, print_r, var_export nem json_encode do cliente.
        $this->key = static fn (): string => $key;
        $this->environment = str_starts_with($key, 'nxa_test_') ? 'sandbox' : 'production';
        $this->baseUrl = self::checkBaseUrl($baseUrl);
        $app = self::sanitizeAppInfo($appInfo);
        $runtime = 'php/' . PHP_VERSION;
        $ua = 'nextapi-php/' . self::VERSION . ' ' . $runtime;
        $this->userAgent = $app !== '' ? "{$ua} {$app}" : $ua;
        $os = match (PHP_OS_FAMILY) { 'Linux' => 'linux', 'Darwin' => 'darwin', 'Windows' => 'windows', default => 'other' };
        $this->clientInfo = "{$runtime}; {$os}" . ($app !== '' ? "; {$app}" : '');
        $this->transport = $transport ?? new CurlTransport();
        $this->sleep = $sleep ?? static function (float $s): void { usleep((int) ($s * 1_000_000)); };

        $this->cnpj = new Resources\Cnpj($this);
        $this->socios = new Resources\Socios($this);
        $this->ceps = new Resources\Ceps($this);
        $this->empresas = new Resources\Empresas($this);
        $this->cnaes = new Resources\Cnaes($this);
        $this->estados = new Resources\Estados($this);
        $this->municipios = new Resources\Municipios($this);
        $this->mercado = new Resources\Mercado($this);
        $this->creditos = new Resources\Creditos($this);
    }

    public function __debugInfo(): array
    {
        return ['baseUrl' => $this->baseUrl, 'environment' => $this->environment];
    }

    public function __serialize(): array
    {
        throw new \LogicException('NextApiClient não é serializável (guarda a chave).');
    }

    private static function checkBaseUrl(string $raw): string
    {
        $parts = parse_url($raw);
        $scheme = $parts['scheme'] ?? '';
        $host = $parts['host'] ?? '';
        if ($host === '') {
            throw new \InvalidArgumentException("baseUrl inválida: {$raw}");
        }
        $local = in_array($host, ['localhost', '127.0.0.1', '[::1]'], true);
        if ($scheme !== 'https' && !($scheme === 'http' && $local)) {
            throw new \InvalidArgumentException('baseUrl tem de usar https (http só para localhost): a chave vai no cabeçalho.');
        }
        return rtrim($raw, '/');
    }

    /** @internal Uso dos recursos. */
    public function request(string $method, string $path, ?array $body = null): mixed
    {
        $headers = [
            'Authorization' => 'Bearer ' . ($this->key)(),
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent,
            'X-NextApi-Client' => 'php/' . self::VERSION,
            'X-NextApi-Client-Info' => $this->clientInfo,
        ];
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }
        // POST de lote consome créditos: nunca repete sozinho.
        $retries = $method === 'GET' ? max(0, $this->maxRetries) : 0;
        $url = $this->baseUrl . $path;

        for ($attempt = 0; ; $attempt++) {
            try {
                [$status, $responseHeaders, $raw] = $this->transport->send($method, $url, $headers, $payload, $this->timeout);
            } catch (\RuntimeException $e) {
                if ($attempt < $retries) {
                    ($this->sleep)(self::backoff($attempt + 1));
                    continue;
                }
                $message = $e->getCode() === Transport::TRANSPORT_TIMEOUT
                    ? sprintf('Sem resposta da NextAPI em %d ms.', (int) ($this->timeout * 1000))
                    : 'Falha de conexão com a NextAPI: ' . $e->getMessage();
                throw new NextApiConnectionException($message, previous: $e);
            }

            if ($status >= 200 && $status < 300) {
                if ($raw === '') {
                    return null;
                }
                try {
                    return json_decode($raw, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
                } catch (\JsonException $e) {
                    throw new NextApiServerException('Resposta inválida da NextAPI: ' . $e->getMessage(), $status, body: $raw, previous: $e);
                }
            }
            if ($status >= 300 && $status < 400) {
                throw new NextApiConnectionException(
                    "A NextAPI respondeu com redirecionamento ({$status}); o SDK não o segue para não reenviar a chave.",
                    $status,
                );
            }
            $wait = self::retryAfter($responseHeaders['retry-after'] ?? null);
            if (in_array($status, self::RETRYABLE, true) && $attempt < $retries) {
                ($this->sleep)($wait ?? self::backoff($attempt + 1));
                continue;
            }
            throw self::toException($status, $responseHeaders, $raw, $wait);
        }
    }

    private static function toException(int $status, array $headers, string $raw, ?float $wait): NextApiException
    {
        $decoded = json_decode($raw, true);
        $body = $decoded ?? $raw;
        $code = is_array($decoded) && is_int($decoded['code'] ?? null) ? $decoded['code'] : null;
        $message = is_array($decoded) && is_string($decoded['message'] ?? null) && $decoded['message'] !== ''
            ? $decoded['message'] : "HTTP {$status}";
        $rid = $headers['x-request-id'] ?? null;

        $class = match (true) {
            in_array($code, [ErrorCodes::TOKEN_NOT_PROVIDED, ErrorCodes::TOKEN_INVALID, ErrorCodes::SANDBOX_DISABLED], true) => NextApiAuthenticationException::class,
            in_array($code, [ErrorCodes::MISSING_CREDITS, ErrorCodes::LIVE_SEARCH_MISSING_CREDITS], true) => NextApiInsufficientCreditsException::class,
            in_array($code, [ErrorCodes::REQUIRES_PAID_PLAN, ErrorCodes::LIVE_SEARCH_NOT_ALLOWED], true) => NextApiPaidPlanRequiredException::class,
            $status === 401, $status === 403 => NextApiAuthenticationException::class,
            $status === 404 => NextApiNotFoundException::class,
            $status === 429 => NextApiRateLimitException::class,
            $status >= 500 => NextApiServerException::class,
            $status === 400, $status === 422 => NextApiValidationException::class,
            default => NextApiException::class,
        };
        return new $class($message, $status, $code, $body, $rid, $wait);
    }

    private static function retryAfter(?string $header): ?float
    {
        if ($header === null || trim($header) === '') {
            return null;
        }
        if (is_numeric($header)) {
            $s = (float) $header;
        } else {
            $t = strtotime($header);
            if ($t === false) {
                return null;
            }
            $s = $t - time();
        }
        return min(max($s, 0.0), 60.0);
    }

    private static function backoff(int $attempt): float
    {
        $base = min(8.0, 0.5 * 2 ** ($attempt - 1));
        return $base / 2 + mt_rand() / mt_getrandmax() * $base / 2;
    }

    /** Só caracteres seguros num cabeçalho: o curl repassa CR/LF do appInfo e abriria espaço para injeção de cabeçalho. */
    public static function sanitizeAppInfo(?string $info): string
    {
        return substr((string) preg_replace('#[^A-Za-z0-9._/@+-]#', '', (string) $info), 0, 64);
    }
}
