# MiniJira: tareas y aislamiento por clase

La rama `pruebacritica` parte de `MiniJira-Beta-codex`. Su alcance es proyectos,
tareas, entregas, versiones, comentarios, evaluación de tareas y reportes.
La universidad sigue siendo responsable de las matrículas, la gestión oficial
de docentes y las notas finales de materias. No se requiere una API institucional
para comenzar: el administrador registra las clases y sus listas autorizadas.

## Actualizar una instalación existente

Conservar `app/config/config.php`, las claves de cifrado y `storage/uploads/`.
Desde la raíz, usando el PHP configurado para esta instalación:

```bash
composer install --no-interaction
php database/migrate_beta.php
```

En XAMPP se puede usar `C:\xampp\php\php.exe database\migrate_beta.php`.
La migración es aditiva y repetible: crea `classrooms`, `classroom_members` y
`classroom_audit`, añade el contexto de proyecto/avisos y concede `classes.manage`
al rol con código `admin`. No reasigna roles ni importa la matrícula anterior.
En una base nueva, usar `database/install.sql` y ejecutar la migración después.
No volver a importar `install.sql` en una instalación con datos: ese instalador
reinicia permisos. Las migraciones académicas antiguas no son un prerrequisito.

Los proyectos antiguos permanecen intactos con clase sin clasificar, visibles
solo para supervisores con `projects.manage.all`. El administrador debe crear la
clase correcta, abrir **Editar proyecto**, escogerla y confirmar el equipo usando
su lista autorizada. También se pueden clasificar proyectos históricos vencidos.
No se adivinan clases a partir de carreras, nombres, integrantes o fechas.
Una vez clasificado, el proyecto no se puede mover a otra clase; sus entregas,
archivos y comentarios conservan el contexto original.

## Preparar clases y participantes

1. Crear las cuentas de plataforma que se necesiten desde Administración.
2. Abrir **Clases y participantes**. Registrar código externo único, materia,
   carrera, semestre curricular, grupo, período, docente y estado activo.
3. Pegar los correos de estudiantes autorizados, uno por línea, o importar CSV.
   La primera columna contiene el correo; admite cabecera `email` o `correo`,
   BOM UTF-8 y correos duplicados. El límite es 5000 participantes y 2 MB.
4. Las cuentas de estudiantes deben existir y estar activas. Un correo inválido
   o desconocido rechaza toda la actualización; no crea cuentas silenciosamente.

Guardar reemplaza la lista completa de esa clase, no la amplía implícitamente.
El CSV, si se adjunta, reemplaza el contenido del cuadro de texto. Una lista vacía
retira a todos. Los participantes omitidos pierden acceso y salen de los equipos
de esa clase. Si vuelven a ser autorizados, el docente debe agregarlos de nuevo
al proyecto: los equipos anteriores no se reactivan automáticamente.
Cada cambio registra actor, fecha y diferencias de participantes/docente/estado.
Los datos de cuentas institucionales deben sincronizarse con un proceso autorizado;
esta versión no consulta ni modifica la plataforma de la universidad.

## Reglas de aislamiento

- Un docente puede estar a cargo de varias clases; un estudiante puede pertenecer
  a varias clases, carreras y semestres a la vez. No existe una matrícula única.
- El docente solo crea proyectos en clases activas a su cargo y solo elige alumnos
  activos de esa lista. La validación se repite en el servidor y en la transacción.
- El estudiante necesita autorización de la clase y pertenencia al equipo para
  consultar un proyecto. Ser miembro de un proyecto por sí solo no concede acceso.
- **Mis clases** muestra docente y compañeros autorizados, sin correos, expedientes
  ni notas oficiales de otras personas. La información histórica producida dentro
  del proyecto conserva su contexto y no se publica fuera de la clase/equipo.
- Inactivar una clase revoca el acceso de sus participantes. Cambiar el docente
  actualiza el responsable de los proyectos y retira el acceso del anterior.
- Solo el docente autorizado con `tasks.manage` o el supervisor puede evaluar.
  Un estudiante ve sus notas y retroalimentación, no las de sus compañeros.
  El PDF del estudiante incluye el detalle personal; conserva el avance agregado
  del proyecto. El docente/supervisor puede obtener el informe del equipo.
- Páginas, tareas, comentarios, archivos, versiones, reportes y API aplican la
  misma política de proyecto. Las notificaciones referidas a un proyecto dejan
  de mostrarse al perder el acceso, incluso en un stream SSE que ya estaba abierto.
