<?php
declare(strict_types=1);
require_once __DIR__.'/../app/bootstrap.php';
$pageTitle='Privacidad';
require APP_PATH.'/app/views/partials/header.php';
?>
<section class="panel-head"><div><h1>Privacidad y uso de la información</h1><p>Cómo utiliza MiniJira la información de tus clases y tu trabajo académico.</p></div></section>
<section class="card help-section">
  <h2>Para qué se utiliza</h2>
  <p>MiniJira utiliza información para habilitar el acceso a clases y proyectos, asignar tareas, registrar entregas, comunicar comentarios y avisos, evaluar el trabajo y generar reportes académicos.</p>
  <h2>Información que se registra</h2>
  <p>Incluye tu nombre, correo y rol; la participación en clases y proyectos; tareas, archivos y sus versiones; comentarios; calificaciones y retroalimentación; fechas y horas de las acciones y datos de acceso necesarios para operar y proteger la plataforma.</p>
  <h2>Quién puede acceder</h2>
  <p>El acceso depende del rol y de la participación autorizada en cada clase y proyecto. El docente responsable puede revisar y evaluar el trabajo de su proyecto. Los estudiantes acceden al trabajo compartido de sus equipos y a su propia información de evaluación. Las cuentas con permisos de supervisión pueden acceder a información adicional para administrar la plataforma.</p>
  <p>La visibilidad de los comentarios y sus adjuntos depende de la conversación y sus permisos. Los comentarios privados no se muestran a todo el equipo. La opción de publicar como anónimo oculta el nombre en la conversación; no elimina el registro de autoría del sistema.</p>
  <h2>Archivos y reportes descargados</h2>
  <p>Sube únicamente archivos necesarios para la actividad y evita incluir información personal de terceros que no corresponda. Cuando descargues archivos o reportes, conserva las copias de forma segura y compártelas solo con personas autorizadas. Las copias descargadas quedan fuera del control de acceso de MiniJira.</p>
  <h2>Sesión y preferencias del navegador</h2>
  <p>La plataforma utiliza una cookie de sesión para mantener tu acceso y almacenamiento local del navegador para recordar el tema claro u oscuro. Esa preferencia puede permanecer después de cerrar sesión. Cierra tu sesión al terminar, especialmente en equipos compartidos.</p>
  <h2>Conservación y solicitudes sobre tus datos</h2>
  <p>La institución que administra esta instalación determina los plazos de conservación de la información académica, los archivos y las copias de respaldo. Solicita a su administración información sobre esos plazos, corrección de datos o revisión de accesos. La posibilidad de eliminar información depende de las obligaciones académicas y de las normas aplicables a la institución.</p>
  <p>Esta página describe el funcionamiento de MiniJira. Consulta a tu universidad para identificar al responsable del tratamiento, su aviso institucional de privacidad y los procedimientos que correspondan a tu caso.</p>
  <h2>Consultas de privacidad</h2>
  <?php require APP_PATH.'/app/views/partials/support-contact.php'; ?>
  <p><a href="<?= e(base_url('help.php#contacto')) ?>">Consultar ayuda y soporte</a></p>
</section>
<?php require APP_PATH.'/app/views/partials/footer.php'; ?>
