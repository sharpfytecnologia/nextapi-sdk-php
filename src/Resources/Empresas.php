<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

final class Empresas extends Resource
{
    /** Pesquisa de empresas. $ceps e $cnaes aceitam vários valores separados por vírgula; datas em AAAA-MM-DD. */
    public function pesquisar(
        ?string $q = null,
        ?string $uf = null,
        ?string $codigoMunicipio = null,
        ?string $cnpjRaiz = null,
        ?string $cnpj = null,
        string|\DateTimeInterface|null $dataAberturaInicio = null,
        string|\DateTimeInterface|null $dataAberturaFim = null,
        ?string $codigoSituacaoCadastral = null,
        ?string $ceps = null,
        ?string $cnaes = null,
        ?int $page = null,
        ?int $pageSize = null,
    ): ?array {
        return $this->get('/v1/empresas' . Input::query(compact(
            'q', 'uf', 'codigoMunicipio', 'cnpjRaiz', 'cnpj', 'dataAberturaInicio', 'dataAberturaFim',
            'codigoSituacaoCadastral', 'ceps', 'cnaes', 'page', 'pageSize',
        )));
    }
}
