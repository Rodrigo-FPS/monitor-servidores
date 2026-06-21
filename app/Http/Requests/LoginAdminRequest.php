<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LoginAdminRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Alfanumerico estricto con guiones: elimina riesgo de directory traversal y SQLi
            'username' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[a-zA-Z0-9_-]+$/'],
            'password' => ['required', 'string', 'min:8', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'El formato del nombre de usuario es invalido. No se permiten caracteres especiales.',
        ];
    }
}
