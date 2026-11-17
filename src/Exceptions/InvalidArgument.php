<?php

declare(strict_types=1);

namespace Sharpfy\NextApi\Exceptions;

/** Marca os erros de entrada, para quem quer capturá-los sem depender da classe concreta. */
interface InvalidArgument extends \Throwable
{
}
