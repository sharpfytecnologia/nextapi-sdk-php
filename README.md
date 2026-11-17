# NextAPI para PHP (`sharpfy/nextapi`)

SDK oficial da [NextAPI](https://nextapi.com.br) para PHP 8.2+: CNPJ (inclusive alfanumérico), Simples Nacional, inscrições estaduais, conformidade, sócios, CEP com busca geográfica, pesquisa de empresas, localidades, mercado e créditos. Sem dependências além das extensões `curl` e `json`. As respostas são arrays associativos.

## Requisitos

- PHP 8.2 ou mais recente (testado no 8.2 e no 8.5), com as extensões `curl` e `json`
- Uma chave de API (veja abaixo)

## Instalação

```bash
composer require sharpfy/nextapi
```

Sem Composer: `require 'caminho/para/nextapi-sdk-php/src/autoload.php';`

## Chave de API

Crie a sua em [app.nextapi.com.br](https://app.nextapi.com.br). São dois tipos:

| Prefixo | Ambiente | Custo |
|---|---|---|
| `nxa_test_…` | Sandbox: dados de um subconjunto real, limites próprios e CNPJs de teste que provocam cada erro | Nenhum: nada é debitado |
| `nxa_live_…` | Produção | Créditos do seu plano |

O ambiente sai do prefixo da chave; o endereço da API é o mesmo. Guarde a chave na variável de ambiente
`NEXTAPI_KEY`, que o cliente lê quando você não passa a chave. **Nunca** a coloque no código, no repositório ou num
front-end: quem tem a chave consome os seus créditos.

## Início rápido

```php
use Sharpfy\NextApi\NextApiClient;

$nextapi = new NextApiClient(); // lê NEXTAPI_KEY

$empresa = $nextapi->cnpj->consultar('11.222.333/0001-81');
echo $empresa['razaoSocial'], ' — ', $empresa['situacaoCadastral'], PHP_EOL;
```

## Configuração

```php
use Sharpfy\NextApi\NextApiClient;

$nextapi = new NextApiClient(
    apiKey: getenv('NEXTAPI_KEY'),          // padrão: NEXTAPI_KEY
    baseUrl: 'https://api.nextapi.com.br',  // só https (http apenas para localhost)
    timeout: 15.0,                          // segundos, por tentativa; padrão 30
    maxRetries: 3,                          // padrão 2; 0 desliga
    appInfo: 'minha-app/1.4.0',             // identifica a sua aplicação
);
```

| Parâmetro | Padrão | Efeito |
|---|---|---|
| `apiKey` | `NEXTAPI_KEY` | Chave de API; o prefixo define o ambiente (`$nextapi->environment`) |
| `baseUrl` | `https://api.nextapi.com.br` | Endereço da API |
| `timeout` | 30.0 | Tempo limite de cada tentativa, em segundos |
| `maxRetries` | 2 | Novas tentativas em falha transitória, só em GET |
| `appInfo` | — | Nome e versão da sua aplicação, nos cabeçalhos |
| `transport` | `CurlTransport` | Transporte HTTP próprio (proxy, testes) |

### Laravel

<!-- readme-check: ignorar (depende do framework) -->
```php
// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->singleton(NextApiClient::class, fn () => new NextApiClient(
        apiKey: config('services.nextapi.key'),
        appInfo: 'loja/3.1',
    ));
}

// config/services.php
'nextapi' => ['key' => env('NEXTAPI_KEY')],
```

Depois, injete `NextApiClient` no controller.

## Exemplos

### CNPJ

```php
// Cadastro (1 crédito). toleranceDays: idade máxima aceita do dado em cache (0 a 45); 0 força tempo real.
$empresa = $nextapi->cnpj->consultar('11222333000181', toleranceDays: 30);
$aoVivo = $nextapi->cnpj->consultarAoVivo('11222333000181'); // tempo real; exige plano que permita

$simples = $nextapi->cnpj->obterSimples('11222333000181');
$ies = $nextapi->cnpj->obterInscricoesEstaduais('11222333000181');
$conformidade = $nextapi->cnpj->obterConformidade('11222333000181'); // sanções e dívida ativa
$alfanumerico = $nextapi->cnpj->consultar('12.ABC.345/01DE-35');
```

### Lotes (até 100 CNPJs)

```php
$lote = $nextapi->cnpj->consultarLote(['11222333000181', '45997418000153']); // 1 crédito por CNPJ encontrado
$simplesLote = $nextapi->cnpj->obterSimplesLote(['11222333000181']);

// Mais de 100: divida em blocos. Lote nunca é repetido sozinho; decida você numa falha.
foreach (array_chunk($todos, 100) as $bloco) {
    array_push($resultados, ...$nextapi->cnpj->consultarLote($bloco));
}
```

### Sócios e grupo econômico

```php
$rel = $nextapi->cnpj->obterRelacionamentos('11222333000181', page: 1, pageSize: 50);
foreach ($rel['socios'] ?? [] as $socio) {
    // hashSocio só é estável quando vinculoVerificavel é true: não guarde os provisórios.
    $empresas = $nextapi->socios->listarEmpresas($socio['hashSocio']);
}
```

### CEP

```php
$cep = $nextapi->ceps->consultar('01001-000');
$porRua = $nextapi->ceps->buscar(uf: 'SP', nomeMunicipio: 'São Paulo', logradouro: 'Paulista');
$raio = $nextapi->ceps->buscarProximos(-23.5505, -46.6333, raioMetro: 500);
$vizinhos = $nextapi->ceps->buscarProximosDoCep('01001000', raioMetro: 1000, pageSize: 100);
```

### Pesquisa de empresas, com paginação

```php
for ($page = 1; ; $page++) {
    $pagina = $nextapi->empresas->pesquisar(
        uf: 'SP',
        cnaes: '1091101',                    // vários: separados por vírgula
        dataAberturaInicio: new DateTimeImmutable('2024-01-01'),
        page: $page,
        pageSize: 100,
    );
    foreach ($pagina['items'] as $e) {
        echo $e['cnpj'], ' ', $e['razaoSocial'], PHP_EOL;
    }
    if ($page * $pagina['pageSize'] >= $pagina['total']) {
        break;
    }
}
```

### Localidades, mercado e créditos

```php
$estados = $nextapi->estados->listar();
$municipios = $nextapi->municipios->listar(uf: 'MG', pageSize: 100);
$bh = $nextapi->municipios->obterPorId(3106200);
$cnaes = $nextapi->cnaes->listar(page: 1, pageSize: 50);

$mercado = $nextapi->mercado->obterCnae('1091101', uf: 'SP', meses: 12);
$creditos = $nextapi->creditos->consultar();
echo "restam {$creditos['totalRemainingCredits']} créditos", PHP_EOL;
```

## Erros

Toda falha vira uma exceção do SDK; a classe diz o que fazer, e o código detalha.

| Classe | Quando | Status / código | O que fazer |
|---|---|---|---|
| `NextApiAuthenticationException` | Chave ausente, inválida ou revogada | 401 / 1002, 400 / 1001 | Confira a chave e o ambiente |
| `NextApiInsufficientCreditsException` | Créditos do período acabaram | 405 / 1004, 1006 | Aguarde a renovação ou compre créditos avulsos |
| `NextApiPaidPlanRequiredException` | Recurso exige plano pago | 405 / 1012, 1005 | Mude de plano |
| `NextApiNotFoundException` | CNPJ, CEP ou município não existe | 404 / 1000 | Trate como resultado, não como falha |
| `NextApiValidationException` | Entrada recusada pela API ou pelo SDK | 400 / 1007, ou status 0 | Corrija a entrada; não repita |
| `NextApiRateLimitException` | Limite de requisições por minuto | 429 / 1003 | Já repetido sozinho; diminua o ritmo |
| `NextApiServerException` | Falha da API ou do provedor externo | 5xx / 1014, 9999 | 503 é repetido sozinho; persistindo, fale com o suporte |
| `NextApiConnectionException` | Rede, tempo esgotado ou redirecionamento | status 0 | Verifique a conexão; GETs já foram repetidos |

Todas trazem o status HTTP, o código da API, a mensagem, o corpo da resposta e o **request id**. Ao falar com o
suporte, mande o request id: ele localiza a requisição nos nossos logs.

```php
use Sharpfy\NextApi\Exceptions\{NextApiException, NextApiNotFoundException,
    NextApiInsufficientCreditsException, NextApiRateLimitException};

try {
    $empresa = $nextapi->cnpj->consultar($cnpj);
} catch (NextApiNotFoundException) {
    $empresa = null;
} catch (NextApiInsufficientCreditsException $e) {
    $log->warning("sem créditos ({$e->requestId})");
} catch (NextApiRateLimitException $e) {
    usleep((int) (($e->retryAfter ?? 5) * 1_000_000));
} catch (NextApiException $e) { // qualquer outra falha do SDK
    $log->error("NextAPI {$e->status}/{$e->apiCode} {$e->getMessage()} req={$e->requestId}");
}
```

As exceções trazem `status`, `apiCode`, `body`, `requestId` e `retryAfter` (segundos). Os códigos estão em
`Sharpfy\NextApi\ErrorCodes`.

## Comportamento

**Novas tentativas.** Só requisições `GET` são repetidas, e só em falha transitória: `429`, `502`, `503`, `504` e
erro de rede. O cliente respeita o `Retry-After` da resposta (até 60 s); sem ele, espera de 0,5 s a 8 s, com
crescimento exponencial e variação aleatória. Os lotes (`POST`) **nunca** são repetidos sozinhos, porque consomem
créditos: numa falha, decida você se repete. O padrão é 2 novas tentativas; `maxRetries: 0` desliga.

**Tempo limite.** `timeout` por tentativa (padrão 30 s). Com novas tentativas, o tempo total pode passar disso.

**Validação local.** Entrada que a API recusaria com certeza nem sai do processo: vira
`NextApiValidationException` (status 0) antes da requisição. É o caso de CNPJ com tamanho ou dígitos verificadores errados,
lote vazio ou acima de 100, CEP sem 8 dígitos, raio menor ou igual a zero e latitude ou longitude fora da faixa.
CNPJ e CEP aceitam pontuação (`11.222.333/0001-81`, `01001-000`).

**CNPJ alfanumérico.** O formato novo da Receita (IN RFB 2.229/2024, a partir de julho de 2026) é aceito em todos os
métodos de CNPJ: `12.ABC.345/01DE-35` vira `12ABC34501DE35`, com letras em maiúsculas.

**Segurança.** A chave não aparece em logs, mensagens de erro nem na representação do cliente. O cliente não segue
redirecionamento, para a chave nunca ir para outro host, e só aceita `https` no `baseUrl` (`http` apenas para
`localhost`).

**Cabeçalhos enviados.** Além da autorização, cada requisição leva `User-Agent`, `X-NextApi-Client`
(`php/<versão>`) e `X-NextApi-Client-Info` (runtime, sistema e o seu `appInfo`). Servem para o suporte e para
medirmos o uso de cada versão; não carregam dado do seu negócio. O `appInfo` passa por um filtro: só
`A-Za-z0-9._/@+-`, até 64 caracteres.

**Reuso.** Crie um cliente por processo e reutilize (no Laravel, como singleton). Ele não guarda estado entre chamadas.

## Sandbox

Com a chave `nxa_test_`, estes CNPJs provocam cada resposta da API, para você testar o tratamento de erro sem
gastar nada:

| CNPJ | Resposta |
|---|---|
| `99999991000125` | 404, não encontrado |
| `99999992000170` | 405 / 1004, sem créditos |
| `99999993000114` | 429 / 1003, limite por minuto |
| `99999994000169` | 503 / 1014, falha do provedor |
| `99999995000103` | sucesso depois de 8 s (consulta lenta) |
| `99999996000158` | 405 / 1012, exige plano pago |
| `99999997000100` | 401 / 1002, chave inválida |

O sandbox tem limite próprio de requisições por minuto e por dia, e os dados vêm de um subconjunto real: um CNPJ ou
CEP pode existir em produção e não no sandbox.

## Testes

Os testes não usam rede: conferem cada método contra o contrato da API, em `contract/cases.json` (rota, parâmetros, corpo, respostas e erros).

```bash
docker run --rm -v "$PWD":/src -w /src php:8.2-cli php tests/run_tests.php
```

Com `NEXTAPI_SMOKE_KEY` definida (e `NEXTAPI_SMOKE_ENV=sandbox` ou `production`), roda também a fumaça contra a API
publicada, com os passos de `contract/smoke.json`.

## Suporte e licença

Dúvidas e problemas: abra uma issue neste repositório ou escreva pelo [app.nextapi.com.br](https://app.nextapi.com.br),
com o request id do erro. Licença MIT.
