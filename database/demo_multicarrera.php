<?php
/** Definiciones adicionales; cargar desde populate_demo.php --extended. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli' || !isset($definitions,$classes,$period)) { http_response_code(404); exit; }
$definitions += [
    'elena'=>['docente','Elena','Salazar'],
    'ricardo'=>['docente','Ricardo','Molina'],
    'natalia'=>['estudiante','Natalia','Ríos'],
    'andres'=>['estudiante','Andrés','Vega'],
    'paula'=>['estudiante','Paula','Suárez'],
    'mateo'=>['estudiante','Mateo','Cabrera'],
];
$classes=array_merge($classes,[
    ['MULTI-SIS-PROG-A','Programación II','Ingeniería de Sistemas',3,'A',$period,'elena',['natalia','andres','paula'],true],
    ['MULTI-SIS-PROG-B','Programación II','Ingeniería de Sistemas',3,'B',$period,'elena',['natalia','mateo'],true],
    ['MULTI-SIS-BD-C','Bases de datos II','Ingeniería de Sistemas',4,'C',$period,'elena',['natalia','andres'],true],
    ['MULTI-ADM-EST-A','Estadística aplicada','Administración',3,'A',$period,'elena',['natalia','paula','mateo'],true],
    ['MULTI-IND-EST-B','Estadística aplicada','Ingeniería Industrial',4,'B',$period,'elena',['andres','paula'],true],
    ['MULTI-ADM-GEST-A','Gestión empresarial','Administración',4,'A',$period,'ricardo',['natalia','mateo'],true],
    ['MULTI-ADM-GEST-B','Gestión empresarial','Administración',4,'B',$period,'ricardo',['natalia','paula'],true],
    ['MULTI-SIS-GEST-C','Gestión de tecnología','Ingeniería de Sistemas',5,'C',$period,'ricardo',['natalia','andres','mateo'],true],
    ['MULTI-IND-COST-A','Costos industriales','Ingeniería Industrial',5,'A',$period,'ricardo',['andres','paula'],true],
    ['MULTI-IND-COST-B','Costos industriales','Ingeniería Industrial',5,'B',$period,'ricardo',['andres','paula'],true],
]);
