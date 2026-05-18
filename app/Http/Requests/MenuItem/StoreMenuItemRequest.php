<?php

namespace App\Http\Requests\MenuItem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:100', Rule::unique('menu_items','name')->whereNull('deleted_at')],
            'description' => ['nullable', 'string'],
            'base_price' => ['required', 'numeric', 'min:1'],
            'image_url' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
