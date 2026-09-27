<?php

namespace App\Services\Ai\Support;

/**
 * Checks a decoded AI response against the schema an AiRequest
 * declared it expects, before anything in the editorial domain sees
 * it. Requesting JSON does not mean a model returned valid JSON, and
 * valid JSON does not mean it has the fields or types the caller
 * needs - both are verified here, deterministically. This never
 * "repairs" malformed output into something that merely looks valid;
 * it only reports what is wrong so the caller can turn it into a
 * controlled failure.
 *
 * Schema shape: ['field' => ['type' => 'string'|'int'|'float'|'bool'|'array',
 * 'required' => bool (default true), 'max_length' => int (optional, string fields only)]].
 */
class AiOutputValidator
{
    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, array<string, mixed>>  $schema
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validate(array $data, array $schema): array
    {
        $errors = [];

        foreach ($schema as $field => $rules) {
            $required = $rules['required'] ?? true;
            $present = array_key_exists($field, $data);

            if (! $present) {
                if ($required) {
                    $errors[] = "Missing required field: {$field}";
                }

                continue;
            }

            $value = $data[$field];
            $type = $rules['type'] ?? 'string';

            if (! $this->matchesType($value, $type)) {
                $errors[] = "Field {$field} expected type {$type}";

                continue;
            }

            if ($type === 'string' && isset($rules['max_length']) && mb_strlen($value) > $rules['max_length']) {
                $errors[] = "Field {$field} exceeds max_length of {$rules['max_length']}";
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
        ];
    }

    private function matchesType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int' => is_int($value),
            'float' => is_float($value) || is_int($value),
            'bool' => is_bool($value),
            'array' => is_array($value),
            default => false,
        };
    }
}
