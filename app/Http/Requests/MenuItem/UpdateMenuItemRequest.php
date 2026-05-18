<?php

namespace App\Http\Requests\MenuItem;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $menuItem = $this->route('menuItem');
        return [
            'category_id' => ['sometimes', 'exists:categories,id'],
            'name' => ['sometimes', 'string', 'max:100', Rule::unique('menu_items','name')->whereNull('deleted_at')->ignore($menuItem->id)],
            'description' => ['nullable', 'string'],
            'base_price' => ['sometimes', 'numeric', 'min:1'],
            'image_url' => ['nullable', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
