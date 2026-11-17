<?php

// Contrato compartilhado pelos seis SDKs (sdks/contract/cases.json). Sem PHPUnit: só PHP e ext-curl.

declare(strict_types=1);

require __DIR__ . '/../src/autoload.php';

use Sharpfy\NextApi\Exceptions;
use Sharpfy\NextApi\Input;
use Sharpfy\NextApi\NextApiClient;
use Sharpfy\NextApi\Transport;

const KEY = 'nxa_test_0123456789abcdef';
// Contrato em sdks/contract (monorepo) ou em contract/ dentro do SDK (espelho público).
$contractDir = is_dir(__DIR__ . '/../contract') ? __DIR__ . '/../contract' : __DIR__ . '/../../contract';
$contract = json_decode((string) file_get_contents($contractDir . '/cases.json'), true, 512, JSON_THROW_ON_ERROR);

final class Fake implements Transport
{
    public array $seen = [];

    /** @param \Closure(int): array $respond */
    public function __construct(private \Closure $respond)
    {
    }

    public function send(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $this->seen[] = compact('method', 'url', 'headers', 'body');
        return ($this->respond)(count($this->seen));
    }
}

function ok(mixed $body, int $status = 200, array $headers = []): array
{
    return [$status, $headers, json_encode($body)];
}

function client(Transport $t, int $retries = 2, float $timeout = 30.0): NextApiClient
{
    return new NextApiClient(KEY, maxRetries: $retries, timeout: $timeout, transport: $t, sleep: static function (float $s): void {});
}

/** Chamada canônica: argumentos posicionais e, no fim, um objeto com os opcionais (viram argumentos nomeados). */
function invoke(NextApiClient $api, string $call, array $args): mixed
{
    [$resource, $method] = explode('.', $call);
    $named = [];
    if ($args !== [] && is_array(end($args)) && !array_is_list(end($args))) {
        $named = array_pop($args);
    }
    if (!method_exists($api->{$resource}, $method)) {
        throw new LogicException("SDK sem {$call}");
    }
    return $api->{$resource}->{$method}(...$args, ...$named);
}

$failures = 0;
$count = 0;
function check(string $name, callable $fn): void
{
    global $failures, $count;
    $count++;
    try {
        $fn();
        echo "ok   {$name}\n";
    } catch (Throwable $e) {
        $failures++;
        echo "FAIL {$name}: " . get_class($e) . ': ' . $e->getMessage() . "\n";
    }
}

function same(mixed $expected, mixed $actual, string $what = ''): void
{
    if ($expected !== $actual) {
        throw new RuntimeException("{$what} esperado " . var_export($expected, true) . ', veio ' . var_export($actual, true));
    }
}

function throws(string $class, callable $fn): Throwable
{
    try {
        $fn();
    } catch (Throwable $e) {
        if ($e instanceof $class) {
            return $e;
        }
        throw new RuntimeException('esperado ' . $class . ', veio ' . get_class($e) . ': ' . $e->getMessage());
    }
    throw new RuntimeException("esperado {$class}, nada foi lançado");
}

check('appInfo é filtrado antes dos cabeçalhos', function () use ($contract) {
    $spec = $contract['headers']['appInfo'];
    $fake = new Fake(static fn () => ok([]));
    $api = new NextApiClient(KEY, appInfo: $spec['input'], transport: $fake, sleep: static function (float $s): void {});
    $api->creditos->consultar();
    $h = $fake->seen[0]['headers'];
    if (!str_ends_with($h['User-Agent'], ' ' . $spec['sanitized']) || !str_ends_with($h['X-NextApi-Client-Info'], '; ' . $spec['sanitized'])
        || !preg_match('#' . $contract['headers']['info'] . '#', $h['X-NextApi-Client-Info'])) {
        throw new RuntimeException("UA {$h['User-Agent']} info {$h['X-NextApi-Client-Info']}");
    }
});

