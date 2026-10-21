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

La migracion es repetible. Crea dos tablas y toma una observacion inicial solo
para proyectos que no tienen historial. Conserva observaciones previas, usuarios,
claves y datos. Aplicarla antes de poner la version Beta en servicio.
Para una base vacia, `database/install.sql` ya incluye ambas tablas.
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
Usa Chromium del sistema o `CHROMIUM_BIN`; alternativamente instalar el navegador
administrado por Playwright. No requiere credenciales externas.
