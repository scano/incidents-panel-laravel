# Uso de IA

Se utilizó Codex como apoyo para:

- Preparar el entorno Docker con Laravel, PHP 8.4 y MySQL.
- Implementar incidencias, transiciones, comentarios, historial, autenticación y diagnóstico de inconsistencias.
- Desarrollar y ajustar el panel Livewire, sus filtros, paginación y diseño responsive.
- Crear pruebas y revisar la interfaz con Playwright.

Las decisiones siguieron el enunciado y las indicaciones del usuario

Durante el proceso se corrigieron errores de configuración, validación y comportamiento de la interfaz. 
Las comprobaciones fueron asistidas por Codex.
Se ha hecho una revisión personal completa de toda la implementación. 

Decisiones propias: 
Interfaz, estructura básica de la base de datos, separación del detalle en pestañas y comportamiento del scroll en listado, comentarios e historial, etc...

Correcciones de la IA: 
el orden del historial provocaba fallos al coincidir las fechas de varios eventos. 
Se separó el orden cronológico interno de la presentación, que muestra primero los más recientes.

Codex ayudó a implementar estas decisiones, corregir comportamientos y desarrollar y ejecutar las pruebas.