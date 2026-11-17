<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

/** Consultas por CNPJ, com ou sem pontuação, numérico ou alfanumérico. */
final class Cnpj extends Resource
{
    /**
     * Dados cadastrais do estabelecimento (1 crédito).
     *
     * @param int|null $toleranceDays idade máxima aceita do dado em cache, em dias (0 a 45); 0 força tempo real.
     */
    public function consultar(string $cnpj, ?int $toleranceDays = null): ?array
    {
        return $this->get('/v1/cnpj/' . Input::cnpj($cnpj) . Input::query(['toleranceDays' => $toleranceDays]));
    }

    /** Consulta em tempo real ao provedor (toleranceDays 0); exige plano que permita. */
    public function consultarAoVivo(string $cnpj): ?array
    {
        return $this->consultar($cnpj, 0);
    }

    /** Situação no Simples Nacional e no MEI. */
    public function obterSimples(string $cnpj, ?int $toleranceDays = null): ?array
    {
        return $this->get('/v1/cnpj/' . Input::cnpj($cnpj) . '/simples' . Input::query(['toleranceDays' => $toleranceDays]));
    }

    /** Inscrições estaduais do estabelecimento, uma por UF. */
    public function obterInscricoesEstaduais(string $cnpj): ?array
    {
        return $this->get('/v1/cnpj/' . Input::cnpj($cnpj) . '/ie');
    }

    /** Sanções (CEIS, CNEP, CEPIM) e dívida ativa da União. Planos pagos ou créditos avulsos. */
    public function obterConformidade(string $cnpj): ?array
    {
        return $this->get('/v1/cnpj/' . Input::cnpj($cnpj) . '/conformidade');
    }

    /** Sócios e empresas ligadas pelos mesmos sócios (grupo econômico). */
    public function obterRelacionamentos(string $cnpj, ?int $page = null, ?int $pageSize = null, ?bool $incluirInativas = null): ?array
    {
        return $this->get('/v1/cnpj/' . Input::cnpj($cnpj) . '/relacionamentos'
            . Input::query(['page' => $page, 'pageSize' => $pageSize, 'incluirInativas' => $incluirInativas]));
    }

    /** Até 100 CNPJs numa chamada; 1 crédito por CNPJ encontrado. Nunca repetida automaticamente. */
    public function consultarLote(array $cnpjs): ?array
    {
        return $this->post('/v1/cnpj/batch', ['cnpjs' => Input::batch($cnpjs)]);
    }

    /** Simples Nacional de até 100 CNPJs numa chamada. Nunca repetida automaticamente. */
    public function obterSimplesLote(array $cnpjs): ?array
    {
        return $this->post('/v1/cnpj/batch/simples', ['cnpjs' => Input::batch($cnpjs)]);
    }
}
