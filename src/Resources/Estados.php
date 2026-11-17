<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Resources;

use Sharpfy\NextApi\Input;

final class Estados extends Resource
{
    public function listar(?int $page = null, ?int $pageSize = null): ?array
    {
        return $this->get('/v1/estados' . Input::query(compact('page', 'pageSize')));
    }
}
