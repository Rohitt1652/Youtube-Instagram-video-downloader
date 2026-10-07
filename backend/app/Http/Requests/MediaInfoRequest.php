<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Symfony\Component\HttpFoundation\Response;

class MediaInfoRequest extends FormRequest
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
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => 'A media URL is required.',
            'url.url' => 'Please provide a valid URL format.',
            'url.starts_with' => 'Only secure HTTPS URLs are permitted.',
            'url.max' => 'The provided URL exceeds the maximum length.',
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
