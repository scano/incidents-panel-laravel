<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceder · Panel operativo de incidencias</title>
    @vite(['resources/css/app.css'])
</head>
<body>
    <main class="login-page">
        <section class="login-content" aria-labelledby="login-heading">
            <p class="eyebrow">PANEL OPERATIVO DE INCIDENCIAS</p>
            <h1 id="login-heading">Accede a tu espacio</h1>
            <p class="login-description">Consulta y gestiona las incidencias de tu equipo.</p>
            <form method="POST" action="{{ route('login.store') }}" class="login-form">
                @csrf
                <x-field name="email" label="Correo electrónico">
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required
                        maxlength="254" autocomplete="username" autofocus
                        @error('email') aria-invalid="true" aria-describedby="email-error" @enderror>
                </x-field>
                <x-field name="password" label="Contraseña">
                    <input id="password" name="password" type="password" required maxlength="255" autocomplete="current-password"
                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                </x-field>
                <button type="submit" class="button-primary login-submit">Entrar al panel <span aria-hidden="true">→</span></button>
            </form>

        </section>
    </main>
</body>
</html>
