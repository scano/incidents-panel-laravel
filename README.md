# Panel operativo de incidencias

Aplicación de la prueba técnica mid/senior para registrar incidencias, consultar su seguimiento y diagnosticar inconsistencias conservando la trazabilidad. Implementada con Laravel, MySQL, Blade/Livewire y Tailwind CSS.

## Requisitos

Para la instalación incluida se necesita Docker Engine con Docker Compose v2 o Docker Desktop, conexión a Internet y los puertos locales 8000, 5173 y 3306 disponibles. Los puertos se pueden cambiar en `.env` mediante `APP_PORT`, `VITE_PORT` y `MYSQL_PORT`.

| Componente | Versión utilizada |
| --- | --- |
| PHP | 8.4 |
| Laravel | 13 |
| Composer | 2 |
| MySQL | 8.4 |
| Node.js / npm | Node.js 24, con npm incluido en la imagen |
| Interfaz | Livewire 4, Tailwind CSS 4 y Alpine proporcionado por Livewire |

Docker ejecuta estas herramientas; no hace falta instalarlas en el equipo. Las versiones concretas de dependencias están fijadas en `composer.lock` y `package-lock.json`.

## Instalación y ejecución

**Windows:** usar Docker Desktop con WSL 2 y contenedores Linux, con la integración de Docker habilitada para la distribución WSL. 
Clonar el repositorio y ejecutar los comandos siguientes desde la terminal de WSL (por ejemplo, Ubuntu); `./bin/setup` requiere una shell Linux y no se ejecuta directamente en PowerShell.

Desde la raíz del repositorio:

```sh
./bin/setup

# Cargar las incidencias de ejemplo después del arranque.
docker compose exec app php artisan db:seed --no-interaction
```

