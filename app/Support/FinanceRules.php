<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Validation\ValidationException;

final class FinanceRules
{
    public static function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
