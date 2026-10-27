<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Incidencias · Panel operativo</title>
    @vite(['resources/css/app.css'])
    @livewireStyles
</head>
<body>
    <a href="#workspace" class="skip-link">Saltar al listado</a>
    <livewire:incident-dashboard />
    @livewireScripts
</body>
</html>
