<?php

namespace App\Http\Requests\Banner;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBannerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'max:255'],
            'image' => ['sometimes', 'image', 'mimes:jpeg,png,jpg,webp', 'max:2048', 'dimensions:ratio=3/1'],
            'description' => ['nullable', 'string', 'max:500'],
            'link_url' => ['nullable', 'url', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'image.image' => 'File harus berupa gambar.',
            'image.mimes' => 'Gambar harus berformat jpeg, png, jpg, atau webp.',
            'image.max' => 'Ukuran gambar maksimal 2MB.',
            'image.dimensions' => 'Gambar harus memiliki rasio 3:1.',
            'link_url.url' => 'URL link harus valid.',
        ];
    }
}
