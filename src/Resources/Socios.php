<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

final class Socios extends Resource
{
    /**
     * Empresas do sócio. O hash vem de obterRelacionamentos (socios[].hashSocio); com vinculoVerificavel falso
     * ele é provisório: não guarde.
     */
    public function listarEmpresas(string $hashSocio, ?int $page = null, ?int $pageSize = null, ?bool $incluirInativas = null): ?array
    {
        return $this->get('/v1/socios/' . Input::hashSocio($hashSocio) . '/empresas'
            . Input::query(['page' => $page, 'pageSize' => $pageSize, 'incluirInativas' => $incluirInativas]));
    }
}
