# Usabilidad de BetaJiraFinal

## Reportes en móvil

El reporte consolidado conserva todos sus datos y filtros. Hasta 700 px, las
filas se muestran como fichas de dos columnas, con nombre/contexto y etiquetas
visibles. Se mantienen las cabeceras y roles de tabla para lectores de pantalla.
Los nombres extensos pueden partirse sin ensanchar la página. En escritorio e
impresión continúa la tabla habitual.

Al entrar desde un teléfono, los filtros se pliegan en «Filtros del reporte»;
el usuario puede abrirlos con ratón, toque o teclado. «Descargar PDF» permanece
visible y exporta los resultados de los filtros **aplicados**, no los cambios
que aún no se hayan enviado. El PDF no muestra las etiquetas exclusivas de las
fichas móviles. No cambian los cálculos ni las reglas de acceso.

### Seleccionar una clase

El filtro «Clase» permite elegir por materia, grupo, período, carrera, semestre y
docente, sin conocer identificadores numéricos. La lista solo incluye clases con
proyectos autorizados para la persona que consulta el reporte. Los demás filtros
aplicados acotan las opciones; «Todas las clases» elimina la selección individual.
El nombre completo aparece también en el resumen y en el PDF. Las clases inactivas
siguen disponibles para supervisores con permiso global, identificadas como tales.
Si una clase deja de estar disponible o no coincide con los filtros, se conserva
la selección con un aviso genérico y no se expone su nombre ni información ajena.

Prueba de selección, filtros por contexto, descarga y aislamiento por rol:

```bash
python3 tests/report_class_browser.py
```

## Portada y ayuda

La portada describe tareas, entregas, calificaciones y reportes por clases
con participantes autorizados. Distingue este alcance de las inscripciones y
la gestión oficial de docentes de la universidad.

`help.php` y `privacy.php` son públicas y están enlazadas en el pie de todas las
pantallas. La ayuda incluye acceso, entregas, revisión docente, teclado y soporte.
Privacidad explica los datos utilizados, quién puede verlos, comentarios anónimos,
copias descargadas, sesión, tema y consultas a la institución responsable.
Los plazos y procedimientos institucionales no se inventan ni se fijan desde la
aplicación: corresponde a cada universidad proporcionar esa información.

## Contacto oficial

Configurar una dirección real en el archivo local `app/config/config.php`:

```php
'support' => [
    'email' => 'soporte@tu-universidad.edu',
],
```

También puede utilizarse `SISTEMA_SUPPORT_EMAIL` en el entorno del servidor.
Se valida la dirección antes de mostrarla como enlace de correo. Si no existe
un correo válido, se indica solicitar el canal oficial a la administración
académica o al docente responsable. No se envía correo desde estas páginas ni
se recopilan solicitudes en un formulario.

## Teclado

- El enlace «Saltar al contenido principal» mueve el foco al contenido.
- El menú móvil informa su estado y su etiqueta de abrir/cerrar; Escape lo cierra.
- La campana controla su panel; Escape lo cierra y devuelve el foco a la campana.
  Si está dentro del menú móvil, el primer Escape cierra solo las notificaciones.
- Los días disponibles y los eventos del calendario son botones; las tareas son
  enlaces con nombres completos, aunque el texto visual esté abreviado.
- La ventana de eventos es un `dialog` nativo: el fondo queda inactivo, Tab y
  Mayús + Tab recorren solo controles visibles de la ventana, y cerrar con Escape,
  Cancelar o la X devuelve el foco al origen. Los estudiantes conservan la vista
  de solo lectura; los permisos de edición del servidor siguen vigentes.
- Se respeta `hidden` en todos los componentes, para que los controles ocultos
  no aparezcan ni reciban foco por reglas visuales de los botones.

## Verificación

Con dependencias Composer instaladas, servidor iniciado y el poblado demo:

```bash
python3 tests/usability_browser.py
python3 tests/reports_demo.py
python3 tests/theme_browser.py
```

La nueva prueba es de solo lectura: revisa páginas públicas, foco, controles de
calendario, reportes de 320 a 1280 px en ambos temas, nombres largos, impresión y
descarga PDF. Usa Chromium y Python/Playwright, con `UI_TEST_URL` y `CHROMIUM_BIN`
como opciones. No requiere una migración adicional de base de datos.
