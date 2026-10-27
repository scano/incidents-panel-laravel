# Panel operativo de incidencias

Prueba técnica Laravel / MySQL. Estado actual: entorno Docker, modelo de datos, servicio transaccional, panel Livewire y autenticación por sesión implementados. Incluye diagnóstico de inconsistencias con modo dry-run y corrección conservadora de duplicados.

## Requisitos

Solo Docker Engine con Docker Compose v2 o superior (o Docker Desktop), conexión a Internet para la instalación y los puertos locales 8000 y 5173 libres. No es necesario instalar PHP, Composer, Node ni MySQL en el equipo.

El entorno utiliza Laravel 13, PHP 8.4, Composer 2, MySQL 8.4 y Node 24 con npm. Las imágenes fijan la rama de versión y reciben sus parches; `composer.lock` y `package-lock.json` fijan las dependencias de la aplicación.

## Instalación

Desde la raíz del repositorio:

```sh
./bin/setup
```

El script copia `.env.example` si falta `.env`, construye PHP con sus extensiones, instala dependencias, genera la clave si está vacía, compila los assets, espera a MySQL, prepara su base y usuario de pruebas, ejecuta migraciones, crea las cuentas de demostración en entorno local y levanta Laravel y Vite. Se puede volver a ejecutar: no borra los datos ni reemplaza la clave existente. La primera ejecución tarda más por la descarga y construcción de imágenes.

Abrir http://localhost:8000 para acceder al panel operativo. El endpoint `/up` comprueba que la aplicación arranca; no comprueba la base de datos.

Para cambiar puertos, copiar primero `.env.example` a `.env` y ajustar `APP_PORT`, `APP_URL` y `VITE_PORT` antes de ejecutar el script. Vite está configurado para acceso local por `localhost`.

## Acceso al panel

El panel requiere iniciar sesión en `/login`. `./bin/setup` crea estas cuentas en el entorno local:

| Usuario | Correo | Contraseña de demostración |
| --- | --- | --- |
| Ana | `ana@example.test` | `Incidencias123!` |
| Luis | `luis@example.test` | `Incidencias123!` |

En una instalación existente:

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --class=UserSeeder
```

El seeder solo crea cuentas en `local` y `testing`, y no restablece nombres o contraseñas si los correos ya existen. Las contraseñas se almacenan con hash. No hay registro público, recuperación de contraseña, roles ni permisos diferenciados: ambos usuarios pueden operar todas las incidencias.

El login utiliza el guard de sesión de Laravel, regenera la sesión al acceder y limita a cinco intentos fallidos por correo/IP durante 60 segundos. El cierre de sesión es un POST con CSRF, invalida la sesión y regenera el token. El panel usa middleware `auth`; los componentes Livewire comprueban también la autenticación en cada petición. Las credenciales incorrectas devuelven un mensaje genérico y la contraseña no se conserva en los datos del formulario.

## Trabajo diario

```sh
# Arrancar servicios ya instalados
docker compose up -d

# Estado y registros
docker compose ps
docker compose logs -f app node mysql

# Artisan, Composer y npm
docker compose exec app php artisan migrate:status
docker compose exec app php artisan test
docker compose exec app composer install
docker compose run --rm --no-deps node npm ci
docker compose run --rm --no-deps node npm run build

