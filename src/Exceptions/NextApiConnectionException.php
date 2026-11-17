<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Exceptions;

/** Não houve resposta: rede, DNS, TLS, tempo esgotado ou redirecionamento recusado. */
final class NextApiConnectionException extends NextApiException
{
}
