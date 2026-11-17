<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Exceptions;

/** Falha do lado da API (5xx), inclusive a do provedor em tempo real (1014). */
final class NextApiServerException extends NextApiException
{
}