# Detener conservando los datos
docker compose down
```

Las pruebas incluyen creación, validación, transiciones, reapertura, datos desactualizados, comentarios, autenticación y rollback si falla el log. Tanto la aplicación como las pruebas utilizan MySQL 8.4. La suite usa una base y un usuario exclusivos, separados del panel.

## Modelo y reglas de negocio

`incidents` contiene el estado actual; `incident_logs`, el historial de operaciones; `incident_comments`, el seguimiento. Las claves foráneas impiden borrar una incidencia con historial o comentarios. Hay índices por estado/fecha, prioridad/fecha y fecha, y para consultar el historial de cada incidencia. El historial se ordena por fecha e ID para desempatar eventos del mismo segundo. Las fechas se almacenan en UTC.

Los enums `IncidentStatus`, `IncidentPriority` e `IncidentAction` centralizan los valores. Los estados y prioridades se guardan como strings para permitir cambios futuros mediante el código, sin depender de un ENUM específico de MySQL. Las escrituras operativas deben pasar por `App\Services\IncidentService`; escribir directamente con Eloquent o SQL omite estas reglas.

| Estado actual | Destinos permitidos |
| --- | --- |
| Abierta | En revisión |
| En revisión | Bloqueada, resuelta |
| Bloqueada | En revisión |
| Resuelta | Abierta, con motivo obligatorio y evento `reopened` |

La creación siempre empieza abierta. En la interfaz, el actor de creación, los cambios de estado y los comentarios se obtienen del usuario autenticado; el navegador no envía su identidad. El solicitante es independiente, porque se puede registrar una incidencia en nombre de otra persona. Las prioridades admitidas son baja, media, alta y crítica.

Cada operación valida datos y escribe la incidencia o comentario junto con su log en una transacción. Para cambiar un estado se obtiene la fila con `lockForUpdate`, se compara con el estado del modelo aportado por el llamador y se valida la transición antes de escribir. Esto rechaza estados desactualizados; el llamador debe conservar el estado que vio el usuario. No es un control de versiones completo: no detecta una secuencia de cambios que termina en el mismo estado inicial. La interfaz conserva este estado observado hasta actualizarlo explícitamente.

La creación registra `created`; los cambios, `status_changed` o `reopened`; los comentarios, `comment_added` con el ID del comentario en los metadatos. Los logs de comentarios no representan transiciones y deberán excluirse al diagnosticar el último estado registrado. Los logs no tienen `updated_at` y no se ofrecen operaciones de edición o borrado.

## Datos de ejemplo

```sh
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed
```

Además de las dos cuentas de demostración, el seeder genera 15 incidencias (4 abiertas, 4 en revisión, 4 bloqueadas y 3 resueltas) mediante el servicio, con comentarios e historial válido. Repetirlo conserva las incidencias de demostración existentes sin duplicarlas, identificadas por título y solicitante `Ana (demo)`. Las inconsistencias deliberadas se introducen mediante un seeder independiente, descrito más abajo. Las factories sirven para preparar datos de pruebas; su creación directa no genera historial.

## Pruebas con MySQL

```sh
docker compose exec app php artisan test
```

`phpunit.xml` selecciona obligatoriamente la conexión `mysql_testing`. Utiliza el mismo servidor MySQL 8.4, pero con la base fija `incidencias_testing` y el usuario `incidencias_test`, que solo tiene permisos sobre esa base. La conexión de pruebas no utiliza `DB_DATABASE`, `DB_USERNAME` ni `DB_URL`, para evitar que una variable de la aplicación redirija las pruebas a los datos del panel. Usa `DB_HOST`, `DB_PORT` y `DB_TEST_PASSWORD`.

El script `docker/mysql/init-testing.sh` crea la base y el usuario en la primera inicialización de MySQL. `./bin/setup` también lo ejecuta expresamente para instalaciones con un volumen ya existente. Si se cambia `DB_TEST_PASSWORD`, hay que volver a ejecutar el setup para sincronizar la contraseña de la cuenta de pruebas.

Para preparar únicamente la base de pruebas en una instalación existente:

```sh
docker compose up -d --wait mysql
docker compose exec -T mysql sh /docker-entrypoint-initdb.d/10-testing.sh
```

La suite ejecuta migraciones y reinicializa las tablas de la base de pruebas. `Tests\TestCase` comprueba la conexión antes de continuar y aborta si la configuración no corresponde a la base y al usuario reservados. Si hay una configuración de Laravel cacheada de un entorno distinto, ejecutar `docker compose exec app php artisan config:clear` antes de las pruebas.

Las pruebas verifican el comportamiento sobre MySQL, incluyendo transacciones y estado desactualizado. No simulan dos conexiones concurrentes.

## Servicios y datos

- `app`: servidor de desarrollo de Laravel en el puerto local 8000, con código montado desde este directorio. PHP incluye PDO MySQL, mbstring, intl, zip, bcmath y pcntl; la aplicación y las pruebas usan exclusivamente MySQL.
- `mysql`: accesible desde `app` como `mysql:3306`. El healthcheck comprueba una consulta con el usuario de la aplicación. Los datos se guardan en el volumen `mysql_data`.
- `node`: Vite con recarga en caliente en el puerto local 5173. `node_modules` vive en un volumen para evitar mezclar dependencias de macOS y Linux.

Las credenciales de `.env.example` son exclusivamente locales. `.env` está excluido de Git. MySQL no expone puertos al host; la aplicación y Vite se publican solo en loopback. Este entorno usa `artisan serve` y está destinado al desarrollo local.

Cambiar las variables de usuario o contraseña en `.env` no cambia usuarios de una base ya inicializada: MySQL solo utiliza esas variables al crear el volumen. Se deben actualizar las credenciales en MySQL o recrear sus datos deliberadamente.

`docker compose down -v` elimina los volúmenes, **incluida toda la base de datos**. No es necesario para parar ni reiniciar el proyecto.

## Uso del panel

- **Nueva incidencia** abre el formulario. Al guardar, cierra el formulario, limpia los filtros, actualiza el listado y muestra una confirmación sin abrir el detalle.
- La búsqueda consulta título, descripción, solicitante y responsable. Se combina con estado y prioridad; los filtros se reflejan en la URL y reinician la paginación. El listado muestra diez incidencias por página, con la más reciente primero. La paginación usa textos en español y controles claros separados del resumen; en móvil muestra Anterior, posición de página y Siguiente.
- Los contadores muestran totales globales, independientemente de los filtros. Pulsarlos activa o desactiva el filtro del estado correspondiente. Las incidencias bloqueadas se resaltan en el listado.
- El título de una incidencia abre su detalle con las pestañas Datos de la incidencia, Comentarios e Histórico. El histórico tiene scroll propio y mantiene visibles la cabecera y las pestañas. La cabecera mantiene visibles el título, estado y prioridad. Las pestañas se manejan también con flechas, Inicio y Fin, y conservan los borradores al cambiar de sección. El actor se obtiene de la sesión y las reaperturas exigen un motivo. Bloquear y resolver solicitan confirmación en el navegador.
- Si cambia el estado desde otra sesión, el intento de actualización se rechaza. **Actualizar datos** obtiene el estado actual para continuar.
- Los comentarios toman el autor de la sesión y se registran junto con su evento. Se muestran del más reciente al más antiguo, con scroll propio y el formulario fijo debajo. Al publicar uno nuevo, se muestra arriba y el listado vuelve al inicio. Los textos se muestran escapados. Las validaciones se presentan en español junto al campo correspondiente.
- Los diálogos mantienen el foco dentro de su contenido y se cierran con Escape, el botón de cierre o el fondo. Las animaciones respetan la preferencia de movimiento reducido. Las fechas se presentan en la zona Europe/Madrid.

Los componentes `IncidentDashboard`, `CreateIncident` e `IncidentDetail` mantienen su propio estado de interfaz; todas las escrituras pasan por `IncidentService`. Los eventos `incident-created` e `incident-updated` coordinan el cierre del formulario y el refresco del listado y contadores. El estado observado del detalle y su ID están protegidos con `Locked`, para conservar la comprobación del servicio frente a datos desactualizados.

## Diagnóstico y corrección de inconsistencias

El diagnóstico es un comando Artisan independiente de la interfaz. Para preparar los tres casos del ejercicio en una instalación nueva:

```sh
docker compose exec app php artisan db:seed --class=InconsistentIncidentSeeder
```

Este seeder es opcional y no se ejecuta con `db:seed` por defecto. Crea tres incidencias con prefijo `[Auditoría demo]`; repetirlo no duplica incidencias ni reintroduce errores ya corregidos. No altera incidencias preexistentes fuera de esas muestras.

| Caso | Simulación | Decisión |
| --- | --- | --- |
| Resuelta sin log de resolución | Tras la revisión se actualiza directamente la fila a `resuelta` | Revisión manual: no sabemos quién resolvió ni cuándo; no se fabrica un evento histórico. |
| Estado distinto del último log | La fila indica `bloqueada` y el último cambio registrado, `en_revision` | Revisión manual: ninguno de los dos datos demuestra por sí solo lo que ocurrió. |
| Log duplicado | Se copia exactamente el cambio de abierta a revisión | Se marca la copia como duplicada, se conserva el original y se registra la reparación. |

La primera muestra genera dos hallazgos (falta de resolución y discrepancia), por lo que las tres incidencias producen **cuatro hallazgos** inicialmente.

```sh
# Sin escrituras: comportamiento por defecto
docker compose exec app php artisan incidents:audit
# Opción explícita equivalente
docker compose exec app php artisan incidents:audit --dry-run
# Aplicar solo correcciones seguras, firmadas por un actor
docker compose exec app php artisan incidents:audit --fix --actor="Mantenimiento"
# Limitar el diagnóstico o la corrección a un ID real
docker compose exec app php artisan incidents:audit --incident=9 --dry-run
```

El ID del último ejemplo es ilustrativo; usar uno existente en el listado. `--fix` y `--dry-run` son incompatibles. Un ID desconocido, mal formado o un actor vacío se rechazan antes de escribir.

Códigos de salida: `0` sin hallazgos pendientes en las comprobaciones realizadas; `1` con hallazgos pendientes; `2` para opciones inválidas. Por eso un dry-run que encuentra los casos de ejemplo termina con código 1. Tras `--fix`, los dos casos ambiguos siguen presentes y el código continúa siendo 1: no significa que la reparación del duplicado haya fallado.

### Qué comprueba y cómo corrige

`IncidentAuditService` recorre incidencias por lotes de 100 y lee los logs ordenados por `(created_at, id)`. Usa Query Builder y valores crudos para poder informar de estados o acciones desconocidos sin fallar por los casts de los enums. Comprueba creación, transiciones permitidas, reapertura con motivo, continuidad de la cadena, coincidencia del estado actual y existencia de un evento válido de resolución. Los comentarios y eventos de reparación no son cambios de estado.

Una copia solo es reparable automáticamente si es un evento de estado válido e idéntico al evento de estado anterior en acción, valores, actor, ID de usuario, fecha y metadatos almacenados. No debe existir otra transición entre ambos. Diferencias de actor, fecha o metadatos, cadenas incompletas, estados desconocidos y marcas de duplicado inválidas quedan para revisión. La comparación es deliberadamente estricta: dos JSON equivalentes con distinta representación pueden requerir revisión manual.

La corrección no borra ni reescribe los valores históricos: añade `duplicate_of_id` y `voided_at` a la copia y genera un evento `duplicate_marked` con actor, fecha, IDs y una copia de los datos originales en `metadata`. El historial del panel muestra tanto la copia marcada como el evento de reparación. Los diagnósticos posteriores excluyen las copias cuya referencia al original idéntico puede verificarse. Repetir `--fix` no añade más marcas ni eventos.

Cada incidencia se procesa en su propia transacción. En modo `--fix` se bloquean la incidencia y sus logs, y se vuelve a calcular el diagnóstico antes de modificar datos; si falla el nuevo evento de auditoría, también se revierte la marca. La atomicidad es por incidencia, no por toda la ejecución: si falla una incidencia posterior, las anteriores pueden estar ya corregidas. El bloqueo se coordina con el servicio de operaciones normal, que también bloquea la incidencia; no puede garantizar coordinación con escrituras externas que omitan ese protocolo. Las pruebas no simulan dos conexiones concurrentes.

Los casos manuales se señalan en la salida del comando sin cambiar estado ni inventar logs. Para resolverlos hay que contrastar con evidencia externa, identificar el estado correcto y conservar la explicación, el actor y la fecha real de la intervención. El ejercicio permite dejarlos sin corrección automática cuando faltan datos.

### Consultas SQL de detección

Estas consultas MySQL 8.4 se han ejecutado contra los ejemplos. Explican la detección de los tres casos; el servicio aplica además las validaciones descritas arriba antes de considerar un evento válido o una reparación segura.

**Resueltas sin evento válido de resolución:** `NOT EXISTS` busca la ausencia de un cambio desde revisión a resuelta, con actor y sin marca de duplicado.

```sql
SELECT i.id, i.status
FROM incidents AS i
WHERE i.status = 'resuelta'
  AND NOT EXISTS (
    SELECT 1 FROM incident_logs AS l
    WHERE l.incident_id = i.id AND l.duplicate_of_id IS NULL
      AND l.action = 'status_changed'
      AND l.old_value = 'en_revision' AND l.new_value = 'resuelta'
      AND TRIM(l.user_name) <> ''
  );
