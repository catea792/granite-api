<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Exceptions\ApiValidationException;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

abstract class ApiFormRequest extends FormRequest
{
    /** @var list<string> */
    private array $unexpectedFields = [];

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @param  array<string, mixed>  $rules
     * @return array<string, mixed>
     */
    protected function rejectUnknownFields(array $rules): array
    {
        $this->unexpectedFields = array_values(array_diff(array_keys($this->all()), array_keys($rules)));

        foreach ($this->unexpectedFields as $field) {
            $rules[$field] = ['prohibited'];
        }

        return $rules;
    }

    protected function failedValidation(Validator $validator): never
    {
        $failedRules = $validator->failed();
        $issues = [];

        foreach ($validator->errors()->messages() as $field => $messages) {
            $rule = (string) array_key_first($failedRules[$field] ?? []);
            $code = match (true) {
                in_array($field, $this->unexpectedFields, true) => 'UNSUPPORTED_FIELD',
                $rule === 'Required' => 'REQUIRED',
                $rule === 'Max' && is_numeric($this->input($field)) => 'MAX_VALUE',
                $rule === 'Max' => 'MAX_LENGTH',
                default => 'INVALID',
            };

            foreach ($messages as $message) {
                $issues[] = ['field' => $field, 'code' => $code, 'message' => $message];
            }
        }

        throw new ApiValidationException($issues);
    }
}
