<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                => 'required|string|max:255',
            'description'          => 'nullable|string|max:5000',
            'task_type'            => 'required|in:one-time,recurring,rotating',
            'assigned_to_user_id'  => 'nullable|integer|exists:users,id',
            'due_date'             => 'nullable|date',
            'frequency'            => 'nullable|in:daily,weekly,monthly,yearly',
            'priority'             => 'nullable|in:low,medium,high',
            'reward_points'        => 'nullable|integer|min:0|max:10000',
            'estimated_hours'      => 'nullable|numeric|min:0|max:1000',
            'icon'                 => 'nullable|string|max:100',
            'color'                => 'nullable|string|max:7|regex:/^#[0-9a-fA-F]{6}$/',
            'notes'                => 'nullable|string|max:5000',
        ];
    }
}