```

**Estado distinto del último log:** la ventana selecciona el último evento de estado por fecha e ID. El `LEFT JOIN` detecta también la ausencia de historial; `<=>` compara de forma segura cuando hay valores nulos.

```sql
WITH ranked AS (
  SELECT id, incident_id, new_value,
    ROW_NUMBER() OVER (PARTITION BY incident_id ORDER BY created_at DESC, id DESC) AS rn
  FROM incident_logs
  WHERE duplicate_of_id IS NULL AND action IN ('created', 'status_changed', 'reopened')
)
SELECT i.id, i.status, l.id AS log_id, l.new_value
FROM incidents AS i
LEFT JOIN ranked AS l ON l.incident_id = i.id AND l.rn = 1
WHERE l.id IS NULL OR NOT (i.status <=> l.new_value);
```

**Candidatos a duplicado:** `LAG` identifica el evento de estado anterior de cada incidencia. La consulta exige igualdad de fecha, actor, acción, valores y JSON almacenado; los casts binarios evitan que la collation ignore diferencias de mayúsculas. Los ciclos válidos con transiciones intermedias no son duplicados.

```sql
WITH adjacent AS (
  SELECT id, LAG(id) OVER (PARTITION BY incident_id ORDER BY created_at, id) AS previous_id
  FROM incident_logs
  WHERE duplicate_of_id IS NULL AND action IN ('created', 'status_changed', 'reopened')
)
SELECT d.incident_id, d.id AS duplicate_id, c.id AS canonical_id
FROM adjacent AS a
JOIN incident_logs AS d ON d.id = a.id
JOIN incident_logs AS c ON c.id = a.previous_id
WHERE BINARY d.action = BINARY c.action
  AND (BINARY d.old_value <=> BINARY c.old_value)
  AND (BINARY d.new_value <=> BINARY c.new_value)
  AND BINARY d.user_name = BINARY c.user_name
  AND (d.user_id <=> c.user_id)
  AND (BINARY CAST(d.metadata AS CHAR) <=> BINARY CAST(c.metadata AS CHAR))
  AND d.created_at = c.created_at;
