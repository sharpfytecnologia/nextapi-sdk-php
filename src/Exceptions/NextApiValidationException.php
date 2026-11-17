<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Exceptions;

/** Parâmetro rejeitado pela API (400) ou pelo próprio SDK antes de enviar (status 0). */
final class NextApiValidationException extends NextApiException implements \Sharpfy\NextApi\Exceptions\InvalidArgument
{
}