foreach ($contract['cases'] as $c) {
    check('contrato: ' . $c['id'], function () use ($c) {
        $fake = new Fake(static fn () => ok($c['response']));
        $result = invoke(client($fake), $c['call'] ?? $c['id'], $c['args']);
        same(1, count($fake->seen), 'requisições');
        $r = $fake->seen[0];
        $u = parse_url($r['url']);
        same($c['method'], $r['method'], 'método');
        same('https://api.nextapi.com.br', $u['scheme'] . '://' . $u['host'], 'origem');
        same($c['path'], $u['path'], 'caminho');
        parse_str($u['query'] ?? '', $q);
        $expected = $c['query'];
        ksort($q);
        ksort($expected);
        same($expected, $q, 'query');
        same('Bearer ' . KEY, $r['headers']['Authorization'], 'Authorization');
        if (!preg_match('#^nextapi-php/\d+\.\d+\.\d+ php/#', $r['headers']['User-Agent'])) {
            throw new RuntimeException('User-Agent ' . $r['headers']['User-Agent']);
        }
        global $contract;
        foreach (['X-NextApi-Client' => 'client', 'X-NextApi-Client-Info' => 'info'] as $h => $k) {
            if (!preg_match('#' . $contract['headers'][$k] . '#', $r['headers'][$h])) {
                throw new RuntimeException("{$h} {$r['headers'][$h]}");
            }
        }
        if (array_key_exists('body', $c)) {
            same('application/json', $r['headers']['Content-Type'], 'Content-Type');
            same($c['body'], json_decode($r['body'], true), 'corpo');
        } else {
            same(null, $r['body'], 'corpo');
        }
        same($c['response'], $result, 'resposta');
    });
}

foreach ($contract['invalid'] as $c) {
    check('entrada inválida não chega à API: ' . $c['reason'], function () use ($c) {
        $fake = new Fake(static fn () => ok([]));
        $e = throws(Exceptions\NextApiValidationException::class, fn () => invoke(client($fake), $c['call'], $c['args']));
        same(0, $e->status, 'status');
        same(0, count($fake->seen), 'requisições');
    });
}

foreach ($contract['valid'] as $c) {
    check('entrada aceita: ' . $c['call'], function () use ($c) {
        $fake = new Fake(static fn () => ok([]));
        invoke(client($fake), $c['call'], $c['args']);
        same($c['path'], parse_url($fake->seen[0]['url'], PHP_URL_PATH));
    });
}

$classes = [
    'authentication' => Exceptions\NextApiAuthenticationException::class,
    'insufficientCredits' => Exceptions\NextApiInsufficientCreditsException::class,
    'paidPlanRequired' => Exceptions\NextApiPaidPlanRequiredException::class,
    'notFound' => Exceptions\NextApiNotFoundException::class,
    'validation' => Exceptions\NextApiValidationException::class,
    'rateLimit' => Exceptions\NextApiRateLimitException::class,
    'server' => Exceptions\NextApiServerException::class,
];
foreach ($contract['errors'] as $c) {
    check("erro {$c['status']}/{$c['body']['code']} vira {$c['error']}", function () use ($c, $classes) {
        $fake = new Fake(static fn () => ok($c['body'], $c['status'], ['x-request-id' => 'req-1', 'retry-after' => '0']));
        $e = throws(Exceptions\NextApiException::class, fn () => client($fake)->cnpj->consultar('11222333000181'));
        same($classes[$c['error']], get_class($e), 'classe');
        same($c['status'], $e->status, 'status');
        same($c['body']['code'], $e->apiCode, 'code');
        same($c['body']['message'], $e->getMessage(), 'message');
        same('req-1', $e->requestId, 'requestId');
        same(($c['retryable'] ?? false) ? 3 : 1, count($fake->seen), 'tentativas');
    });
}

check('retry: 503 e depois 200', function () {
    $fake = new Fake(static fn (int $n) => $n === 1 ? ok([], 503, ['retry-after' => '0']) : ok(['cnpj' => '1']));
    same(['cnpj' => '1'], client($fake)->cnpj->consultar('11222333000181'));
    same(2, count($fake->seen));
});

check('retry: POST de lote nunca é repetido', function () {
    $fake = new Fake(static fn () => ok(['code' => 1014, 'message' => 'x'], 503));
    throws(Exceptions\NextApiServerException::class, fn () => client($fake)->cnpj->consultarLote(['11222333000181']));
    same(1, count($fake->seen));
});

check('falha de rede é repetida e vira NextApiConnectionException sem a chave', function () {
    $fake = new Fake(static fn () => throw new RuntimeException('connection refused'));
    $e = throws(Exceptions\NextApiConnectionException::class, fn () => client($fake, 1)->creditos->consultar());
    same(2, count($fake->seen));
    same(false, str_contains($e->getMessage(), KEY));
});

check('tempo esgotado vira NextApiConnectionException', function () {
    $fake = new Fake(static fn () => throw new RuntimeException('timeout', Transport::TRANSPORT_TIMEOUT));
    $e = throws(Exceptions\NextApiConnectionException::class, fn () => client($fake, 0, 0.02)->creditos->consultar());
    same(true, str_contains($e->getMessage(), '20 ms'), $e->getMessage());
});

