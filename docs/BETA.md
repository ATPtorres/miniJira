# MiniJira Beta codex

La rama `MiniJira-Beta-codex` incorpora notificaciones con actualizacion automatica,
recordatorios programados, metricas compartidas, analitica y reportes PDF.

## Instalacion y actualizacion

Requisitos: PHP 8.x con `pdo_mysql`, `openssl`, `session`, `mbstring`, `fileinfo`
y `dom`; Composer 2; MySQL/MariaDB. Para desarrollo local se recomienda PHP 8.4.

Desde la raiz del checkout, con la configuracion local existente:

```bash
composer install --no-interaction --prefer-dist
php database/migrate_beta.php
```

La migracion es repetible. Crea dos tablas, añade los campos de versiones y
autor de eliminación de ULAT-21, y toma una observacion inicial solo
para proyectos que no tienen historial. Conserva observaciones previas, usuarios,
claves y datos. Aplicarla antes de poner la version Beta en servicio.
Para una base vacia, `database/install.sql` ya incluye estas tablas y campos.
No volver a importar `install.sql` sobre una base en uso: reinicia permisos.
Las migraciones academicas siguen siendo independientes de esta actualizacion.

El servidor debe publicar unicamente `public/`. Ejemplo de desarrollo:

```bash
php -S 127.0.0.1:8080 -t public scripts/dev-router.php
```

`app.url` debe coincidir con el origen real. Puede sobrescribirse con
`SISTEMA_APP_URL`. Nunca publicar `app/`, `database/`, `storage/` o `vendor/`.

## Notificaciones

Los eventos de calendario notifican al crear, modificar y cancelar; los
comentarios de proyecto y tarea conservan sus destinatarios y reglas de anonimato.
La API toma siempre el usuario de la sesion, nunca de un parametro del cliente.
Marcar o borrar requiere CSRF y propiedad del aviso.

En Apache/FPM, `notifications.transport=auto` usa Server-Sent Events (SSE).
El stream libera el candado de sesion, revalida sesion/usuario cada dos segundos
y termina al cerrar sesion o dar de baja al usuario. Cada conexion dura hasta
20 segundos y el cliente vuelve a conectar; no necesita un broker externo.
El proxy inverso debe permitir streaming sin buffering (por ejemplo,
`proxy_buffering off` en Nginx) y suficientes workers PHP para solicitudes normales.

En el servidor PHP integrado se usan consultas cada cinco segundos para no
bloquear su unico worker. Tambien se usan si SSE falla o no esta disponible.
Se puede forzar `notifications.transport=poll` en la configuracion local.
La campana, el contador y el listado se actualizan sin recargar; los filtros y
paginacion del listado se conservan. El HTML de los mensajes se muestra como texto.

## Hilos y adjuntos (ULAT-23 y ULAT-24)

Los comentarios de proyecto y tarea muestran un comentario principal y sus
respuestas en orden cronológico. En el proyecto cualquier miembro puede abrir
un hilo. En tareas se conserva la regla existente: el docente abre hasta diez
hilos y los miembros pueden responder. No se admiten respuestas de tercer nivel
ni referencias a comentarios de otra tarea o proyecto.

Las respuestas de proyecto heredan la visibilidad del hilo. En uno privado
participan el autor original, el docente responsable y administradores globales;
los demás no ven ni comentarios, ni avisos, ni adjuntos. El autor de una raíz
anónima conserva el anonimato al responder. Los nuevos avisos llegan al docente
y al autor del hilo según su visibilidad, sin mostrar el nombre anónimo.

Todos los formularios, incluidos los de respuesta en tareas, permiten adjuntar
documentos o multimedia. Se validan extensión y MIME real, 10 MB por archivo y
50 MB compartidos por proyecto. Comentario, archivos y notificaciones se
confirman juntos; un fallo revierte registros y elimina los archivos recién
guardados. La descarga exige acceso al proyecto y al hilo privado.

El autor puede eliminar su comentario y el gestor moderarlo. Eliminar una raíz
elimina también sus respuestas y revoca todos sus adjuntos, libera el cupo y
limpia los archivos físicos. Los comentarios anteriores se conservan como raíces.
No hace falta una migración adicional en la rama Beta: ya dispone de `parent_id`
y `comment_id`.

## Historial de archivos y entregas (ULAT-21)

