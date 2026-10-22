<?php
declare(strict_types=1);
final class ClassroomController
{
    public static function save(): void
    {
        requirePermission('classes.manage');requireCsrf();
        $id=(int)post('id') ?: null;
        try {
            $text=post('roster');
            if (isset($_FILES['roster_file']) && $_FILES['roster_file']['error']!==UPLOAD_ERR_NO_FILE) {
                $f=$_FILES['roster_file'];
                if ($f['error']!==UPLOAD_ERR_OK || !is_uploaded_file($f['tmp_name']) || filesize($f['tmp_name'])>2*1024*1024) throw new InvalidArgumentException('El CSV debe pesar como máximo 2 MB.');
                $text=file_get_contents($f['tmp_name']);
            }
            $emails=[];$lines=preg_split('/\R/',ltrim($text,"\xEF\xBB\xBF"));
            foreach($lines as $line) {
                if(trim($line)==='')continue;
                $columns=str_getcsv($line,',','"','');$email=normalize_email(trim($columns[0]??''));
                if($email==='email' || $email==='correo')continue;
                if(!filter_var($email,FILTER_VALIDATE_EMAIL))throw new InvalidArgumentException('La lista debe contener un correo válido por fila o un CSV con email en la primera columna.');
                $emails[$email]=true;
            }
            if(count($emails)>5000)throw new InvalidArgumentException('Máximo 5000 participantes por importación.');
            $students=[];
            foreach(array_chunk(array_keys($emails),400) as $chunk){
                $hashes=array_map(fn($email)=>EncryptionService::emailLookupHash($email),$chunk);$marks=implode(',',array_fill(0,count($chunk),'?'));
                $q=db()->prepare('SELECT u.id,u.email_lookup_hash,u.email FROM users u JOIN roles r ON r.id=u.role_id WHERE u.is_active=1 AND r.code="estudiante" AND (u.email_lookup_hash IN ('.$marks.') OR u.email IN ('.$marks.'))');
                $q->execute(array_merge($hashes,$chunk));$found=[];
                foreach($q->fetchAll() as $row){$students[]=(int)$row['id'];$found[$row['email_lookup_hash']??'']=true;if($row['email'])$found[EncryptionService::emailLookupHash($row['email'])]=true;}
                foreach($chunk as $email)if(!isset($found[EncryptionService::emailLookupHash($email)]))throw new InvalidArgumentException('No existe una cuenta activa de estudiante para '.$email.'. Crea o reactiva esa cuenta antes de importar.');
            }
            $data=[];foreach(['external_code','subject','career','semester_number','group_name','period','teacher_id','is_active'] as $key)$data[$key]=post($key);
            $id=Classroom::save($id,$data,$students,current_user_id());
            flash_set('success','Clase y lista autorizada actualizadas. Las bajas se aplican de inmediato.');
        }catch(Throwable $e){error_log('Error guardando clase: '.$e->getMessage());flash_set('error',$e instanceof InvalidArgumentException?$e->getMessage():'No se pudo guardar. Comprueba que el código externo no esté repetido.');}
        redirect('admin/classes.php'.($id?'?id='.$id:''));
    }
}