```

El seeder estándar genera datos coherentes. Las muestras inconsistentes solo aparecen al ejecutar expresamente `InconsistentIncidentSeeder`; la suite las reconstruye en la base aislada de pruebas.

La autenticación y las cuentas de prueba se describen en «Acceso al panel». El panel utiliza Livewire 4 y Tailwind 4. La documentación de asistencia con IA está en `ai-process/README.md`.

## Verificación del entorno

Comprobado en Docker Desktop sobre macOS ARM64: instalación mediante `./bin/setup`, compilación de assets, migraciones ejecutadas en MySQL y respuesta HTTP 200 del panel autenticado y Vite, y redirección a `/login` para invitados. El servicio de dominio cuenta con 22 casos correctos (89 aserciones) ejecutados en MySQL 8.4. Además, los diez casos de interfaz pasan en MySQL 8.4. La suite completa contiene 62 casos y 326 aserciones correctas, ejecutados en MySQL 8.4. Los trece casos de auditoría cubren dry-run, repetición del seeder, corrección idempotente, conservación de evidencia, rollback, entradas inválidas, alcance por ID, datos desconocidos, ciclos válidos y falsos duplicados. Se revisó con Playwright en escritorio y móvil (390 px), incluyendo creación, cambio de estado, cancelación y aceptación de confirmación, comentarios, cierre con Escape y ausencia de desbordamiento horizontal. La base local se ha reiniciado después de esas comprobaciones con los seeders estándar.

## Limpieza del esqueleto

Se retiraron la bienvenida, el comando de ejemplo `inspire` y `routes/console.php`, el controlador y proveedor vacíos, el modelo/factory de usuario inicial (reintroducido después al implementar el login), el test unitario trivial y la entrada JavaScript vacía. La prueba inicial de la portada se sustituyó por `IncidentPageTest`, que comprueba que se renderiza el panel. El comando `incidents:audit` se descubre desde `app/Console/Commands`.

Vite compila el CSS; Livewire proporciona Alpine y el JavaScript de interacción. Se eliminaron Tinker, Pail, Pao, Concurrently y Multiplex, y los scripts de creación/desarrollo heredados. El arranque documentado sigue siendo `./bin/setup` y `docker compose up -d`. La cola queda configurada como síncrona, sin workers.

Se conservan las migraciones iniciales ya aplicadas para mantener su historial, incluida la que crea `sessions`. Las sesiones y la caché siguen utilizando MySQL. Tras añadir el login, el guard usa el modelo `User` de este proyecto; solo se ofrecen acceso y cierre de sesión. Se mantiene la configuración del framework y las herramientas de desarrollo que utiliza este proyecto, como PHPUnit, Pint y Boost.

## Identidad en el historial

Los nuevos logs y comentarios de la interfaz guardan `user_id` como clave foránea nullable, además de `user_name` o `author_name` como copia del nombre en ese momento. Cambiar el nombre de una cuenta no modifica el historial previo; las relaciones `user()` permiten consultar la cuenta vinculada. Las claves foráneas impiden borrar usuarios con registros asociados.

La migración añade las columnas sin atribuir los registros antiguos a cuentas nuevas. Los datos históricos anteriores al login y los eventos de mantenimiento pueden conservar un nombre declarado y `user_id = NULL`. Los seeders de incidencias actuales utilizan las cuentas Ana y Luis para guardar sus IDs y nombres en el historial y los comentarios. El servicio acepta un `User` para las operaciones autenticadas y nombres explícitos para los procesos internos de demostración/mantenimiento. Los componentes web siempre le pasan el usuario resuelto en el servidor. La auditoría compara también `user_id` al identificar duplicados, para distinguir cuentas con nombres iguales.

Las pruebas de autenticación verifican redirección de invitados, login, errores y validación, regeneración de sesión, limitación de intentos, logout, protección directa de componentes, sesión caducada, rechazo de identidad inyectada, solicitante independiente, snapshots de nombre e idempotencia del seeder de cuentas.

La autenticación se verificó también con Playwright: login de Ana, nombre visible en el panel, detalle sin campos manuales de identidad, revisión a 390 px, cierre de sesión y redirección de vuelta al acceso sin errores de consola.

Se comprobó la inicialización de la base y la cuenta de pruebas tanto en el volumen existente como en una instancia MySQL temporal desde cero. El usuario de pruebas recibe acceso denegado al intentar abrir la base del panel.
