<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionController {
	/*.s. Muestra el formulario de inicio de sesión. */
	public function create(): View {
		return view('auth.login');
	}

	/*.s. Autentica las credenciales, regenera la sesión y redirige al destino solicitado o al panel. */
	public function store(LoginRequest $request): RedirectResponse {
		$request->authenticate();
		$request->session()->regenerate();

		return redirect()->intended(route('incidents.index'));
	}

	/*.s. Cierra la sesión, invalida sus datos y renueva el token CSRF antes de volver al formulario de acceso. */
	public function destroy(Request $request): RedirectResponse {
		Auth::logout();
		$request->session()->invalidate();
		$request->session()->regenerateToken();

		return redirect()->route('login');
	}
}
