<?php

declare(strict_types=1);

namespace App\Application\Account;

use App\Application\Shared\ApiProblem;

final class AuthInput
{
    public static function parse(array $body, array $fields): array
    {
        $actual = array_keys($body);
        sort($actual);
        sort($fields);
        if ($actual !== $fields) {
            throw new ApiProblem('invalid_input', 422);
        }
        foreach ($body as $key => $value) {
            if (! is_string($value)) {
                throw new ApiProblem('invalid_input', 422);
            }
            if ($key === 'email') {
                $body[$key] = mb_strtolower(trim($value));
                if (strlen($body[$key]) > 254 || filter_var($body[$key], FILTER_VALIDATE_EMAIL) === false) {
                    throw new ApiProblem('invalid_input', 422);
                }
            } elseif ($key === 'name' && (trim($value) === '' || mb_strlen($value) > 80)) {
                throw new ApiProblem('invalid_input', 422);
            } elseif ($key === 'uiLocale' && ! in_array($value, config('lessons.ui_locales'), true)) {
                throw new ApiProblem('invalid_input', 422);
            }
        }
        if (array_key_exists('passwordConfirmation', $body)
            && ($body['password'] !== $body['passwordConfirmation'] || mb_strlen($body['password']) < 12
                || strlen($body['password']) > 72 || str_contains($body['password'], "\0"))) {
            throw new ApiProblem('invalid_input', 422);
        }

        return $body;
    }
}
