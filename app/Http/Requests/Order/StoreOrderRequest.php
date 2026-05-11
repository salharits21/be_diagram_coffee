<?php

namespace App\Http\Requests\Order;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'branch_id' => ['required', 'exists:branches,id'],
            'payment_method' => ['required', 'in:xendit,cash'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.menu_item_id' => ['required', 'exists:menu_items,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];

        if (!auth()->check()) {
            $rules['guest_name'] = ['required', 'string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'branch_id.required' => 'Cabang harus dipilih.',
            'branch_id.exists' => 'Cabang tidak valid.',
            'payment_method.required' => 'Metode pembayaran harus dipilih.',
            'payment_method.in' => 'Metode pembayaran harus xendit atau cash.',
            'items.required' => 'Minimal 1 item harus dipesan.',
            'items.min' => 'Minimal 1 item harus dipesan.',
            'items.*.menu_item_id.required' => 'ID menu item wajib diisi.',
            'items.*.menu_item_id.exists' => 'Menu item tidak valid.',
            'items.*.quantity.required' => 'Jumlah item wajib diisi.',
            'items.*.quantity.min' => 'Jumlah item minimal 1.',
        ];
    }
}
