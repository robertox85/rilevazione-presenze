<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class RequestValidationService
{

    /**
     * @throws ValidationException
     */
    public function validateCheckInRequest(Request $request): void
    {
        $rules = [
            'latitude' => [
                'required',
                'numeric',
                'min:-90',
                'max:90',
                'regex:/^-?\d{1,2}\.\d+$/',
            ],
            'longitude' => [
                'required',
                'numeric',
                'min:-180',
                'max:180',
                'regex:/^-?\d{1,3}\.\d+$/',
            ],

            'device_uuid' => [
                'required',
                'regex:/^[0-9a-fA-F]{16}$/'
            ],

            'device_name' => 'nullable|string|max:255',
        ];

        $this->validateRequest($request, $rules);
    }

    /**
     * @throws ValidationException
     */
    public function validateCheckOutRequest(Request $request): void
    {
        $rules = [
            'latitude' => [
                'required',
                'numeric',
                'min:-90',
                'max:90',
                'regex:/^-?\d{1,2}\.\d+$/',
            ],
            'longitude' => [
                'required',
                'numeric',
                'min:-180',
                'max:180',
                'regex:/^-?\d{1,3}\.\d+$/',
            ],

            'device_uuid' => [
                'required',
                'regex:/^[0-9a-fA-F]{16}$/'
            ],
        ];


        $this->validateRequest($request, $rules);


    }

    /**
     * Metodo comune per validare le richieste
     */
    private function validateRequest(Request $request, array $rules): void
    {
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->messages()->all());
        }
    }

}