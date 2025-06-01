<?php

namespace Axcel\Http;

use Axcel\Container\Container;
use Axcel\Validation\Validator;

abstract class FormRequest extends Request
{
    protected Container $container;
    protected array $validatedData = [];

    //initialize the Validator class to adopt error handling when using form request/custom
    protected ?Validator $validator = null; // Make it nullable and initialize to null
    protected array $errors = [];

    public function __construct()
    {
        parent::__construct();
        $this->container = Container::getInstance();
    }

    /**
     * Create FormRequest from existing Request
     */
    public static function createFrom(Request $request): static
    {
        $formRequest = new static();

        // Copy basic components
        $formRequest->query = $request->query;
        $formRequest->request = $request->request;
        $formRequest->attributes = $request->attributes;
        $formRequest->cookies = $request->cookies;
        $formRequest->files = $request->files;
        $formRequest->server = $request->server;
        $formRequest->headers = $request->headers;

        // dd($formRequest);
        return $formRequest;
    }

    /**
     * Determine if the user is authorized to make this request
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request
     */
    abstract public function rules(): array;

    /**
     * Get custom messages for validator errors
     */
    public function messages(): array
    {
        return [];
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Override in child classes to modify data before validation
    }

    /**
     * Handle a passed validation attempt
     */
    protected function passedValidation(): void
    {
        // Override in child classes to perform actions after validation passes
    }

    /**
     * Determine if the request has validation errors
     */
    public function hasErrors(): bool
    {
        if ($this->validator === null) {
            $this->validateResolved();
        }

        return !empty($this->errors);
    }

    /**
     * Handle a failed validation attempt
     */
    protected function failedValidation(array $errors): void
    {
        $this->errors = $errors;
    }

    /**
     * Handle a failed authorization attempt
     */
    protected function failedAuthorization(): void
    {
        throw new AuthorizationException('This action is unauthorized.');
    }

    /**
     * Validate the request
     */
    public function validateResolved(): void
    {
        $this->prepareForValidation();

        if (!$this->authorize()) {
            $this->failedAuthorization();
        }

        $this->validator = $this->getValidatorInstance();

        if ($this->validator->fails()) {
            $this->failedValidation($this->validator->getErrors());
        }

        $this->validatedData = $this->validator->getValidatedData();
        $this->passedValidation();
    }

    /**
     * Get the validator instance for the request
     */
    protected function getValidatorInstance(): Validator
    {
        return new Validator($this->all(), $this->rules(), $this->messages(), $this->attributes());
    }

    /**
     * Get the validated data from the request
     */
    public function validated(): array
    {
        return $this->validatedData;
    }

    /**
     * Get specific validated data
     */
    public function safe(): array
    {
        return $this->validated();
    }

    /**
     * Get validation errors
     */
    public function errors(): array
    {
        // Check if validator has been initialized, if not create it
        if ($this->validator === null) {
            $this->validator = $this->getValidatorInstance();
        }

        return $this->validator->getErrors();
    }

    /**
     * Get only specified keys from validated data
     */
    public function only(array $keys): array
    {
        return array_intersect_key($this->validated(), array_flip($keys));
    }

    /**
     * Get all validated data except specified keys
     */
    public function except(array $keys): array
    {
        return array_diff_key($this->validated(), array_flip($keys));
    }
}
