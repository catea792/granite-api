<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\ApiFormRequest;

final class UpdateProductRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->rejectUnknownFields([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string', 'max:10000'],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'Tên sản phẩm không được để trống.',
            'name.string' => 'Tên sản phẩm phải là chuỗi.',
            'name.max' => 'Tên sản phẩm không được vượt quá 255 ký tự.',
            'description.string' => 'Mô tả phải là chuỗi.',
            'description.max' => 'Mô tả không được vượt quá 10000 ký tự.',
            'is_active.required' => 'Trạng thái hoạt động không được để trống.',
            'is_active.boolean' => 'Trạng thái hoạt động phải là boolean.',
            '*.prohibited' => 'Trường này không được hỗ trợ.',
        ];
    }
}