- Los avisos conservados en la base permiten auditoría; ocultarlos por revocación
  no equivale a borrarlos. Los recordatorios no se entregan a usuarios revocados.
- Las cuentas con `projects.manage.all` tienen supervisión global explícita.
  `classes.manage` permite administrar listas; no se concede a estudiantes/docentes
  por defecto. No otorgar capacidades administrativas como permisos de consulta.

Las rutas anteriores `/admin/academic.php` y `/user/academic.php` rechazan cualquier
POST con HTTP 410. Los GET conducen al contexto de clases con sus controles.
Las tablas académicas antiguas se preservan, pero no alimentan el flujo operativo.

## Paneles y rendimiento

El panel docente/estudiante ofrece período, carrera, materia, semestre, grupo y
estado (pendiente, en proceso, entregado/por evaluar, terminado, evaluado, vencido).
Las opciones provienen solo de clases autorizadas y se actualizan de forma
jerárquica. Los filtros están en la URL; navegación atrás y paginación los conservan.
Los indicadores corresponden al filtro aplicado. El estudiante lista sus propias
tareas; el docente lista las de sus proyectos autorizados.

Carga de trabajo, proyectos, analítica y administración de clases usan páginas de
25 filas. Los participantes de una clase se consultan de 25 en 25. Los selectores
administrativos de clases se limitan a 1000 opciones y los valores distintos de
filtros a 200; estos límites no conceden acceso a elementos no autorizados.
Los catálogos/expedientes académicos globales dejaron de cargarse al entrar.

Los conteos de proyectos agregan tareas sin multiplicarlas por los integrantes.
Los comentarios cargan adjuntos por lote. El tablero calcula bloqueos con los datos
ya leídos. Las tarjetas consultan métricas en una petición agrupada de hasta 25
proyectos cada ocho segundos; el gráfico seleccionado usa su consulta detallada.
Estas optimizaciones no constituyen una certificación de capacidad de producción:
los objetivos de concurrencia deben medirse en la infraestructura definitiva.

## Validación

Usar únicamente una base desechable cuyo nombre termine en `_test`. El primer
comando reinicia los datos de esa base, nunca la base de la aplicación:

```bash
SISTEMA_DB_DATABASE=minijira_beta_test php tests/beta.php
PHP_BIN=/ruta/php SUPERCRONIC_BIN=/ruta/supercronic \
  SISTEMA_DB_DATABASE=minijira_beta_test python3 tests/http_beta.py
```

La suite incluye dos docentes, dos clases, un estudiante compartido entre carreras,
un alumno de otra clase y un compañero de equipo. Verifica selección de estudiantes,
POST manipulados, notas privadas, retirada/restauración de permisos, cambio de
docente, clase inactiva, aislamiento de archivos/PDF/historial/API/avisos, revocación
SSE, importación CSV atómica, clasificación de legados y métricas agrupadas.
Chromium comprueba filtros, navegación, importación de participantes, entregas,
versiones y descargas. También se ejecutan las regresiones Beta y el cron real.

## Poblado para pruebas manuales

Ejecutar `php database/populate_demo.php` para crear un conjunto pequeño de datos.
Ver [cuentas, contraseña y escenarios demo](DEMO.md). No reinicia datos existentes.

## ULAT-29: reportes consolidados

La opción **Reportes** del menú abre `projects/reports.php`. Genera un reporte
actual de varios proyectos, filtrable por período, carrera, materia, semestre,
grupo, ID de clase y estudiante. El docente consulta sus proyectos; el estudiante
consulta exclusivamente sus tareas, aunque manipule el filtro de estudiante;
el supervisor con permiso global consulta el conjunto autorizado completo.

Muestra avance general y por proyecto, tareas vencidas y desempeño individual
consolidado (proyectos, tareas terminadas, evaluadas, puntos y porcentaje).
El avance global se calcula sobre tareas, no promediando porcentajes de proyectos.
El desempeño usa únicamente puntos posibles de tareas evaluadas; sin evaluaciones
se muestra «Sin evaluación». Un filtro individual restringe también el avance a
las tareas de ese estudiante. No se reconstruyen estados históricos.
El botón **Descargar PDF** exporta el mismo conjunto filtrado, con las mismas
reglas de acceso, sin recursos externos. Requiere las dependencias Composer
existentes. No requiere cambios en la base de datos.

Prueba de integración de solo lectura sobre el poblado demo sin modificaciones,
con el servidor iniciado y `pdftotext` disponible:

```bash
python3 tests/reports_demo.py
```

Puede configurarse `REPORT_TEST_URL` para indicar otra URL del servidor.
