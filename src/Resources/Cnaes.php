<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

final class Cnaes extends Resource
{
    public function listar(?int $page = null, ?int $pageSize = null): ?array
    {
        return $this->get('/v1/cnaes' . Input::query(compact('page', 'pageSize')));
    }
}
