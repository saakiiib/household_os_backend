<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'file'                => 'required|file|max:10240|mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg,gif,txt,csv,zip',
            'title'               => 'required|string|max:255',
            'category'            => 'required|in:insurance,passport,medical,school,warranty,contract,deed,utility_bill,tax,other',
            'description'         => 'nullable|string|max:2000',
            'expiry_date'         => 'nullable|date',
            'shared_with_roles'   => 'nullable|array|max:3',
            'shared_with_roles.*' => 'in:admin,co-admin,member',
            'shared_with_users'   => 'nullable|array|max:50',
            'shared_with_users.*' => 'integer|exists:users,id',
            'is_sensitive'        => 'nullable|boolean',
        ];
    }
}
