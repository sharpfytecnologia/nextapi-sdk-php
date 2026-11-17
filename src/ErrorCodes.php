<?php

declare(strict_types=1);

namespace Sharpfy\NextApi;

/** Códigos de erro devolvidos pela NextAPI no corpo { status, code, message }. */
final class ErrorCodes
{
    public const NOT_FOUND = 1000;
    public const TOKEN_NOT_PROVIDED = 1001;
    public const TOKEN_INVALID = 1002;
    public const TOO_MANY_REQUESTS = 1003;
    public const MISSING_CREDITS = 1004;
    public const LIVE_SEARCH_NOT_ALLOWED = 1005;
    public const LIVE_SEARCH_MISSING_CREDITS = 1006;
    public const INVALID_CEP = 1007;
    public const REQUIRES_PAID_PLAN = 1012;
    public const EXTERNAL_PROVIDER_FAILURE = 1014;
    public const SANDBOX_DAILY_LIMIT = 1015;
    public const SANDBOX_DISABLED = 1016;
}
