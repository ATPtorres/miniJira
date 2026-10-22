# Datos demo para pruebas rápidas

Desde `pruebacritica`, sobre la base configurada en `app/config/config.php`:

```bash
php database/migrate_beta.php
php database/populate_demo.php
```

Para XAMPP: `C:\xampp\php\php.exe database\populate_demo.php`.
No se necesita importar SQL ni usar la base desechable de las pruebas automáticas.
El script conserva los datos existentes. Crea recursos con prefijo `DEMO` y cuentas
en `@demo.minijira.test`. Repetirlo no duplica ni reinicia los proyectos y sus cambios.
Si una cuenta demo ya existe con otro rol o contraseña, se detiene sin sobrescribirla.
Los correos son ficticios y no se envían mensajes por email.

## Acceso

**Contraseña de todas las cuentas de este poblado: `Clave.2026!`**.
Las cuentas que existían antes del poblado conservan su contraseña anterior.

| Rol | Nombre | Correo |
|---|---|---|
| Administrador | Administrador Demo | admin@demo.minijira.test |
| Docente | Ana Rojas | ana@demo.minijira.test |
| Docente | Carlos Mendoza | carlos@demo.minijira.test |
| Docente | Lucía Vargas | lucia@demo.minijira.test |
| Docente | Diego Flores | diego@demo.minijira.test |
| Estudiante | Alejandro Torres | alejandro@demo.minijira.test |
| Estudiante | José Chambi | jose@demo.minijira.test |
| Estudiante | María Quispe | maria@demo.minijira.test |
| Estudiante | Sofía López | sofia@demo.minijira.test |
| Estudiante | Luis Fernández | luis@demo.minijira.test |
| Estudiante | Camila Pérez | camila@demo.minijira.test |
| Estudiante | Pedro Gutiérrez | pedro@demo.minijira.test |
| Estudiante | Valeria Castro | valeria@demo.minijira.test |

## Clases

El período activo se obtiene del año y semestre calendario al crear el poblado.
El semestre curricular indicado en la tabla es independiente del período calendario.

| Carrera | Materia | Semestre | Grupo | Docente | Estudiantes |
|---|---|---|---|---|---|
| Ingeniería de Sistemas | Programación I | 1 | A | Ana | Alejandro, José, María |
| Ingeniería de Sistemas | Programación I | 1 | B | Ana | Sofía, Luis |
| Ingeniería de Sistemas | Bases de datos | 3 | A | Carlos | Alejandro, Camila, Pedro |
| Administración | Contabilidad I | 2 | C | Lucía | José, Valeria, Sofía |
| Ingeniería Industrial | Estadística | 4 | D | Diego | Camila, Luis, Valeria |
| Administración | Gestión de proyectos | 4 | A | Lucía | Alejandro, María, Valeria |
| Ingeniería de Sistemas | Introducción a sistemas | 1 | Histórico | Carlos | Alejandro, Pedro |

La última clase está **inactiva**. Solo la supervisión administrativa puede
consultar su proyecto. Las otras seis están activas.

## Qué probar

- Ana tiene dos grupos de Programación I, pero no ve las clases de otros docentes.
- Alejandro pertenece a tres clases, dos carreras y tres semestres curriculares.
- José comparte Programación I con Alejandro y tiene Contabilidad I con Lucía.
- Sofía tiene Programación I en el grupo B; no ve el proyecto del grupo A aunque
  la docente de ambos sea Ana.
- Cada proyecto tiene cuatro tareas: pendiente, en proceso, por evaluar y evaluada.
  Bases de datos y Estadística incluyen una tarea pendiente ya vencida.
- La tarea por evaluar tiene dos versiones de un archivo descargable de ejemplo.
  La evaluada tiene un archivo y una nota de 8/10 con retroalimentación.
- Hay comentarios en hilos, eventos de calendario y avisos dentro de MiniJira.
- Como administrador, retira a un estudiante de una clase y comprueba que pierda
  acceso a sus proyectos, archivos e informes. Para reponer el acceso, restáuralo
  en la clase y vuelve a incluirlo en el equipo del proyecto.

Total inicial: **13 cuentas, 7 clases, 7 proyectos, 28 tareas y 21 archivos/versiones**.
Los archivos son TXT sintéticos; no contienen información de personas reales.
