<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
$pageTitle='Ayuda y soporte';
require APP_PATH.'/app/views/partials/header.php';
?>
<section class="panel-head"><div><h1>Ayuda y soporte</h1><p>Resuelve las dudas más frecuentes sobre tus tareas, entregas y evaluaciones.</p></div></section>
<nav class="help-topics" aria-label="Temas de ayuda">
  <a href="#acceso">Acceso</a><a href="#estudiantes">Entregar tareas</a><a href="#docentes">Revisar y calificar</a><a href="#teclado">Usar el teclado</a><a href="#contacto">Contactar soporte</a>
</nav>
<section class="card help-section" id="acceso" tabindex="-1">
  <h2>Acceso y clases</h2>
  <p>MiniJira sirve para organizar tareas, recibir entregas y evaluar el trabajo de las clases. Las inscripciones y la gestión oficial de docentes se realizan en la plataforma institucional de tu universidad.</p>
  <p>Usa la cuenta habilitada por quien administra MiniJira. Si no tienes acceso o no aparece una clase, solicita que revisen tu cuenta y tu participación en esa clase y proyecto.</p>
  <p><a href="<?= e(base_url('login.php')) ?>">Iniciar sesión</a> · <a href="<?= e(base_url('forgot-password.php')) ?>">Recuperar mi contraseña</a></p>
</section>
<section class="card help-section" id="estudiantes" tabindex="-1">
  <h2>Para estudiantes: entregar y consultar resultados</h2>
  <ol>
    <li>Entra a <strong>Mi panel</strong> y abre la tarea asignada. Comprueba sus instrucciones y fecha límite.</li>
    <li>En el detalle de la tarea, utiliza la sección de entregables para seleccionar y subir tus archivos. Revisa el tamaño permitido que aparece junto al formulario.</li>
    <li>Confirma que el archivo figura en el historial de entregas con su fecha y hora. Seleccionar un archivo sin enviarlo no registra la entrega.</li>
    <li>Consulta el estado y los comentarios del docente. Cuando evalúe la tarea, podrás ver tu calificación y retroalimentación.</li>
  </ol>
  <p>Si la tarea está bloqueada o vencida y no permite entregar, consulta al docente. Para sustituir una entrega, usa las opciones de versión disponibles en la tarea.</p>
</section>
<section class="card help-section" id="docentes" tabindex="-1">
  <h2>Para docentes: asignar, revisar y calificar</h2>
  <ol>
    <li>Abre tu clase o proyecto y comprueba que participan los estudiantes correspondientes.</li>
    <li>Crea las tareas con instrucciones, puntaje y fecha límite, y asígnalas a los estudiantes.</li>
    <li>Consulta las tareas por evaluar, abre sus archivos y revisa el historial de versiones.</li>
    <li>Registra la calificación y la retroalimentación. Usa <strong>Reportes</strong> para consultar el avance y desempeño, aplicar filtros y descargar el PDF.</li>
  </ol>
  <p>El avance indica tareas terminadas respecto del total; el desempeño considera los puntos de las tareas evaluadas. Una tarea sin evaluación no se interpreta como una nota de cero.</p>
</section>
<section class="card help-section" id="teclado" tabindex="-1">
  <h2>Navegación con teclado</h2>
  <p>Usa Tab y Mayús + Tab para recorrer los controles, Enter para abrir enlaces y Enter o Espacio para activar botones. El primer enlace permite saltar al contenido principal.</p>
  <p>En el calendario, los días disponibles tienen un botón para crear eventos. Los eventos se pueden abrir con el teclado. Dentro de una ventana del calendario, Tab recorre sus controles y Escape la cierra y devuelve el foco al control de origen.</p>
  <p>También puedes cambiar el estado de una tarea con su formulario, sin arrastrar tarjetas. El botón de tema oscuro está disponible en el menú; en móvil, abre primero el menú principal.</p>
</section>
<section class="card help-section" id="contacto" tabindex="-1">
  <h2>Contacto y soporte</h2>
  <p>Para dudas sobre instrucciones, fechas o calificaciones, consulta al docente responsable. Para problemas con la cuenta, los permisos o el funcionamiento, solicita ayuda al equipo que administra MiniJira.</p>
  <?php require APP_PATH.'/app/views/partials/support-contact.php'; ?>
  <p>Si falla una entrega, conserva el mensaje mostrado y consulta el historial antes de volver a enviarla. Para conocer cómo se utiliza tu información, consulta <a href="<?= e(base_url('privacy.php')) ?>">Privacidad</a>.</p>
</section>
<?php require APP_PATH.'/app/views/partials/footer.php'; ?>
