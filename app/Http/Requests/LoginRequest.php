<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:254'], 'password' => ['required', 'string', 'max:255']];
    }

    public function authenticate(): void
    {
        $key = 'login:'.hash('sha256', $this->string('email')->toString().'|'.$this->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Demasiados intentos. Vuelve a intentarlo en '.RateLimiter::availableIn($key).' segundos.',
            ]);
        }

        if (! Auth::attempt($this->validated())) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'El correo o la contraseña no son correctos.']);
        }

        RateLimiter::clear($key);
    }
}
