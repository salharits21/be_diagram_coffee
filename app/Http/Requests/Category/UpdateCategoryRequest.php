<?php

namespace App\Http\Requests\Category;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $categoryId = $this->route('category')->id;
        return [
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('categories','name')->ignore($categoryId)->whereNull('deleted_at')],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    // public function messages(): array
    // {
    //     return [
    //         'sort_order.min' => 'Posisi kategori harus lebih besar dari 0',
    //         'name.max' => 'Nama kategori maksimal 100 karakter',
    //         'name.unique' => 'Nama kategori sudah ada'
    //     ];
    // }
}
