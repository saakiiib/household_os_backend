<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRenewalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'               => 'required|string|max:255',
            'category'            => 'required|in:insurance,passport,subscription,warranty,contract,medical,other',
            'renewal_date'        => 'required|date',
            'cost'                => 'nullable|numeric|min:0|max:99999999.99',
            'currency'            => 'nullable|string|size:3|alpha',
            'responsible_user_id' => 'required|integer|exists:users,id',
            'frequency'           => 'required|in:annual,bi-annual,quarterly,monthly,one-time',
            'notes'               => 'nullable|string|max:5000',
        ];
    }
}
