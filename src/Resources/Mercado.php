<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

/** Dados de mercado agregados. Planos pagos ou créditos avulsos. */
final class Mercado extends Resource
{
    /** Empresas ativas, aberturas e baixas de um CNAE (com ou sem pontuação), no Brasil ou numa UF. */
    public function obterCnae(string $cnae, ?string $uf = null, ?int $meses = null): ?array
    {
        $codigo = Input::segment((string) preg_replace('/[\s.\/-]/', '', $cnae), 'cnae');
        return $this->get("/v1/mercado/cnaes/{$codigo}" . Input::query(compact('uf', 'meses')));
    }

    /** Empresas e economia (PIB, população) de um município, pelo código da Receita. */
    public function obterMunicipio(int $id): ?array
    {
        return $this->get('/v1/mercado/municipios/' . Input::id($id, 'id'));
    }

    /** Série de um indicador econômico (ex.: "ipca"), de $inicio a $fim (AAAAMM). */
    public function obterIndicador(string $tipo, ?string $inicio = null, ?string $fim = null): ?array
    {
        return $this->get('/v1/mercado/indicadores/' . Input::segment($tipo, 'tipo') . Input::query(compact('inicio', 'fim')));
    }
}
