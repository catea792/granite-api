<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin\Auth;

use App\Http\Requests\ApiFormRequest;

final class LoginRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->rejectUnknownFields([
            'email' => ['required', 'string', 'email:filter', 'max:255'],
            'password' => ['required', 'string', 'max:1024'],
        ]);
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->string('email')->toString()))]);
        }
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'email.required' => 'Email là bắt buộc.',
            'email.email' => 'Email không đúng định dạng.',
            'email.max' => 'Email không được vượt quá 255 ký tự.',
            'password.required' => 'Mật khẩu là bắt buộc.',
            'password.string' => 'Mật khẩu phải là chuỗi.',
            'password.max' => 'Mật khẩu không hợp lệ.',
            '*.prohibited' => 'Trường này không được hỗ trợ.',
        ];
    }
}
