<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'      => 'required|email|unique:users,email|max:255',
            'password'   => 'required|string|min:8|max:128',
            'first_name' => 'required|string|max:100',
            'last_name'  => 'required|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'The email has already been taken.',
            'password.min' => 'Password must be at least 8 characters.',
        ];
    }
}
