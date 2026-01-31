<?php
function normalizeOptionalString(string $value): ?string
{
    $value = trim($value);
    return $value === '' ? null : $value;
}

function normalizeOptionalNumber(string $value, string $fieldName, array &$errors, bool $integer = false): ?float
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        $errors[] = sprintf('%s must be a number.', $fieldName);
        return null;
    }

    return $integer ? (float) (int) $value : (float) $value;
}
