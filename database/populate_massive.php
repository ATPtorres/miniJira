<?php
/**
 * Poblado masivo para pruebas de rendimiento y concurrencia.
 * 100 Docentes, 200 Estudiantes, múltiples carreras, semestres, materias, clases y proyectos.
 * Todas las cuentas usan la contraseña: Clave.2026!
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../app/bootstrap.php';

$password = 'Clave.2026!';
$domain = 'demo.minijira.test';

// Administrador responsable
$adminUser = User::findByEmail('admin@demo.minijira.test') ?? User::findByEmail('admin@sistema.local');
if (!$adminUser) {
    fwrite(STDERR, "Error: No se encontró un usuario administrador.\n");
    exit(1);
}
$adminId = (int) $adminUser['id'];

// Pool de nombres y apellidos
$nombresM = [
    'Carlos', 'Javier', 'Roberto', 'Fernando', 'Diego', 'Alejandro', 'Andrés', 'Ricardo',
    'Gabriel', 'Gonzalo', 'Rodrigo', 'Mario', 'Sergio', 'Héctor', 'Raúl', 'Eduardo',
    'Pablo', 'Álvaro', 'Marcos', 'Sebastián', 'Emilio', 'Víctor', 'Hugo', 'Leonardo',
    'Ignacio', 'Iván', 'Óscar', 'Rubén', 'Antonio', 'Rafael', 'Mauricio', 'Fabián',
    'Julio', 'César', 'Gustavo', 'Guillermo', 'Marcelo', 'Lucas', 'Felipe', 'Santiago',
    'Damián', 'Esteban', 'Joaquín', 'Martín', 'Nicolás', 'Pedro', 'Manuel', 'Jorge', 'Luis', 'David'
];

$nombresF = [
    'María', 'Ana', 'Laura', 'Elena', 'Lucía', 'Paula', 'Marta', 'Andrea', 'Claudia',
    'Patricia', 'Natalia', 'Daniela', 'Isabel', 'Sara', 'Silvia', 'Cristina', 'Teresa',
    'Beatriz', 'Raquel', 'Irene', 'Rosa', 'Mónica', 'Alicia', 'Clara', 'Julia', 'Lorena',
    'Pilar', 'Victoria', 'Rocío', 'Alejandra', 'Sandra', 'Gabriela', 'Adriana', 'Carolina',
    'Verónica', 'Diana', 'Miriam', 'Vanessa', 'Marina', 'Estefanía', 'Valeria', 'Camila',
    'Bianca', 'Noelia', 'Jimena', 'Pamela', 'Cecilia', 'Florencia', 'Ximena', 'Mariana'
];

$apellidos = [
    'Gómez', 'Rodríguez', 'Fernández', 'López', 'Martínez', 'González', 'Pérez', 'Sánchez',
    'Romero', 'Torres', 'Álvarez', 'Ruiz', 'Ramírez', 'Flores', 'Benítez', 'Acosta',
    'Medina', 'Herrera', 'Suárez', 'Castro', 'Giménez', 'Gutiérrez', 'Pereyra', 'Rojas',
    'Molina', 'Ortiz', 'Silva', 'Núñez', 'Luna', 'Juárez', 'Cabrera', 'Ríos', 'Morales',
    'Godoy', 'Moreno', 'Balderrama', 'Mamani', 'Quispe', 'Condori', 'Choque', 'Mendoza',
    'Vargas', 'Mercado', 'Pinto', 'Guzmán', 'Terán', 'Vaca', 'Soliz', 'Justiniano', 'Camacho',
    'Aguilar', 'Paredes', 'Salazar', 'Chávez', 'Reyes', 'Valdez', 'Delgado', 'Cruz', 'Peña', 'Ramos'
];

echo "========================================================\n";
echo "INICIANDO POBLADO MASIVO (100 Docentes, 200 Estudiantes)\n";
echo "========================================================\n";

$lockName = 'minijira-massive-' . substr(hash('sha256', (string) db()->query('SELECT DATABASE()')->fetchColumn()), 0, 24);
$q = db()->prepare('SELECT GET_LOCK(?, 0)');
$q->execute([$lockName]);
if ((int) $q->fetchColumn() !== 1) {
    fwrite(STDERR, "Ya hay un poblado en ejecución.\n");
    exit(1);
}

try {
    // 1. Generar hash único para todas las cuentas
    $passwordHash = password_hash($password, password_algo());

    // 2. Crear / Asegurar 100 Docentes
    echo "1. Registrando 100 docentes...\n";
    $docenteIds = [];
    $docentesNuevos = 0;

    for ($i = 1; $i <= 100; $i++) {
        $numStr = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $email = "docente{$numStr}@{$domain}";
        
        $nombre = ($i % 2 === 0) ? $nombresF[($i - 1) % count($nombresF)] : $nombresM[($i - 1) % count($nombresM)];
        $apellido = $apellidos[($i * 3) % count($apellidos)];
        
        $existing = User::findByEmail($email);
        if ($existing) {
            $docenteIds[$i] = (int) $existing['id'];
        } else {
            $id = User::create([
                'first_name' => $nombre,
                'last_name' => $apellido,
                'email' => $email,
                'role_code' => 'docente',
                'password_hash' => $passwordHash,
            ]);
            $docenteIds[$i] = $id;
            $docentesNuevos++;
        }
    }
    echo "   -> 100 Docentes listos ({$docentesNuevos} nuevos creados).\n";

    // 3. Crear / Asegurar 200 Estudiantes
    echo "2. Registrando 200 estudiantes...\n";
    $estudianteIds = [];
    $estudiantesNuevos = 0;

    for ($i = 1; $i <= 200; $i++) {
        $numStr = str_pad((string) $i, 3, '0', STR_PAD_LEFT);
        $email = "estudiante{$numStr}@{$domain}";

        $nombre = ($i % 2 === 0) ? $nombresM[($i - 1) % count($nombresM)] : $nombresF[($i - 1) % count($nombresF)];
        $apellido = $apellidos[($i * 7) % count($apellidos)];

        $existing = User::findByEmail($email);
        if ($existing) {
            $estudianteIds[$i] = (int) $existing['id'];
        } else {
            $id = User::create([
                'first_name' => $nombre,
                'last_name' => $apellido,
                'email' => $email,
                'role_code' => 'estudiante',
                'password_hash' => $passwordHash,
            ]);
            $estudianteIds[$i] = $id;
            $estudiantesNuevos++;
        }
    }
    echo "   -> 200 Estudiantes listos ({$estudiantesNuevos} nuevos creados).\n";

    // 4. Catálogo de Carreras y Materias
    echo "3. Generando mapa académico de carreras, materias y semestres...\n";
    $catalogo = [
        'Ingeniería de Sistemas' => [
            1 => ['Programación I', 'Álgebra Lineal', 'Introducción a Sistemas'],
            2 => ['Programación II', 'Estructuras Discretas', 'Cálculo I'],
            3 => ['Estructuras de Datos', 'Cálculo II', 'Bases de Datos I'],
            4 => ['Bases de Datos II', 'Sistemas Operativos', 'Estadística y Probabilidades'],
            5 => ['Ingeniería de Software I', 'Redes de Computadoras I', 'Arquitectura de Computadoras'],
            6 => ['Ingeniería de Software II', 'Redes de Computadoras II', 'Inteligencia Artificial'],
            7 => ['Seguridad de la Información', 'Desarrollo Web y Móvil', 'Gestión de Proyectos TI'],
            8 => ['Sistemas Distribuidos', 'Calidad de Software', 'Taller de Grado']
        ],
        'Ingeniería Industrial' => [
            1 => ['Introducción a Ingeniería Industrial', 'Química General', 'Álgebra Básica'],
            2 => ['Cálculo I', 'Dibujo Técnico Industrial', 'Física I'],
            3 => ['Física II', 'Termodinámica', 'Estudio del Trabajo'],
            4 => ['Estadística Aplicada', 'Mecánica de Materiales', 'Procesos de Manufactura'],
            5 => ['Investigación Operativa I', 'Costos Industriales', 'Ingeniería Económica'],
            6 => ['Investigación Operativa II', 'Gestión de Calidad', 'Seguridad y Salud Ocupacional'],
            7 => ['Logística y Cadena de Suministro', 'Planificación y Control de la Producción'],
            8 => ['Dirección de Operaciones', 'Evaluación de Proyectos Industriales']
        ],
        'Administración de Empresas' => [
            1 => ['Fundamentos de Administración', 'Matemática Financiera', 'Metodología de la Investigación'],
            2 => ['Microeconomía', 'Contabilidad Básica', 'Derecho Empresarial'],
            3 => ['Macroeconomía', 'Contabilidad de Costos', 'Estadística Empresarial'],
            4 => ['Comportamiento Organizacional', 'Marketing Estratégico', 'Finanzas Corporativas I'],
            5 => ['Gestión del Talento Humano', 'Finanzas Corporativas II', 'Investigación de Mercados'],
            6 => ['Planificación Estratégica', 'Comercio Internacional', 'Gestión de Operaciones'],
            7 => ['Liderazgo y Negociación', 'Formulación de Proyectos Empresariales'],
            8 => ['Plan de Negocios y Emprendimiento', 'Simulación de Negocios']
        ],
        'Contaduría Pública' => [
            1 => ['Contabilidad Básica', 'Documentación Mercantil', 'Matemáticas Financieras'],
            2 => ['Contabilidad Intermedia', 'Legislación Tributaria I', 'Cálculo Financiero'],
            3 => ['Contabilidad de Costos I', 'Legislación Tributaria II', 'Estadística'],
            4 => ['Contabilidad de Costos II', 'Contabilidad de Sociedades', 'Auditoría Básica'],
            5 => ['Contabilidad Gubernamental', 'Auditoría Financiera I', 'Análisis Financiero'],
            6 => ['Auditoría Financiera II', 'Contabilidad Bancaria', 'Presupuestos'],
            7 => ['Auditoría Operativa', 'Normas Internacionales NIIF', 'Gabinete Contable'],
            8 => ['Auditoría Forense', 'Seminario de Grado Contable']
        ],
        'Derecho' => [
            1 => ['Introducción al Derecho', 'Historia del Derecho', 'Derecho Romano'],
            2 => ['Derecho Constitucional I', 'Derecho Civil I (Personas)', 'Teoría del Estado'],
            3 => ['Derecho Constitucional II', 'Derecho Civil II (Bienes)', 'Derecho Penal I'],
            4 => ['Derecho Civil III (Obligaciones)', 'Derecho Penal II', 'Derecho Internacional'],
            5 => ['Derecho Civil IV (Contratos)', 'Derecho Procesal Orgánico', 'Derecho Laboral'],
            6 => ['Derecho Mercantil I', 'Derecho Procesal Civil', 'Derecho de Familia'],
            7 => ['Derecho Mercantil II', 'Derecho Procesal Penal', 'Derecho Tributario'],
            8 => ['Práctica Forense', 'Filosofía y Deontología Jurídica']
        ],
        'Ingeniería Civil' => [
            1 => ['Geometría Descriptiva', 'Física I', 'Cálculo Diferencial'],
            2 => ['Topografía I', 'Estática', 'Cálculo Integral'],
            3 => ['Topografía II', 'Resistencia de Materiales I', 'Geología Aplicada'],
            4 => ['Hidráulica I', 'Resistencia de Materiales II', 'Mecánica de Suelos I'],
            5 => ['Estructuras Isostáticas', 'Hidráulica II', 'Mecánica de Suelos II'],
            6 => ['Estructuras Hiperestáticas', 'Hormigón Armado I', 'Carreteras I'],
            7 => ['Hormigón Armado II', 'Carreteras II', 'Obras Hidráulicas'],
            8 => ['Puentes y Estructuras', 'Dirección de Obras Civiles']
        ],
        'Medicina' => [
            1 => ['Anatomía Humana I', 'Histología', 'Embriología'],
            2 => ['Anatomía Humana II', 'Bioquímica Médica', 'Genética'],
            3 => ['Fisiología Humana I', 'Microbiología y Parasitología'],
            4 => ['Fisiología Humana II', 'Farmacología Básica', 'Inmunología'],
            5 => ['Semiología Médica', 'Patología General', 'Epidemiología'],
            6 => ['Medicina Interna I', 'Cirugía General I', 'Farmacología Clínica'],
            7 => ['Medicina Interna II', 'Pediatría', 'Ginecología y Obstetricia'],
            8 => ['Salud Pública', 'Bioética Médica y Medicina Legal']
        ],
        'Psicología' => [
            1 => ['Introducción a la Psicología', 'Bases Biológicas de la Conducta', 'Historia de la Psicología'],
            2 => ['Psicología del Desarrollo I', 'Procesos Cognitivos', 'Epistemología'],
            3 => ['Psicología del Desarrollo II', 'Psicología Social', 'Estadística para Psicología'],
            4 => ['Teorías de la Personalidad', 'Psicometría', 'Psicopatología I'],
            5 => ['Psicopatología II', 'Evaluación Psicológica', 'Psicología Educativa'],
            6 => ['Técnicas de Intervención', 'Psicología Organizacional', 'Psicología Clínica'],
            7 => ['Psicoterapia', 'Orientación Vocacional', 'Deontología Psicológica'],
            8 => ['Taller de Integración Profesional', 'Investigación Psicológica']
        ]
    ];

    // 5. Generar Clases (Classrooms) distribuyendo docentes y estudiantes
    echo "4. Creando clases, asignando participantes y proyectos...\n";
    $periodoActual = date('Y') . '-' . ((int)date('n') <= 6 ? '1' : '2');
    $grupos = ['A', 'B', 'C'];
    
    $clasesCreadas = 0;
    $proyectosCreados = 0;
    $tareasCreadas = 0;
    
    $docenteIndex = 1;
    $classCounter = 0;

    foreach ($catalogo as $carrera => $semestres) {
        $carreraAbrev = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $carrera), 0, 3));
        
        foreach ($semestres as $semestreNum => $materias) {
            foreach ($materias as $materia) {
                // Asignar 1 o 2 grupos por materia
                $numGrupos = ($classCounter % 3 === 0) ? 2 : 1;
                
                for ($g = 0; $g < $numGrupos; $g++) {
                    $grupo = $grupos[$g];
                    $materiaSlug = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $materia), 0, 5));
                    $externalCode = "EXT-{$carreraAbrev}-S{$semestreNum}-{$materiaSlug}-{$grupo}";

                    // Asignar docente rotativo (de los 100 disponibles)
                    $currentDocenteNum = (($docenteIndex - 1) % 100) + 1;
                    $docenteId = $docenteIds[$currentDocenteNum];
                    $docenteIndex++;

                    // Asignar estudiantes rotativos (de los 200 disponibles)
                    $inicioEstudiante = (($classCounter * 7) % 200) + 1;
                    $cantEstudiantes = 18;
                    $estudiantesDeClase = [];
                    for ($e = 0; $e < $cantEstudiantes; $e++) {
                        $idx = (($inicioEstudiante + $e - 1) % 200) + 1;
                        $estudiantesDeClase[] = $estudianteIds[$idx];
                    }
                    $estudiantesDeClase = array_values(array_unique($estudiantesDeClase));

                    // Verificar si la clase ya existe
                    $qCheck = db()->prepare('SELECT id FROM classrooms WHERE external_code = ?');
                    $qCheck->execute([$externalCode]);
                    $classId = $qCheck->fetchColumn();

                    if (!$classId) {
                        $classId = Classroom::save(null, [
                            'external_code' => $externalCode,
                            'subject' => $materia,
                            'career' => $carrera,
                            'semester_number' => $semestreNum,
                            'group_name' => $grupo,
                            'period' => $periodoActual,
                            'teacher_id' => $docenteId,
                            'is_active' => 1
                        ], $estudiantesDeClase, $adminId);
                        $clasesCreadas++;
                    } else {
                        $classId = (int) $classId;
                    }

                    // Asegurar lista sincronizada de estudiantes autorizados en la clase
                    $estudiantesAutorizados = Classroom::studentIds($classId);
                    if (empty($estudiantesAutorizados)) {
                        Classroom::save($classId, [
                            'external_code' => $externalCode,
                            'subject' => $materia,
                            'career' => $carrera,
                            'semester_number' => $semestreNum,
                            'group_name' => $grupo,
                            'period' => $periodoActual,
                            'teacher_id' => $docenteId,
                            'is_active' => 1
                        ], $estudiantesDeClase, $adminId);
                        $estudiantesAutorizados = Classroom::studentIds($classId);
                    }

                    // Crear Proyecto para la clase si no existe
                    $projectTitle = "Proyecto · {$materia} · Grupo {$grupo}";
                    $qProj = db()->prepare('SELECT id FROM projects WHERE classroom_id = ? AND title = ?');
                    $qProj->execute([$classId, $projectTitle]);
                    $projectId = $qProj->fetchColumn();

                    if (!$projectId) {
                        $projectId = Project::create([
                            'classroom_id' => $classId,
                            'title' => $projectTitle,
                            'description' => "Proyecto de aplicación práctica para la materia de {$materia} en la carrera de {$carrera}.",
                            'objectives' => "Desarrollar competencias integrales, trabajo colaborativo y entregas periódicas según el cronograma.",
                            'start_date' => date('Y-m-d', strtotime('-15 days')),
                            'end_date' => date('Y-m-d', strtotime('+45 days')),
                            'responsible_teacher_id' => $docenteId,
                            'created_by_id' => $adminId,
                        ], $estudiantesAutorizados);
                        $proyectosCreados++;

                        // Crear tareas representativas en el proyecto
                        $taskDefs = [
                            ['Investigación preliminar y marco teórico', 'done', -5, 10, 'high', 9, 'Excelente investigación inicial.'],
                            ['Diseño de la solución y propuesta metodológica', 'review', 5, 10, 'medium', null, null],
                            ['Desarrollo e implementación del trabajo', 'doing', 15, 15, 'high', null, null],
                            ['Elaboración del informe final y presentación', 'todo', 30, 20, 'medium', null, null],
                        ];

                        foreach ($taskDefs as $tIdx => [$tTitle, $tStatus, $tDays, $tPts, $tPrio, $grade, $feedback]) {
                            $assignedStudent = $estudiantesAutorizados[$tIdx % count($estudiantesAutorizados)];
                            $dueDate = date('Y-m-d', strtotime(($tDays >= 0 ? '+' : '') . $tDays . ' days'));
                            
                            Task::create($projectId, [
                                'title' => $tTitle,
                                'description' => "Actividad académica asignada en {$materia}. Cumplir con los criterios de evaluación establecidos.",
                                'assigned_student_id' => $assignedStudent,
                                'points' => $tPts,
                                'priority' => $tPrio,
                                'status' => $tStatus,
                                'due_date' => $dueDate
                            ]);
                            $tareasCreadas++;

                            if ($tStatus === 'done' && $grade !== null) {
                                $qTaskId = db()->prepare('SELECT MAX(id) FROM project_tasks WHERE project_id = ?');
                                $qTaskId->execute([$projectId]);
                                $taskId = (int) $qTaskId->fetchColumn();
                                if ($taskId > 0) {
                                    Task::gradeAndComplete($taskId, $grade, $feedback);
                                }
                            }
                        }
                    }

                    $classCounter++;
                }
            }
        }
    }

    echo "========================================================\n";
    echo "POBLADO MASIVO COMPLETADO CON ÉXITO\n";
    echo "========================================================\n";
    echo "Docentes registrados:    100 (docente001@{$domain} a docente100@{$domain})\n";
    echo "Estudiantes registrados: 200 (estudiante001@{$domain} a estudiante200@{$domain})\n";
    echo "Total de clases activas: {$classCounter}\n";
    echo "Nuevas clases creadas:   {$clasesCreadas}\n";
    echo "Nuevos proyectos:        {$proyectosCreados}\n";
    echo "Nuevas tareas:           {$tareasCreadas}\n";
    echo "Contraseña para todos:   {$password}\n";
    echo "========================================================\n";

} catch (Throwable $e) {
    fwrite(STDERR, "Error durante el poblado masivo: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
} finally {
    $q = db()->prepare('SELECT RELEASE_LOCK(?)');
    $q->execute([$lockName]);
}
