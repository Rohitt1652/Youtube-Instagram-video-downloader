<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class MediaDownloadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url' => [
                'required',
                'string',
                'url',
                'starts_with:https://',
                'max:2048',
            ],
            'format_id' => [
                'required',
                'string',
                'max:64',
                'regex:/^[a-zA-Z0-9_\-]+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'A media URL is required.',
            'url.starts_with' => 'Only secure HTTPS URLs are permitted.',
            'format_id.required' => 'Please select a download format.',
            'format_id.regex' => 'The selected format identifier contains invalid characters.',
        ];
    }

    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'error' => [
                'code' => 'VALIDATION_ERROR',
                'message' => $validator->errors()->first(),
                'details' => $validator->errors()->toArray(),
            ],
        ], Response::HTTP_UNPROCESSABLE_ENTITY));
    }
}
