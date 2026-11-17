<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Exceptions;

/** O recurso exige plano pago ou créditos avulsos (1012, 1005). */
final class NextApiPaidPlanRequiredException extends NextApiException
{
}
