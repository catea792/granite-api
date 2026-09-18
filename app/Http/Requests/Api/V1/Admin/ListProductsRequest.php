<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\V1\Admin;

use App\Http\Requests\ApiFormRequest;

final class ListProductsRequest extends ApiFormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->rejectUnknownFields([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'page.integer' => 'Trang phải là số nguyên.',
            'page.min' => 'Trang phải lớn hơn hoặc bằng 1.',
            'per_page.integer' => 'Số bản ghi mỗi trang phải là số nguyên.',
            'per_page.min' => 'Số bản ghi mỗi trang phải lớn hơn hoặc bằng 1.',
            'per_page.max' => 'Số bản ghi mỗi trang không được vượt quá 100.',
            '*.prohibited' => 'Trường này không được hỗ trợ.',
        ];
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 20);
    }
}
