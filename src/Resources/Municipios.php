<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

final class Municipios extends Resource
{
    /** Municípios de uma UF, ou de todas sem $uf. */
    public function listar(?string $uf = null, ?int $page = null, ?int $pageSize = null): ?array
    {
        return $this->get('/v1/municipios' . Input::query(compact('uf', 'page', 'pageSize')));
    }

    /** Município pelo código da Receita, com a hierarquia do IBGE. */
    public function obterPorId(int $id): ?array
    {
        return $this->get('/v1/municipios/' . Input::id($id, 'id'));
    }
}
