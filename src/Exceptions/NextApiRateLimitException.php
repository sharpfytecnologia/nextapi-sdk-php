<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Exceptions;

/** Limite de requisições do plano ou do sandbox (429). retryAfter em segundos. */
final class NextApiRateLimitException extends NextApiException
{
}
