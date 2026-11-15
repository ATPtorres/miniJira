# MiniJira — tareas y entregas

Plataforma de tareas universitarias estilo JIRA, construida en **PHP 8.x + MySQL/MariaDB (XAMPP)** sin frameworks.

La rama `pruebacritica` incorpora clases y participantes autorizados, aislamiento académico y un panel de carga de trabajo. La universidad conserva su plataforma de inscripciones y gestión oficial de docentes. Ver [alcance, migración y uso de pruebacritica](docs/PRUEBACRITICA.md).

La rama Beta agrega notificaciones con actualizacion automatica, Cron de recordatorios,
analitica real e informes PDF. Ver [instalacion, ejecucion y pruebas de Beta](docs/BETA.md).

La rama `BetaJiraFinal` incorpora reportes adaptados a móvil, una portada centrada en tareas y entregas, y páginas públicas de ayuda y privacidad. Ver [uso, contacto de soporte y navegación por teclado](docs/USABILIDAD.md).

Para Beta, ejecutar `composer install` y `php database/migrate_beta.php` antes de iniciar.

---

## 1. Requisitos

- [XAMPP](https://www.apachefriends.org/) con PHP 8.x, Apache, MySQL/MariaDB y phpMyAdmin.
- Extensiones PHP habilitadas: `pdo_mysql`, `openssl`, `session`, `mbstring`, `fileinfo` y `dom`.
- Composer 2 para instalar el generador de reportes PDF de Beta.

## 2. Instalación rápida

### Opción A: Script único (recomendado)

```bash
# Desde la raíz del proyecto:
C:\xampp\mysql\bin\mysql.exe -u root < database\install.sql
```

O desde phpMyAdmin: pestaña SQL → pegar contenido de `database\install.sql` → Ejecutar.

### Opción B: Archivos separados

1. Importar `database\schema.sql` (crea tablas)
2. Importar `database\seed.sql` (datos iniciales)

### Crear usuarios de desarrollo

```bash
C:\xampp\php\php.exe database\create_user.php admin Administrador Admin admin@sistema.local "Clave.2026!"
C:\xampp\php\php.exe database\create_user.php docente Docente Demo docente@sistema.local "Clave.2026!"
C:\xampp\php\php.exe database\create_user.php estudiante Estudiante Demo estudiante@sistema.local "Clave.2026!"
```

### Acceder

```
http://localhost/sistema-universitario/
```

## 3. Credenciales de desarrollo

Ver `docs\CREDENCIALES_DEV.md`. Todos los usuarios usan la contraseña `Clave.2026!`.

| Rol | Email |
|---|---|
| Admin | `admin@sistema.local` |
| Docente | `docente@sistema.local` |
| Estudiante | `estudiante@sistema.local` |

## 4. Funcionalidades implementadas

### Gestión de usuarios (Admin)
- CRUD completo: crear, listar, editar, desactivar
- Cambio inline de rol
- Encriptación de email (AES-256-GCM)
- Contraseña auto-generada segura

### Proyectos universitarios (Docente)
- Cada proyecto pertenece a una clase autorizada; el equipo solo incluye estudiantes de esa clase.
- Crear proyecto con: título, descripción, objetivos, fechas, estudiantes
- Docente responsable obtenido de la clase; editar como administrador no cambia su propietario
- Validación: end_date > start_date, campos requeridos
- Editar información general
- Eliminación lógica (soft delete)

### Tablero Kanban
- 4 estados: TO DO, DOING, IN REVIEW, DONE
- Crear tareas con título, descripción, estudiante, fecha límite
- Tareas siempre inician en TO DO
- Mover tareas entre columnas con confirmación

### Panel de indicadores
- Contadores por estado de tarea
- Porcentaje de avance con barras de progreso
- Timeline temporal vs fecha límite
- Desglose por estudiante
- Detección de vencimiento

### Seguridad
- Login con protección brute-force (5 intentos → bloqueo 15 min)
- CSRF en todos los formularios
- XSS protegido con `e()` en toda salida
- SQL Injection prevenido con PDO prepared statements
- Contraseñas con Argon2id/bcrypt
- Email encriptado con AES-256-GCM
- Tokens de recuperación de uso único y expiración
- Sin registro público (solo admin crea usuarios)

## 5. Estructura del proyecto

```
sistema-universitario/
├── app/
│   ├── bootstrap.php            # Punto de entrada común
│   ├── config/                  # Configuración (BD, app)
│   ├── controllers/             # 5 controladores
│   ├── helpers/                 # Auth, seguridad, validación
│   ├── middleware/               # Auth, CSRF, permisos
│   ├── models/                  # 6 modelos
│   ├── services/                # Encriptación, mail
│   └── views/partials/          # Header y footer
├── database/
│   ├── install.sql              # Instalación completa (recomendado)
│   ├── schema.sql               # Solo tablas
│   ├── seed.sql                 # Datos iniciales
│   ├── create_user.php          # CLI para crear usuarios
│   └── migrations/              # Migraciones históricas
├── docs/
│   ├── CREDENCIALES_DEV.md
│   └── PLAN_DE_PRUEBAS.md
├── public/
│   ├── index.php                # Bienvenida
│   ├── login.php / logout.php
│   ├── admin/                   # Panel administrativo
│   ├── user/                    # Panel de usuario
│   ├── projects/                # Gestión de proyectos
│   ├── api/                     # Endpoint JSON
│   └── assets/                  # CSS + JS
└── storage/logs/                # Logs protegidos
```

## 6. Errores comunes

| Problema | Solución |
|---|---|
| Apache no arranca | Cambia puerto en `httpd.conf` o detén el servicio que lo bloquea |
| "Access denied for 'root'" | Edita `app/config/config.php` con tu contraseña MySQL |
| "Unknown database" | Ejecuta `database/install.sql` |
| Página en blanco | Revisa `storage/logs/app.log` |
| Permisos denegados (403) | Verifica que ejecutaste `install.sql` completo |

## 7. Base de datos

- **8 tablas**: roles, permissions, role_permissions, users, password_reset_tokens, projects, project_students, project_tasks
- **3 roles**: admin (15 permisos), docente (8 permisos), estudiante (6 permisos)
- **15 permisos** granulares del sistema
