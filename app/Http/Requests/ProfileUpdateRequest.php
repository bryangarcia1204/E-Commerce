<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
            // Datos de cliente opcionales para acelerar el checkout.
            'phone' => ['nullable', 'string', 'max:30'],
            'default_shipping_address' => ['nullable', 'string', 'max:500'],
            'preferred_payment_method' => ['nullable', 'string', 'in:'.implode(',', array_keys(User::PAYMENTS))],
        ];
    }
}
