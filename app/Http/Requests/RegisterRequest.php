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
            'email'      => 'required|email:rfc,dns|unique:users,email|max:255',
            'password'   => 'required|string|min:8|max:128|confirmed',
            'first_name' => 'required|string|min:2|max:100|alpha',
            'last_name'  => 'required|string|min:2|max:100|alpha',
            'phone'      => 'nullable|string|min:10|max:20',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => 'Email is required.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'An account with this email already exists.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters.',
            'password.confirmed' => 'Passwords do not match.',
            'first_name.required' => 'First name is required.',
            'first_name.min' => 'First name must be at least 2 characters.',
            'first_name.alpha' => 'First name can only contain letters.',
            'last_name.required' => 'Last name is required.',
            'last_name.min' => 'Last name must be at least 2 characters.',
            'last_name.alpha' => 'Last name can only contain letters.',
        ];
    }
}