check('redirecionamento vira erro', function () {
    $fake = new Fake(static fn () => [302, ['location' => 'https://evil.example/'], '']);
    throws(Exceptions\NextApiConnectionException::class, fn () => client($fake)->creditos->consultar());
    same(1, count($fake->seen));
});

check('transporte curl não segue redirecionamento', function () {
    $port = random_int(20000, 40000);
    $script = tempnam(sys_get_temp_dir(), 'srv') . '.php';
    file_put_contents($script, '<?php file_put_contents("' . $script . '.log", $_SERVER["REQUEST_URI"] . "\n", FILE_APPEND); header("Location: /outro", true, 302);');
    $proc = proc_open([PHP_BINARY, '-S', "127.0.0.1:{$port}", $script], [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']], $pipes);
    try {
        for ($i = 0; $i < 50 && !@fsockopen('127.0.0.1', $port); $i++) {
            usleep(50_000);
        }
        $api = new NextApiClient(KEY, baseUrl: "http://localhost:{$port}");
        throws(Exceptions\NextApiConnectionException::class, fn () => $api->creditos->consultar());
        same("/v1/credits\n", (string) file_get_contents($script . '.log'));
    } finally {
        proc_terminate($proc);
        proc_close($proc);
    }
});

check('a chave não aparece no cliente', function () {
    $api = client(new Fake(static fn () => ok([])));
    foreach ([print_r($api, true), json_encode($api), (function () use ($api) { ob_start(); var_dump($api); return ob_get_clean(); })()] as $dump) {
        same(false, str_contains((string) $dump, KEY), 'dump');
    }
    same('sandbox', $api->environment);
    throws(LogicException::class, fn () => serialize($api));
});

check('baseUrl exige https (exceto localhost)', function () {
    throws(InvalidArgumentException::class, fn () => new NextApiClient(KEY, baseUrl: 'http://api.nextapi.com.br'));
    new NextApiClient(KEY, baseUrl: 'http://localhost:5112');
});

check('sem chave: erro claro; NEXTAPI_KEY é lida do ambiente', function () {
    putenv('NEXTAPI_KEY');
    throws(Exceptions\NextApiAuthenticationException::class, fn () => new NextApiClient());
    putenv('NEXTAPI_KEY=nxa_live_abc');
    same('production', (new NextApiClient())->environment);
    putenv('NEXTAPI_KEY');
});

check('corpo de erro que não é JSON', function () {
    $fake = new Fake(static fn () => [500, [], '<html>Bad Gateway</html>']);
    $e = throws(Exceptions\NextApiServerException::class, fn () => client($fake, 0)->creditos->consultar());
    same('HTTP 500', $e->getMessage());
    same('<html>Bad Gateway</html>', $e->body);
});

check('CNPJ alfanumérico', fn () => same('12ABC34501DE35', Input::cnpj('12.abc.345/01de-35')));

// ---- Fumaça contra a API publicada (sdks/smoke.sh): só roda com NEXTAPI_SMOKE_KEY. ----
$smokeKey = (string) getenv('NEXTAPI_SMOKE_KEY');
if ($smokeKey !== '') {
    $env = getenv('NEXTAPI_SMOKE_ENV') ?: 'production';
    $steps = json_decode((string) file_get_contents($contractDir . '/smoke.json'), true)['steps'];
    foreach ($steps as $step) {
        if (isset($step['env']) && $step['env'] !== $env) {
            continue;
        }
        check("smoke {$env}: {$step['id']}", function () use ($step, $smokeKey, $env, $classes) {
            $key = !empty($step['badKey']) ? substr($smokeKey, 0, 9) . 'chave-invalida' : $smokeKey;
            $api = new NextApiClient($key, maxRetries: 4);
            $got = 'ok';
            $detail = '';
            try {
                invoke($api, $step['call'], $step['args']);
            } catch (Exceptions\NextApiConnectionException $e) {
                $got = 'connection';
                $detail = " ({$e->getMessage()})";
            } catch (Exceptions\NextApiException $e) {
                $got = array_search(get_class($e), $classes, true) ?: 'other';
                $detail = " ({$e->status}/{$e->apiCode} {$e->getMessage()} req={$e->requestId})";
            }
            echo "SMOKE php {$env} {$step['id']}: {$got}{$detail}\n";
            if (!in_array($got, $step['expect'], true)) {
                throw new RuntimeException("veio {$got}, esperado " . implode('|', $step['expect']));
            }
        });
    }
}

echo "\n{$count} testes, {$failures} falhas\n";
exit($failures === 0 ? 0 : 1);
