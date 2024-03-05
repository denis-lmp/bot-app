<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name'                  => 'required|string',
            'email'                 => 'required|string|email|unique:users',
            'password'              => 'required|min:8',
            'password_confirmation' => 'required|same:password'
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'                  => 'Name is required',
            'email.required'                 => 'Email is required',
            'email.email'                    => 'Email is not valid',
            'email.unique'                   => 'Email is already taken',
            'password.required'              => 'Password is required',
            'password.min'                   => 'Password must be at least 8 characters',
            'password_confirmation.required' => 'Password confirmation is required',
            'password_confirmation.same'     => 'Password confirmation must be the same as password'
        ];
    }
}
