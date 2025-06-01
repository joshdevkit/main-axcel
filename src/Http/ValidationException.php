<?php

namespace Axcel\Http;

use Exception;


class ValidationException extends Exception
{
    protected array $errors;

    public function __construct(array $errors, string $message = 'The given data was invalid.')
    {
        $this->errors = $errors;
        parent::__construct($message);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function setErrors(array $errors): void
    {
        $this->errors = $errors;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    public function getFirstError(): ?string
    {
        if (empty($this->errors)) {
            return null;
        }

        $firstField = array_key_first($this->errors);
        $fieldErrors = $this->errors[$firstField];

        return is_array($fieldErrors) ? $fieldErrors[0] : $fieldErrors;
    }

    public function getErrorsForField(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    public function hasErrorsForField(string $field): bool
    {
        return isset($this->errors[$field]) && !empty($this->errors[$field]);
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->errors
        ];
    }

    public function toJson(): string
    {
        return json_encode($this->toArray());
    }

    public function __toString(): string
    {
        return $this->toJson();
    }
}
