<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

final class Creditos extends Resource
{
    /** Saldo de créditos do plano e avulsos, e os limites da chave. Não consome crédito. */
    public function consultar(): ?array
    {
        return $this->get('/v1/credits');
    }
}
