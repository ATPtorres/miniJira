<?php
/**
 * Sistema Universitario
 * app/config/config.example.php
 *
 * PLANTILLA VERSIONADA. Copia este archivo como config.php y ajustalo:
 *
 *   cp app/config/config.example.php app/config/config.php
 *
 * config.php NO se sube al repositorio (esta en .gitignore) porque
 * contiene las claves criptograficas locales.
 *
 * Genera las 3 claves con:
 *   C:\xampp\php\php.exe -r "echo bin2hex(random_bytes(32)), PHP_EOL;"
 *
 * IMPORTANTE: si mueves la app a otra carpeta/PC, copia TU config.php
 * original (no regeneres las claves): los correos cifrados en la BD solo
 * se descifran con las claves con las que se guardaron, y el nombre de
 * la carpeta debe ser siempre "sistema-universitario" (lo exige .htaccess).
 */

return [
    'app' => [
        'name'      => 'Sistema Universitario',
        'env'       => 'local',                        // local | production
        'url'       => 'http://localhost/sistema-universitario', // ajusta si la carpeta cambia
        'timezone'  => 'UTC',
        // Clave para HMAC de tokens (64 hex). En produccion usa una variable de entorno.
        'secret_key' => getenv('SISTEMA_APP_SECRET') ?: 'REEMPLAZA_POR_CLAVE_HMAC_64_HEX',
    ],

    'db' => [
        'host'     => '127.0.0.1',
        'port'     => 3306,
        'database' => 'sistema_universitario',
        'username' => 'root',
        'password' => '',
        'charset'  => 'utf8mb4',
    ],

    'auth' => [
        'session_name'            => 'sistema_uni_session',
        'max_login_attempts'      => 5,     // intentos fallidos antes del bloqueo
        'lockout_minutes'         => 15,    // duracion del bloqueo
        'password_reset_ttl'      => 60,    // minutos de validez del token
        'token_entropy_bytes'     => 32,    // entropia del token (random_bytes)
    ],

    'security' => [
        // Claves independientes para cifrado AES-256-GCM y para el indice de
        // busqueda de correos (64 hex cada una). En produccion usalas via variables de entorno.
        'encryption_key' => getenv('SISTEMA_ENCRYPTION_KEY') ?: 'REEMPLAZA_POR_CLAVE_AES_64_HEX',
        'email_lookup_key' => getenv('SISTEMA_EMAIL_LOOKUP_KEY') ?: 'REEMPLAZA_POR_CLAVE_INDICE_64_HEX',
    ],

    'notifications' => [
        // auto: SSE en Apache/FPM, consultas breves en el servidor PHP de desarrollo.
        'transport' => 'auto', // auto | sse | poll
    ],

    // Contacto institucional visible en Ayuda y Privacidad. Vacío: orientación presencial.
    // También puede configurarse sin editar config.php mediante SISTEMA_SUPPORT_EMAIL.
    'support' => [
        'email' => getenv('SISTEMA_SUPPORT_EMAIL') ?: '',
    ],

    'mail' => [
        // driver = log para desarrollo local; smtp envia correos reales.
        'driver'       => 'log',
        'smtp_host'    => '',
        'smtp_port'    => 587,
        'smtp_user'    => '',
        'smtp_password'=> '',
        'smtp_encryption' => 'tls', // tls | ssl | none
        'from_address' => 'no-reply@universidad.local',
        'from_name'    => 'Sistema Universitario',
    ],
];