Abrir [http://localhost:8000](http://localhost:8000). Vite se ejecuta en el puerto 5173 para servir los assets durante el desarrollo.

El script de instalación:

1. Copia `.env.example` a `.env` si todavía no existe.
2. Construye PHP, instala dependencias de Composer y npm, y compila los assets.
3. Genera `APP_KEY` si está vacía.
4. Arranca MySQL, ejecuta la migración y crea las cuentas de demostración.
5. Arranca Laravel y Vite.

El setup crea las cuentas, pero no carga las incidencias; el segundo comando carga 15 ejemplos con comentarios e historial coherente: 4 abiertos, 4 en revisión, 4 bloqueados y 3 resueltos. Repetir el seeder no duplica las muestras identificadas por título y solicitante `Ana (demo)`.

La base principal se llama **`incidents`**. La instalación también prepara una base y un usuario exclusivos para pruebas. `.env.example` solo contiene credenciales de desarrollo local; `.env` está excluido de Git. `APP_KEY` es la clave secreta de cifrado de Laravel: conservarla entre arranques y no publicarla.

Para cambiar puertos o credenciales antes de instalar, copiar `.env.example` a `.env` y ajustar sus valores. En un volumen MySQL ya inicializado, cambiar `DB_DATABASE`, el usuario o la contraseña en `.env` no crea ni renombra bases o cuentas existentes: deben ajustarse también en MySQL conservando los datos.

Comandos de uso habitual:

```sh
# Levantar una instalación ya preparada.
docker compose up -d

# Comprobar o aplicar migraciones pendientes.
docker compose exec app php artisan migrate:status
docker compose exec app php artisan migrate --no-interaction

# Cargar las cuentas y los datos coherentes de demostración.
docker compose exec app php artisan db:seed --no-interaction

# Consultar los registros.
docker compose logs -f app node mysql

# Compilar los assets después de cambios de frontend.
docker compose run --rm --no-deps node npm run build

# Detener conservando los datos.
docker compose down
```

Los datos persisten en el volumen `mysql_data`. `docker compose down -v` elimina los volúmenes y los datos. La migración única está destinada a instalaciones nuevas; una base con el historial anterior de migraciones requiere reconciliar ese historial antes de aplicarla, sin reiniciar las tablas del panel.

## Usuario de demostración

El acceso requiere iniciar sesión. Las cuentas locales creadas por el setup son:

| Usuario | Correo | Contraseña de demostración |
| --- | --- | --- |
| Ana | `ana@example.test` | `Incidencias123!` |
| Luis | `luis@example.test` | `Incidencias123!` |

El seeder no restablece contraseñas ni nombres de cuentas existentes y solo crea estas cuentas en entornos `local` y `testing`. Ambos usuarios pueden operar todas las incidencias. Se incluye acceso y cierre de sesión; no hay registro público, recuperación de contraseña ni roles.

## Funcionalidades y recorrido de revisión

1. Acceder con una cuenta de demostración y consultar el listado con prioridad, estado, fecha, solicitante y responsable.
2. Combinar búsqueda de texto y filtros de estado/prioridad. Los contadores permiten filtrar por estado y las incidencias bloqueadas aparecen resaltadas.
3. Pulsar **Nueva incidencia**, completar el formulario y guardar. Se validan los datos, se crea el primer evento de historial y se actualizan el listado y los contadores.
4. Abrir una incidencia y cambiar su estado. Bloquear o resolver solicita confirmación; reabrir una resuelta exige un motivo.
5. Añadir un comentario y consultar las pestañas de comentarios e histórico. El autor se obtiene de la sesión y cada operación relevante deja un evento.
6. Revisar el panel en escritorio y móvil; las validaciones se muestran junto al campo en español.

Livewire actualiza filtros, listado y contadores sin recargar toda la página. `IncidentDashboard`, `CreateIncident` e `IncidentDetail` mantienen el estado de sus vistas; los eventos `incident-created` e `incident-updated` coordinan su actualización. Alpine gestiona las pestañas y los diálogos. Los componentes delegan las escrituras en `IncidentService`.

## Modelo de datos y decisiones técnicas

Una única migración en `database/migrations/` crea las tablas necesarias:

| Tabla | Finalidad |
| --- | --- |
| `incidents` | Título, descripción, estado, prioridad, solicitante, responsable y fechas. |
| `incident_logs` | Acción, estado anterior/nuevo, actor, metadatos y fecha; marcas de duplicado para conservar evidencia. |
| `incident_comments` | Comentario, autor y fechas de seguimiento. |
| `users` | Cuentas para identificar a los actores de las operaciones. |
| `sessions` | Sesiones de acceso. |
| `cache`, `cache_locks` | Caché y bloqueos del driver de base de datos. |

Laravel administra además la tabla `migrations`. No se crean tablas de colas ni de recuperación de contraseñas, porque el proyecto no utiliza esas funciones.

- **Integridad:** claves foráneas de incidencias, usuarios y referencias a logs duplicados. Se restringe el borrado de registros con historial asociado.
- **Consultas:** índices por estado/fecha, prioridad/fecha y fecha; comentarios y logs incluyen índices por incidencia, fecha e ID.
- **Estados:** enums `IncidentStatus`, `IncidentPriority` e `IncidentAction` centralizan valores y evitan strings dispersos.
- **Escrituras y trazabilidad:** `IncidentService` valida y guarda cada operación junto con su log en una transacción. Las actualizaciones de estado bloquean la incidencia y rechazan un estado observado desactualizado.
- **Identidad:** logs y comentarios guardan `user_id` y una copia del nombre del actor. Los registros históricos o de mantenimiento pueden conservar `user_id = NULL` sin atribuirles una cuenta sin evidencia.
- **Interfaz y seguridad:** autenticación por sesión, CSRF, validación en servidor y textos escapados. El actor de una operación web se resuelve en el servidor.
- **Alcance:** aplicación pequeña con MySQL como único motor, sin roles avanzados, notificaciones, workers ni integraciones de negocio externas. Docker facilita la ejecución local.

Transiciones permitidas:

| Estado actual | Destinos |
| --- | --- |
| Abierta | En revisión |
| En revisión | Bloqueada o resuelta |
| Bloqueada | En revisión |
| Resuelta | Abierta, con motivo obligatorio |

La creación genera `created`; los cambios, `status_changed`; la reapertura, `reopened`; y los comentarios, `comment_added`. Los comentarios y las reparaciones no cuentan como transiciones al diagnosticar el último estado. La auditoría ordena los eventos por fecha e ID. Las fechas se almacenan en UTC y se presentan en Europe/Madrid.

## Diagnóstico y corrección de inconsistencias

La demostración del ejercicio se realiza en la **base principal `incidents`**, con la conexión MySQL normal del panel. No requiere crear una base de pruebas ni ejecutar PHPUnit.

### Preparar los tres casos

```sh
docker compose exec app php artisan db:seed --class=InconsistentIncidentSeeder --database=mysql --no-interaction
docker compose exec app php artisan incidents:audit --dry-run
```

El seeder crea tres incidencias con prefijo `[Auditoría demo]`. Es opcional, no se ejecuta en la carga estándar y no modifica otras incidencias. Repetirlo no duplica las muestras ni reintroduce inconsistencias ya corregidas.

| Caso | Simulación | Decisión |
| --- | --- | --- |
| Resuelta sin log de resolución | Se cambia directamente el estado a `resuelta` tras la revisión. | Revisión manual: faltan actor y fecha verificables; no se inventa un evento de resolución. |
| Estado distinto del último log | La incidencia indica `bloqueada`, pero el último estado del historial es `en_revision`. | Revisión manual: hay que contrastar qué ocurrió antes de elegir el estado correcto. |
| Log duplicado | Se copia exactamente el evento de paso de abierta a revisión. | Marcar la copia y registrar la reparación sin borrar el original. |

En una carga nueva, las tres muestras producen **cuatro hallazgos**: la primera también presenta discrepancia de estado.

### Aplicar la corrección segura

```sh
# Diagnosticar sin modificar datos; también es el comportamiento por defecto.
docker compose exec app php artisan incidents:audit --dry-run

# Marcar los duplicados exactos y registrar quién realiza la corrección.
docker compose exec app php artisan incidents:audit --fix --actor="Mantenimiento"

# Comprobar el resultado y la repetición sin nuevas correcciones.
docker compose exec app php artisan incidents:audit --dry-run
docker compose exec app php artisan incidents:audit --fix --actor="Mantenimiento"
```

Se puede añadir `--incident=ID` usando un ID real del panel para limitar el diagnóstico o la corrección a una incidencia. `--fix` y `--dry-run` son incompatibles.

`IncidentAuditService` recorre las incidencias por lotes de 100. Comprueba transiciones, continuidad del historial, resolución, reapertura con motivo y marcas de duplicado. Usa Query Builder con valores crudos para informar también de acciones o estados desconocidos.

Solo corrige eventos de estado válidos, consecutivos e idénticos en acción, valores, nombre e ID del actor, fecha y metadatos almacenados. Un ciclo de estados válido no se considera un duplicado. Los casos ambiguos quedan señalados para revisión manual.

La corrección añade `duplicate_of_id` y `voided_at` a la copia y crea un evento `duplicate_marked` con actor y evidencia original en `metadata`. El panel conserva los eventos originales y muestra la reparación. Se bloquean la incidencia y sus logs, se vuelve a diagnosticar y se guarda todo en una transacción por incidencia: si falla el evento de reparación, también se revierte la marca.

En las muestras nuevas se espera un duplicado marcado y **tres hallazgos manuales pendientes**. Repetir `--fix` debe marcar cero duplicados adicionales. Los casos sin evidencia suficiente requieren investigar el estado real y documentar la intervención antes de corregirlos.

| Código de salida | Significado |
| --- | --- |
| `0` | Sin hallazgos pendientes en las comprobaciones realizadas. |
| `1` | Quedan hallazgos; es el resultado esperado en la demostración, incluso tras corregir el duplicado. |
| `2` | Opciones inválidas. |

## Pruebas automatizadas adicionales

La comprobación del ejercicio es el recorrido del panel y la auditoría sobre `incidents` descritos arriba. El repositorio incluye además pruebas PHPUnit de operaciones, interfaz, autenticación, migraciones y auditoría.

La suite usa la conexión `mysql_testing`, la base `incidencias_testing` y el usuario `incidencias_test`, preparados por `bin/setup`. Usa el mismo servidor MySQL y la contraseña `DB_TEST_PASSWORD` (por defecto `local_test_password`). La suite reinicializa sus tablas y `Tests\TestCase` impide ejecutarla sobre la base del panel.

Si el entorno ya estaba instalado, preparar la base aislada:

```sh
docker compose up -d --wait mysql
docker compose exec -T mysql sh /docker-entrypoint-initdb.d/10-testing.sh
```

Ejecutar las pruebas:

```sh
docker compose exec app php artisan config:clear
docker compose exec app php artisan test --compact
```


## Entorno de desarrollo

- Host: macOS
- IDE: PhpStorm de Jetbrains
- IA: Codex
