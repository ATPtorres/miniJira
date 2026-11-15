<?php
declare(strict_types=1);
$supportEmail = trim((string) (getenv('SISTEMA_SUPPORT_EMAIL') ?: config('support.email', '')));
if (filter_var($supportEmail, FILTER_VALIDATE_EMAIL)):
?>
<p>Contacta al equipo responsable de esta instalación: <a href="mailto:<?= e($supportEmail) ?>"><?= e($supportEmail) ?></a>.</p>
<?php else: ?>
<p>Solicita el canal oficial de soporte a la administración académica de tu universidad o al docente responsable de tu clase.</p>
<?php endif; ?>
<p>Indica la materia, el grupo, la tarea y lo que ocurrió. No compartas tu contraseña ni códigos o enlaces de recuperación. Si adjuntas una captura, oculta datos de otras personas.</p>
