<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

final class Ceps extends Resource
{
    /** Endereço e coordenadas de um CEP. */
    public function consultar(string $cep): ?array
    {
        return $this->get('/v1/ceps/' . Input::cep($cep));
    }

    /** Busca por UF, município, logradouro ou texto livre ($q não combina com $logradouro). */
    public function buscar(
        ?string $uf = null,
        ?int $idMunicipio = null,
        ?string $nomeMunicipio = null,
        ?string $logradouro = null,
        ?string $q = null,
        ?int $page = null,
        ?int $pageSize = null,
    ): ?array {
        return $this->get('/v1/ceps' . Input::query(compact('uf', 'idMunicipio', 'nomeMunicipio', 'logradouro', 'q', 'page', 'pageSize')));
    }

    /** CEPs num raio (em metros) ao redor de uma coordenada. */
    public function buscarProximos(float $latitude, float $longitude, float $raioMetro, ?int $page = null, ?int $pageSize = null): ?array
    {
        Input::range($latitude, -90, 90, 'latitude');
        Input::range($longitude, -180, 180, 'longitude');
        Input::positive($raioMetro, 'raioMetro');
        return $this->get('/v1/ceps/geo' . Input::query(compact('latitude', 'longitude', 'raioMetro', 'page', 'pageSize')));
    }

    /** CEPs num raio (em metros) ao redor de outro CEP. */
    public function buscarProximosDoCep(string $cep, float $raioMetro, ?int $page = null, ?int $pageSize = null): ?array
    {
        $v = Input::cep($cep);
        Input::positive($raioMetro, 'raioMetro');
        return $this->get("/v1/ceps/geo/{$v}" . Input::query(compact('raioMetro', 'page', 'pageSize')));
    }
}
