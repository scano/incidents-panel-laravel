<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /*.s. Permite enviar la solicitud de acceso sin exigir una sesión previa. */
    public function authorize(): bool
    {
        return true;
    }

    /*.s. Normaliza el correo eliminando espacios de los extremos y convirtiéndolo a minúsculas antes de validarlo. */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('email'))) {
            $this->merge(['email' => mb_strtolower(trim($this->input('email')))]);
        }
    }

    /*.s. Define los campos obligatorios y los límites de formato y longitud de las credenciales. */
    public function rules(): array
    {
        return ['email' => ['required', 'string', 'email', 'max:254'], 'password' => ['required', 'string', 'max:255']];
    }

    /*.s. Limita los intentos por correo e IP, comprueba las credenciales y limpia el contador cuando el acceso es correcto. */
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
