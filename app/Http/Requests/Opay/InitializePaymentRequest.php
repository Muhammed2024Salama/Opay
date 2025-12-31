<?php

namespace App\Http\Requests\Opay;

use Illuminate\Foundation\Http\FormRequest;

class InitializePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount'      => 'required|numeric|min:1|max:100000',
            'user_id'     => 'required|integer',
            'user_name'   => 'required|string|max:255',
            'user_email'  => 'required|email|max:255',
        ];
    }
}
