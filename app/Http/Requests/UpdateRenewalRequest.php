<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRenewalRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            'title'               => 'sometimes|string|max:255',
            'category'            => 'sometimes|in:insurance,passport,subscription,warranty,contract,medical,other',
            'renewal_date'        => 'sometimes|date',
            'cost'                => 'sometimes|nullable|numeric|min:0|max:99999999.99',
            'currency'            => 'sometimes|nullable|string|size:3|alpha',
            'responsible_user_id' => 'sometimes|integer|exists:users,id',
            'frequency'           => 'sometimes|in:annual,bi-annual,quarterly,monthly,one-time',
            'status'              => 'sometimes|in:active,completed,cancelled,renewed',
            'notes'               => 'sometimes|nullable|string|max:5000',
        ];
    }
}
