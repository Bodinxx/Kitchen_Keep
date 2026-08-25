<?php
declare(strict_types=1);
namespace App\Util;
final class Validator
{
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            foreach ($fieldRules as $rule => $ruleValue) {
                if (is_int($rule)) { $rule = $ruleValue; $ruleValue = true; }
                switch ($rule) {
                    case 'required':
                        if ($ruleValue && (!is_string($value) ? empty($value) && $value !== '0' && $value !== 0 : trim((string) $value) === '')) $errors[$field][] = 'This field is required.';
                        break;
                    case 'email':
                        if ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) $errors[$field][] = 'Please provide a valid email address.';
                        break;
                    case 'min_length':
                        if ($value !== null && mb_strlen((string) $value) < (int) $ruleValue) $errors[$field][] = 'Must be at least ' . (int) $ruleValue . ' characters.';
                        break;
                    case 'max_length':
                        if ($value !== null && mb_strlen((string) $value) > (int) $ruleValue) $errors[$field][] = 'Must be no more than ' . (int) $ruleValue . ' characters.';
                        break;
                    case 'regex':
                        if ($value !== null && $value !== '' && preg_match((string) $ruleValue, (string) $value) !== 1) $errors[$field][] = 'Format is invalid.';
                        break;
                    case 'in_list':
                        if ($value !== null && $value !== '' && !in_array($value, (array) $ruleValue, true)) $errors[$field][] = 'Please choose a valid option.';
                        break;
                }
            }
        }
        return $errors;
    }
}
