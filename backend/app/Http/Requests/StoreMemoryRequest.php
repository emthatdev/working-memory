<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreMemoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'    => ['required', 'in:text,image,pdf'],
            'content' => ['required_if:type,text', 'nullable', 'string'],
            'file'    => ['required_if:type,image,pdf', 'nullable', 'file', 'mimes:jpg,jpeg,png,gif,webp,pdf', 'max:20480'],
        ];
    }
}
