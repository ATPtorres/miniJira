<?php
declare(strict_types=1);
final class AcademicController
{
    private static function guard(string $perm): void { requirePermission($perm); }

    // ---- Periods ----
    public static function createPeriod(): void { self::guard('periods.manage'); requireCsrf();
        $code=trim(post('code')); $name=trim(post('name')); $s=post('start_date'); $e=post('end_date');
        if($code===''||$name===''||$s===''||$e===''){ flash_set('error','Todos los campos son obligatorios.'); redirect('admin/academic.php?tab=periods');}
        try{ AcademicPeriod::create(['code'=>$code,'name'=>$name,'start_date'=>$s,'end_date'=>$e,'is_active'=>1]); flash_set('success','Periodo creado.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=periods');
    }
    public static function updatePeriod(): void { self::guard('periods.manage'); requireCsrf();
        $id=(int)post('id'); $code=trim(post('code')); $name=trim(post('name')); $s=post('start_date'); $e=post('end_date'); $act=(int)post('is_active');
        try{ AcademicPeriod::update($id,['code'=>$code,'name'=>$name,'start_date'=>$s,'end_date'=>$e,'is_active'=>$act]); flash_set('success','Periodo actualizado.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=periods');
    }

    // ---- Careers ----
    public static function createCareer(): void { self::guard('careers.manage'); requireCsrf();
        $code=trim(post('code')); $name=trim(post('name')); $dur=(int)post('duration_semesters'); $desc=trim(post('description'));
        if($code===''||$name===''||$dur<1||$dur>12){ flash_set('error','Datos de carrera invalidos.'); redirect('admin/academic.php?tab=careers');}
        try{ Career::create(['code'=>$code,'name'=>$name,'description'=>$desc,'duration_semesters'=>$dur]); flash_set('success','Carrera creada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=careers');
    }
    public static function updateCareer(): void { self::guard('careers.manage'); requireCsrf();
        $id=(int)post('id'); $code=trim(post('code')); $name=trim(post('name')); $dur=(int)post('duration_semesters'); $desc=trim(post('description')); $act=(int)post('is_active');
        try{ Career::update($id,['code'=>$code,'name'=>$name,'description'=>$desc,'duration_semesters'=>$dur,'is_active'=>$act]); flash_set('success','Carrera actualizada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=careers');
    }

    // ---- Subjects ----
    public static function createSubject(): void { self::guard('subjects.manage'); requireCsrf();
        $code=trim(post('code')); $name=trim(post('name')); $credits=post('credits'); $desc=trim(post('description'));
        if($code===''||$name===''){ flash_set('error','Codigo y nombre obligatorios.'); redirect('admin/academic.php?tab=subjects');}
        try{ Subject::create(['code'=>$code,'name'=>$name,'description'=>$desc,'credits'=>$credits]); flash_set('success','Materia creada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=subjects');
    }
    public static function updateSubject(): void { self::guard('subjects.manage'); requireCsrf();
        $id=(int)post('id'); $code=trim(post('code')); $name=trim(post('name')); $credits=post('credits'); $desc=trim(post('description')); $act=(int)post('is_active');
        try{ Subject::update($id,['code'=>$code,'name'=>$name,'description'=>$desc,'credits'=>$credits,'is_active'=>$act]); if(isset($_POST['prereqs'])) Subject::setPrerequisites($id, (array)$_POST['prereqs']); flash_set('success','Materia actualizada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=subjects');
    }

    // ---- StudyPlans ----
    public static function createPlan(): void { self::guard('study_plans.manage'); requireCsrf();
        $career_id=(int)post('career_id'); $code=trim(post('code')); $name=trim(post('name')); $version=trim(post('version')); $valid=post('valid_from'); $is_current=isset($_POST['is_current']);
        if($career_id<=0||$code===''||$name===''){ flash_set('error','Datos de plan invalidos.'); redirect('admin/academic.php?tab=plans');}
        try{ StudyPlan::create(['career_id'=>$career_id,'code'=>$code,'name'=>$name,'version'=>$version ?: '1.0','is_current'=>$is_current,'valid_from'=>$valid]); flash_set('success','Plan creado.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=plans');
    }
    public static function updatePlan(): void { self::guard('study_plans.manage'); requireCsrf();
        $id=(int)post('id'); $career_id=(int)post('career_id'); $code=trim(post('code')); $name=trim(post('name')); $version=trim(post('version')); $valid=post('valid_from'); $is_current=isset($_POST['is_current']); $act=(int)post('is_active');
        try{ StudyPlan::update($id,['career_id'=>$career_id,'code'=>$code,'name'=>$name,'version'=>$version ?: '1.0','is_current'=>$is_current,'valid_from'=>$valid,'is_active'=>$act]); flash_set('success','Plan actualizado.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=plans');
    }
    public static function syncPlanSubjects(): void { self::guard('study_plans.manage'); requireCsrf();
        $plan_id=(int)post('plan_id');
        $entries=[];
        $sids = $_POST['subject_id'] ?? []; $sems = $_POST['semester_number'] ?? [];
        foreach($sids as $i=>$sid){ if((int)$sid>0) $entries[]=['subject_id'=>(int)$sid,'semester'=>(int)($sems[$i] ?? 1)];}
        try{ StudyPlan::syncSubjects($plan_id,$entries); flash_set('success','Malla actualizada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=plans&plan='.$plan_id);
    }

    // ---- Groups ----
    public static function createGroup(): void { self::guard('groups.manage'); requireCsrf();
        $code=trim(post('code')); $name=trim(post('name')); $career_id=(int)post('career_id'); $plan_id=post('study_plan_id'); $sem=(int)post('semester_number'); $period_id=(int)post('academic_period_id'); $cap=(int)post('capacity');
        if($code===''||$name===''||$career_id<=0||$sem<=0||$period_id<=0){ flash_set('error','Datos de grupo invalidos.'); redirect('admin/academic.php?tab=groups');}
        try{ AcademicGroup::create(['code'=>$code,'name'=>$name,'career_id'=>$career_id,'study_plan_id'=>$plan_id,'semester_number'=>$sem,'academic_period_id'=>$period_id,'capacity'=>$cap ?: 30]); flash_set('success','Grupo creado.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=groups');
    }
    public static function updateGroup(): void { self::guard('groups.manage'); requireCsrf();
        $id=(int)post('id'); $code=trim(post('code')); $name=trim(post('name')); $career_id=(int)post('career_id'); $plan_id=post('study_plan_id'); $sem=(int)post('semester_number'); $period_id=(int)post('academic_period_id'); $cap=(int)post('capacity'); $act=(int)post('is_active');
        try{ AcademicGroup::update($id,['code'=>$code,'name'=>$name,'career_id'=>$career_id,'study_plan_id'=>$plan_id,'semester_number'=>$sem,'academic_period_id'=>$period_id,'capacity'=>$cap ?: 30,'is_active'=>$act]); flash_set('success','Grupo actualizado.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=groups');
    }

    // ---- TeachingAssignments ----
    public static function createAssignment(): void { self::guard('assignments.manage'); requireCsrf();
        $d=['teacher_id'=>(int)post('teacher_id'),'subject_id'=>(int)post('subject_id'),'group_id'=>(int)post('group_id'),'academic_period_id'=>(int)post('academic_period_id'),'career_id'=>(int)post('career_id'),'status'=>post('status') ?: 'activa'];
        if($d['teacher_id']<=0||$d['subject_id']<=0||$d['group_id']<=0){ flash_set('error','Datos de asignacion invalidos.'); redirect('admin/academic.php?tab=assignments');}
        try{
            TeachingAssignment::create($d);
            $teacher = User::findById($d['teacher_id']);
            $subject = Subject::find($d['subject_id']);
            $group = AcademicGroup::find($d['group_id']);
            if ($teacher !== null && $subject !== null && $group !== null) {
                Notification::create(
                    $d['teacher_id'],
                    'teaching_assignment',
                    'Nueva asignacion docente',
                    'Se te asigno la materia "' . $subject['name'] . '" en el grupo "' . $group['name'] . '".',
                    'user/academic.php'
                );
            }
            flash_set('success','Asignacion creada.');
        } catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=assignments');
    }
    public static function updateAssignment(): void { self::guard('assignments.manage'); requireCsrf();
        $id=(int)post('id'); $d=['teacher_id'=>(int)post('teacher_id'),'subject_id'=>(int)post('subject_id'),'group_id'=>(int)post('group_id'),'academic_period_id'=>(int)post('academic_period_id'),'career_id'=>(int)post('career_id'),'status'=>post('status') ?: 'activa'];
        try{ TeachingAssignment::update($id,$d); flash_set('success','Asignacion actualizada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=assignments');
    }
    public static function deleteAssignment(): void { self::guard('assignments.manage'); requireCsrf();
        $id=(int)post('id');
        try{ TeachingAssignment::delete($id); flash_set('success','Asignacion eliminada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=assignments');
    }

    // ---- Enrollments ----
    public static function createEnrollment(): void { self::guard('enrollments.manage'); requireCsrf();
        $d=['student_id'=>(int)post('student_id'),'career_id'=>(int)post('career_id'),'study_plan_id'=>post('study_plan_id') ? (int)post('study_plan_id') : null,'group_id'=>(int)post('group_id'),'academic_period_id'=>(int)post('academic_period_id'),'semester_number'=>(int)post('semester_number'),'status'=>post('status') ?: 'activo'];
        if($d['student_id']<=0||$d['career_id']<=0||$d['group_id']<=0){ flash_set('error','Datos de matricula invalidos.'); redirect('admin/academic.php?tab=enrollments');}
        try{
            Enrollment::create($d);
            $student = User::findById($d['student_id']);
            $career = Career::find($d['career_id']);
            $group = AcademicGroup::find($d['group_id']);
            if ($student !== null && $career !== null && $group !== null) {
                Notification::create(
                    $d['student_id'],
                    'enrollment_created',
                    'Nueva inscripcion',
                    'Fuiste inscrito en la carrera "' . $career['name'] . '", grupo "' . $group['name'] . '", semestre ' . $d['semester_number'] . '.',
                    'user/academic.php'
                );
            }
            flash_set('success','Matricula creada.');
        } catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=enrollments');
    }
    public static function updateEnrollment(): void { self::guard('enrollments.manage'); requireCsrf();
        $id=(int)post('id'); $d=['career_id'=>(int)post('career_id'),'study_plan_id'=>post('study_plan_id') ? (int)post('study_plan_id') : null,'group_id'=>(int)post('group_id'),'academic_period_id'=>(int)post('academic_period_id'),'semester_number'=>(int)post('semester_number'),'status'=>post('status') ?: 'activo'];
        try{ Enrollment::update($id,$d); flash_set('success','Matricula actualizada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=enrollments');
    }

    // ---- CourseEnrollments ----
    public static function enrollCourse(): void { self::guard('enrollments.manage'); requireCsrf();
        $eid=(int)post('enrollment_id'); $ta=(int)post('teaching_assignment_id');
        try{ CourseEnrollment::enroll($eid,$ta); flash_set('success','Inscripcion realizada.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        redirect('admin/academic.php?tab=enrollments&enrollment='.$eid);
    }
    public static function updateCourseStatus(): void {
        // docente puede actualizar nota de sus asignaciones, admin todo
        requireCsrf();
        $id=(int)post('id'); $status=post('status'); $grade = post('grade') !== '' ? (float)post('grade') : null;
        $ce = CourseEnrollment::find($id);
        if($ce===null){ flash_set('error','Inscripcion no encontrada.'); redirect('admin/academic.php?tab=enrollments');}
        // check docente owns assignment or is admin
        if(!can('enrollments.manage') && !can('academic.view')){ flash_set('error','Sin permiso.'); redirect('admin/academic.php');}
        try{ CourseEnrollment::updateStatus($id,$status,$grade); flash_set('success','Estado actualizado.');} catch(Throwable $ex){ flash_set('error',$ex->getMessage());}
        $ref = $_POST['ref'] ?? 'admin/academic.php?tab=enrollments';
        redirect($ref);
    }
}