Desde **Archivos** o el detalle de una tarea se accede al historial por proyecto,
tarea o archivo. Muestra cada versión con autor, descripción, fecha/hora, tamaño,
estado actual/anterior/eliminado y descarga de versiones conservadas. El listado
está paginado de 50 en 50 y permite filtrar por tarea. Los adjuntos de comentarios
siguen en sus conversaciones; el historial no expone sus archivos privados.

En **Tipo de subida**, elegir **Archivo nuevo e independiente** o **Nueva versión
de…**. Una revisión admite un solo archivo, puede tener otro nombre y mantiene
su proyecto y tarea. Los nombres iguales por sí solos no agrupan entregas.
El estudiante solo versiona sus propias entregas en tareas asignadas y abiertas;
los gestores con permiso de subida pueden actualizar los archivos del proyecto.
La lista habitual muestra la última versión; las anteriores se descargan desde
el historial y todas cuentan para el cupo de 50 MB.

El servidor asigna los números y serializa revisiones concurrentes: si otra
persona ya actualizó el archivo, pide recargar en lugar de crear una rama o
sobrescribir contenido. Un error de guardado conserva las versiones anteriores.

Eliminar un archivo revoca **todas sus versiones**, libera su espacio y elimina
los archivos físicos; sus nombres, autores, fechas, versiones y autor/fecha de
eliminación permanecen en el historial. No se permite recuperar una descarga
eliminada cambiando el ID o enviando una nueva versión del archivo eliminado.
La eliminación fallida revierte todos los registros antes de tocar los archivos.

Aplicar `php database/migrate_beta.php` antes de usar esta actualización.
La migración es aditiva y repetible. Los archivos existentes comienzan como
versión 1 independiente; se conservan sus metadatos, pero no se reconstruyen
relaciones ni contenidos históricos que el sistema anterior no guardó.

## Recordatorios en segundo plano

El trabajo es independiente de las visitas del navegador:

```bash
php app/helpers/notification_cron.php
```

Genera avisos **dentro de la aplicacion**, no correos. Revisa vencimientos de
manana usando la zona horaria de `app.timezone`:

- Tareas pendientes/en proceso: al estudiante asignado, activo y miembro.
- Eventos e hitos: al docente responsable y estudiantes activos del proyecto.
- Proyectos pendientes de completar: al docente y estudiantes activos.

Una clave por entidad, destinatario y fecha evita repetir entregas incluso si
se borra el aviso. Una nueva fecha de vencimiento permite un nuevo recordatorio.
Un candado de MariaDB/MySQL impide ejecuciones simultaneas, y cada entrega y su
clave se guardan en una sola transaccion. Los errores devuelven codigo distinto
de cero y se registran en `storage/logs/reminders.log`.

En Linux con cron, adaptar e instalar la entrada de
`scripts/reminders.crontab.example` en el crontab del usuario de la aplicacion,
conservando los trabajos existentes. Se ejecuta cada diez minutos; la deduplicacion
permite reintentos durante el dia.

En contenedores o maquinas sin cron, puede usarse Supercronic sin privilegios:

```bash
scripts/install-supercronic.sh /ruta/bin
PHP_BIN=/ruta/php SUPERCRONIC_BIN=/ruta/bin/supercronic scripts/start-reminders.sh
```

El instalador comprueba el hash publicado por Aptible para la version fijada.
`start-reminders.sh` valida el crontab y deja el runner en primer plano para que
el supervisor del contenedor lo gestione. Los procesos deben reiniciarse al
restaurar un entorno; el archivo de crontab por si solo no ejecuta trabajos.
En Windows, programar el mismo comando PHP con el Programador de tareas.

## Avance y analitica

`ProjectMetrics` define el avance: tareas `done` / tareas no eliminadas × 100,
redondeado a entero; sin tareas devuelve 0 %. Puntos y calificaciones no cambian
el peso de las tareas. Un proyecto es final solo si tiene tareas y todas estan
terminadas, independientemente del redondeo del porcentaje.

Crear, completar, reabrir o eliminar una tarea registra el estado observado en
la misma transaccion que el cambio. Un fallo del historial revierte la tarea.
Los graficos muestran la ultima observacion de cada dia registrado, sin inventar
historia previa a Beta. El avance temporal usa fechas con signo e incluye el dia
limite completo.

`projects/analytics.php` ofrece seleccion de proyecto, filtro de hasta 366 dias,
graficos por estado, avance por proyecto y desglose por estudiante. Solo muestra
proyectos a los que el usuario tiene acceso. Paneles y graficos se actualizan
cada ocho segundos. Las graficas SVG incluyen datos tabulares accesibles y no
requieren servicios externos ni CDN. El panel administrativo ya no muestra
curvas, tendencias ni auditoria de ejemplo.

