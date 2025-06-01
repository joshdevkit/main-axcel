<?php

namespace Axcel\Validation;

use Countable;
use Axcel\Facades\DB;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class Validator
{
    private array $data;
    private array $rules;
    private array $messages;
    private array $attributes;
    private array $errors = [];
    private array $validatedData = [];

    public function __construct(array $data, array $rules, array $messages = [], array $attributes = [])
    {
        $this->data = $data;
        $this->rules = $rules;
        $this->messages = $messages;
        $this->attributes = $attributes;

        $this->validate();
    }

    public function validate(): void
    {
        foreach ($this->rules as $field => $fieldRules) {
            $value = $this->getValue($field);
            $rules = is_string($fieldRules) ? explode('|', $fieldRules) : $fieldRules;

            $fieldPassed = true;

            $skip = false;
            foreach ($rules as $rule) {
                if ($rule === 'nullable') {
                    if ($value === null || $value === '') {
                        $skip = true;
                        break;
                    }
                }

                if ($rule === 'sometimes' && !$this->hasValue($field)) {
                    $skip = true;
                    break;
                }
            }

            if ($skip) {
                continue;
            }

            foreach ($rules as $rule) {
                if (in_array($rule, ['nullable', 'sometimes'])) continue;

                if (!$this->validateRule($field, $value, $rule)) {
                    $fieldPassed = false;
                }
            }


            if ($fieldPassed && $this->hasValue($field)) {
                $this->validatedData[$field] = $value;
            }
        }
    }

    private function getValue(string $field)
    {
        if (strpos($field, '.') !== false) {
            return $this->getNestedValue($field);
        }

        return $this->data[$field] ?? null;
    }

    private function hasValue(string $field): bool
    {
        if (strpos($field, '.') !== false) {
            return $this->getNestedValue($field) !== null;
        }

        return array_key_exists($field, $this->data);
    }

    private function getNestedValue(string $field)
    {
        $keys = explode('.', $field);
        $value = $this->data;

        foreach ($keys as $key) {
            if (is_array($value) && array_key_exists($key, $value)) {
                $value = $value[$key];
            } else {
                return null;
            }
        }

        return $value;
    }

    private function validateRule(string $field, $value, string $rule): bool
    {
        if ($rule === 'required') {
            if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                $this->addError($field, 'required');
                return false;
            }
        }

        if (str_starts_with($rule, 'required_with:')) {
            $otherField = substr($rule, 14);
            $otherValue = $this->getValue($otherField);

            if ($otherValue !== null && $otherValue !== '') {
                if ($value === null || $value === '' || (is_array($value) && empty($value))) {
                    $this->addError($field, 'required_with', ['other' => $otherField]);
                    return false;
                }
            }

            return true;
        }

        if ($rule === 'email' && $value !== null && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $this->addError($field, 'email');
                return false;
            }
        }

        if ($rule === 'numeric' && $value !== null && $value !== '') {
            if (!is_numeric($value)) {
                $this->addError($field, 'numeric');
                return false;
            }
        }

        if ($rule === 'integer' && $value !== null && $value !== '') {
            if (!filter_var($value, FILTER_VALIDATE_INT)) {
                $this->addError($field, 'integer');
                return false;
            }
        }

        if ($rule === 'boolean' && $value !== null && $value !== '') {
            if (!in_array($value, [true, false, 0, 1, '0', '1', 'true', 'false'], true)) {
                $this->addError($field, 'boolean');
                return false;
            }
        }

        if ($rule === 'array' && $value !== null) {
            if (!is_array($value)) {
                $this->addError($field, 'array');
                return false;
            }
        }

        if (str_starts_with($rule, 'min:')) {
            $min = (int) substr($rule, 4);
            // Only validate 'min' if a non-empty value is provided
            if ($value !== null && $value !== '') {
                if (strlen((string)$value) < $min) {
                    $this->addError($field, 'min', ['min' => $min]);
                    return false;
                }
            }
        }

        if ($rule === 'file') {
            if (!($value instanceof UploadedFile) || !$value->isValid()) {
                $this->addError($field, 'file');
                return false;
            }
        }

        if ($rule === 'image') {
            if (!($value instanceof UploadedFile) || !$value->isValid()) {
                $this->addError($field, 'image');
                return false;
            }

            $allowedMimes = [
                'image/jpeg',
                'image/png',
                'image/gif',
                'image/webp',
                'image/bmp',
                'image/svg+xml',
            ];

            if (!in_array($value->getClientMimeType(), $allowedMimes)) {
                $this->addError($field, 'image');
                return false;
            }
        }

        if (str_starts_with($rule, 'mimes:')) {
            if (!($value instanceof UploadedFile) || !$value->isValid()) {
                $this->addError($field, 'mimes');
                return false;
            }

            $allowedExts = explode(',', substr($rule, 6));
            $ext = strtolower($value->getClientOriginalExtension());

            if (!in_array($ext, $allowedExts)) {
                $this->addError($field, 'mimes', ['values' => implode(', ', $allowedExts)]);
                return false;
            }
        }

        if (str_starts_with($rule, 'max:')) {
            $max = (int) substr($rule, 4);

            if ($value instanceof UploadedFile) {
                if (!$value->isValid()) {
                    $this->addError($field, 'file');
                    return false;
                }
                $sizeKb = $value->getSize() / 1024;
                if ($sizeKb > $max) {
                    $this->addError($field, 'max', ['max' => $max]);
                    return false;
                }
            } else {
                if (is_numeric($value)) {
                    if ($value > $max) {
                        $this->addError($field, 'max', ['max' => $max]);
                        return false;
                    }
                } elseif (is_string($value)) {
                    if (mb_strlen($value) > $max) {
                        $this->addError($field, 'max', ['max' => $max]);
                        return false;
                    }
                } elseif (is_countable($value)) {
                    if (count($value) > $max) {
                        $this->addError($field, 'max', ['max' => $max]);
                        return false;
                    }
                } else {
                    $this->addError($field, 'max', ['max' => $max]);
                    return false;
                }
            }
        }

        if (str_starts_with($rule, 'in:')) {
            $options = explode(',', substr($rule, 3));
            if ($value !== null && !in_array($value, $options)) {
                $this->addError($field, 'in', ['values' => implode(', ', $options)]);
                return false;
            }
        }

        if (str_starts_with($rule, 'regex:')) {
            $pattern = substr($rule, 6);
            if ($value !== null && !preg_match($pattern, $value)) {
                $this->addError($field, 'regex');
                return false;
            }
        }

        // Unique rule: unique:table,column,except_id,id_column
        if (str_starts_with($rule, 'unique:')) {
            if (!$this->validateUnique($field, $value, $rule)) {
                return false;
            }
        }

        // Exists rule: exists:table,column
        if (str_starts_with($rule, 'exists:')) {
            if (!$this->validateExists($field, $value, $rule)) {
                return false;
            }
        }

        if ($rule === 'confirmed') {
            $confirmationField = $field . '_confirmation';
            $confirmationValue = $this->getValue($confirmationField);

            if ($value !== null && $value !== '' && $value !== $confirmationValue) {
                $this->addError($field, 'confirmed');
                return false;
            }
        }

        return true;
    }

    private function validateUnique(string $field, $value, string $rule): bool
    {
        if ($value === null || $value === '') {
            return true;
        }

        $parts = explode(',', substr($rule, 7));
        $table = trim($parts[0]);
        $column = isset($parts[1]) ? trim($parts[1]) : $field;
        $exceptId = isset($parts[2]) ? trim($parts[2]) : null;
        $idColumn = isset($parts[3]) ? trim($parts[3]) : 'id';

        // Handle special 'auth' keyword - get authenticated user's ID
        if ($exceptId === 'auth') {
            $exceptId = $this->getAuthId();

            // If user is not authenticated, treat as no exception
            if ($exceptId === null) {
                $exceptId = null;
            }
        }

        try {
            $query = DB::table($table)->where($column, $value);

            // Add exception clause if we have a valid except ID
            if ($exceptId !== null && $exceptId !== '' && $exceptId !== 0 && $exceptId !== '0') {
                // Ensure the except ID is cast to proper type
                if (is_numeric($exceptId)) {
                    $exceptId = (int) $exceptId;
                }
                $query->where($idColumn, '!=', $exceptId);
            }

            $count = $query->count();

            if ($count > 0) {
                $this->addError($field, 'unique');
                return false;
            }

            return true;
        } catch (\Exception $e) {
            error_log("Unique validation error: " . $e->getMessage());
            $this->addError($field, 'unique');
            return false;
        }
    }

    private function validateExists(string $field, $value, string $rule): bool
    {
        if ($value === null || $value === '') {
            return true; // Skip validation if no value
        }

        // Parse rule: exists:table,column
        $parts = explode(',', substr($rule, 7));
        $table = $parts[0];
        $column = $parts[1] ?? $field;

        try {
            $count = DB::table($table)->where($column, $value)->count();

            if ($count === 0) {
                $this->addError($field, 'exists', ['table' => $table, 'column' => $column]);
                return false;
            }
        } catch (\Exception $e) {
            // Log error in production, for now we'll add a generic error
            $this->addError($field, 'exists', ['table' => $table, 'column' => $column]);
            return false;
        }

        return true;
    }

    private function addError(string $field, string $rule, array $parameters = []): void
    {
        $value = $this->getValue($field);
        $message = $this->getMessage($field, $rule, $parameters, $value);
        $this->errors[$field][] = $message;
    }

    public function buildFirstErrorMessage(array $errors): string
    {
        if (empty($errors)) {
            return 'Validation failed.';
        }

        $field = array_key_first($errors);
        $firstError = $errors[$field][0] ?? 'Invalid value.';

        return ucfirst($field) . ': ' . $firstError;
    }

    private function getMessage(string $field, string $rule, array $parameters = [], $value = null): string
    {
        $attribute = $this->attributes[$field] ?? $field;

        // Check for custom message first
        $key = "{$field}.{$rule}";
        if (isset($this->messages[$key])) {
            return str_replace(
                [':attribute'] + array_map(fn($k) => ":{$k}", array_keys($parameters)),
                [$attribute] + array_values($parameters),
                $this->messages[$key]
            );
        }

        $messages = [
            'required' => 'The :attribute field is required.',
            'required_with' => 'The :attribute field is required when :other is present.',
            'email' => 'The :attribute must be a valid email address.',
            'numeric' => 'The :attribute must be a number.',
            'integer' => 'The :attribute must be an integer.',
            'boolean' => 'The :attribute field must be true or false.',
            'array' => 'The :attribute must be an array.',
            'min' => 'The :attribute must be at least :min characters.',
            'min_file' => 'The :attribute must be at least :min kilobytes.',
            'max' => 'The :attribute may not be greater than :max characters.',
            'max_file' => 'The :attribute may not be greater than :max kilobytes.',
            'file' => 'The :attribute must be a valid file.',
            'image' => 'The :attribute must be a valid image (jpeg, png, bmp, gif, svg, webp).',
            'mimes' => 'The :attribute must be a file of type: :values.',
            'mimetypes' => 'The :attribute must be a file of type: :values.',
            'in' => 'The selected :attribute is invalid. Valid options are: :values.',
            'regex' => 'The :attribute format is invalid.',
            'unique' => 'The :attribute has already been taken.',
            'exists' => 'The selected :attribute is invalid.',
            'confirmed' => 'The :attribute confirmation does not match.',
        ];

        // Adjust messages for file types
        if (($rule === 'max' || $rule === 'min') && $value instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
            $key = "{$rule}_file"; // will be 'max_file' or 'min_file'
            $message = $messages[$key] ?? $messages[$rule];
        } else {
            $message = $messages[$rule] ?? "The :attribute field is invalid.";
        }

        return str_replace(
            [':attribute', ':min', ':max', ':other', ':values'],
            [
                $attribute,
                $parameters['min'] ?? '',
                $parameters['max'] ?? '',
                $parameters['other'] ?? '',
                isset($parameters['values']) ? implode(', ', (array)$parameters['values']) : ''
            ],
            $message
        );
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public static function make(array $data, array $rules, array $messages = [], array $attributes = []): self
    {
        return new self($data, $rules, $messages, $attributes);
    }

    /**
     * Get the authenticated user's ID
     * Returns null if user is not authenticated
     */
    private function getAuthId()
    {
        try {
            // Check if auth helper function exists and user is authenticated
            if (function_exists('auth') && auth()->check()) {
                return auth()->id();
            }
            return null;
        } catch (\Exception $e) {
            // If there's any error accessing auth, return null
            error_log("Auth access error in validator: " . $e->getMessage());
            return null;
        }
    }

    public function fails(): bool
    {
        return !empty($this->errors);
    }

    public function passes(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function getValidatedData(): array
    {
        return $this->validatedData;
    }
}
