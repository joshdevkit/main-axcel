<?php

namespace Axcel\Http;

/**
 * Form request validation exception
 */
class FormRequestValidationException extends ValidationException
{
    private FormRequest $request;

    public function __construct(array $errors, FormRequest $request, string $message = 'The given data was invalid.')
    {
        $this->request = $request;
        parent::__construct($errors, $message);
    }

    public function getRequest(): FormRequest
    {
        return $this->request;
    }

    /**
     * Get the response for when a request fails validation
     */
    public function getResponse()
    {
        if ($this->request->expectsJson()) {
            return [
                'message' => $this->getMessage(),
                'errors' => $this->getErrors()
            ];
        }

        // For non-JSON requests, you might want to redirect back with errors
        // This is a simplified response - you can customize based on your needs
        return $this->getErrors();
    }
}