## Reportes PDF

Desde el panel del proyecto o Analitica, usar **Descargar informe PDF**.
El informe contiene fecha de generacion, responsable, fechas, descripcion,
objetivos, avance, grafico de distribucion, estudiantes, tareas y notas.
Se titula "Informe final" cuando todas las tareas estan terminadas;
en los demas casos, "Informe de avance".

La descarga valida el mismo acceso al proyecto que el panel. Excluye comentarios
y adjuntos privados/anonimos; no incluye secretos de configuracion.
Dompdf tiene deshabilitados recursos remotos, ejecucion PHP y JavaScript.
Los valores de usuario se escapan y el texto soporta acentos mediante DejaVu Sans.

## Verificacion

### Entregables (ULAT-19)

En el detalle de la tarea, el estudiante asignado puede subir uno o varios
entregables con su permiso `tasks.update.assigned`. No necesita el permiso de
recursos generales `files.upload`; este sigue reservado a quienes gestionan
el proyecto. La tarea debe pertenecer al proyecto y el estudiante debe seguir
siendo miembro. Las tareas bloqueadas (vencidas o calificadas) y los proyectos
vencidos no aceptan nuevas entregas del estudiante; el docente conserva su acceso.

Cada archivo registra automáticamente tarea, autor y fecha/hora del servidor,
que aparecen junto al archivo. Después de subir, se vuelve a la tarea y se avisa
al docente con un enlace directo. Se valida el lote completo, tipo, extensión,
10 MB por archivo y 50 MB por proyecto; si falla el guardado, se revierte el lote.
Este arreglo no requiere migraciones de base de datos.

Para admitir archivos de hasta 10 MB, configurar PHP con
`upload_max_filesize=10M` y `post_max_size=55M` (y reiniciar Apache/FPM).
El límite de PHP puede ser más restrictivo que el de la aplicación. Ajustar
también el límite del servidor HTTP si corresponde.

### Pruebas automatizadas

`tests/beta.php` inicializa y reinicia datos de la base seleccionada: usar
exclusivamente una BD dedicada, con un nombre que termine en `_test` y un usuario
con permisos sobre ella. No usar una copia con datos que se deban conservar.
`SISTEMA_DB_DATABASE` cambia la BD sin modificar configuracion ni credenciales.

```bash
SISTEMA_DB_DATABASE=minijira_beta_test php tests/beta.php
```

Comprueba avance 0/50/100, reapertura, borrado, atomicidad del historial, fechas,
permisos, recordatorios idempotentes y candados, aislamiento de notificaciones,
graficos y generacion PDF. Deja datos sinteticos para las pruebas HTTP siguientes.

Para HTTP, SSE, navegador y scheduler se necesitan Python 3 con Playwright
(`python3 -m pip install -r tests/requirements.txt`), Chromium y `pdftotext`:

```bash
PHP_BIN=/ruta/php SUPERCRONIC_BIN=/ruta/bin/supercronic \
  SISTEMA_DB_DATABASE=minijira_beta_test python3 tests/http_beta.py
```

El runner levanta su propio servidor de pruebas con varios workers en el puerto
8081, verifica aislamiento y CSRF, comentarios privados/anonimos, eventos,
revocacion SSE al cerrar sesion, concordancia PDF/panel, actualizacion visible en
Chromium y ejecucion real de un Cron de prueba. Detiene los procesos que crea.
Incluye peticiones multipart de ULAT-19: entrega con permisos predeterminados,
fecha/hora y autor no falsificables, descarga y aviso al docente, aislamiento
entre tareas y proyectos, cierres por vencimiento/calificación, CSRF, límites,
lotes múltiples y reversión de archivos y registros ante un fallo de base de datos.
Las pruebas de ULAT-23/24 verifican hilos de proyecto/tarea, privacidad heredada,
anonimato, destinatarios de avisos, adjuntos de texto/imagen/audio, aislamiento,
límites y reversión completa, eliminación de hilos y descarga desde Chromium.
Las pruebas de ULAT-21 cubren versiones y descargas anteriores, permisos,
concurrencia real de dos subidas, atomicidad de revisión y eliminación, historial
de eliminados, paginación, privacidad de comentarios, migración repetida y el
flujo de actualización/descarga de versiones en Chromium.
Usa Chromium del sistema o `CHROMIUM_BIN`; alternativamente instalar el navegador
administrado por Playwright. No requiere credenciales externas.
