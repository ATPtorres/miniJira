"""Critical branch: real HTTP authorization, roster imports, revocation, legacy quarantine."""
import json
import urllib.parse
import urllib.request
import urllib.error
import uuid


def run_isolation_checks(base, fx, student, teacher, admin, observer, outsider, php, require, root, stream_event):
    project, task, classroom = fx['project'], fx['task'], fx['classroom']
    c2 = fx['classroom_other']
    teacher2=type(teacher)();teacher2.login('docente2')
    classes=f'admin/classes.php?id={classroom}'
    board=f'projects/board.php?id={project}'
    page=f'projects/task.php?project_id={project}&task_id={task}'
    original=json.loads(php(f'echo json_encode(Classroom::find({classroom}));'))
    initial_task=json.loads(php(f'echo json_encode(Task::find({task}));'))
    temporary=[]

    def class_data(roster='estudiante@beta.test\nobserver@beta.test', **changes):
        data={k:original[k] for k in ['id','external_code','subject','career','semester_number','group_name','period','teacher_id','is_active']}
        data['roster']=roster;data.update(changes);return data

    def multipart(client,path,fields,filename,content,file_field='files[]'):
        boundary=uuid.uuid4().hex;fields={**fields,'csrf_token':client.token(path)};body=bytearray()
        for key,value in fields.items():body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{file_field}"; filename="{filename}"\r\nContent-Type: text/plain\r\n\r\n'.encode());body.extend(content);body.extend(f'\r\n--{boundary}--\r\n'.encode())
        req=urllib.request.Request(base+path,data=bytes(body),headers={'Content-Type':'multipart/form-data; boundary='+boundary})
        try:r=client.o.open(req,timeout=15)
        except urllib.error.HTTPError as e:r=e
        with r:return r.code,r.read().decode(),r.url

    def restore_team():
        data={k:v for k,v in json.loads(php(f'echo json_encode(Project::find({project}));')).items() if k in ['title','description','objectives','start_date','end_date']}
        data.update(action='update-project',project_id=project,classroom_id=classroom)
        data['student_ids[]']=[fx['student'],fx['observer']]
        return admin.post(f'projects/form.php?id={project}',data)

    try:
        require(teacher.req('admin/classes.php')[0]==403 and student.req('admin/classes.php')[0]==403,'isolation: only class administrators manage authorized lists')
        for client in [student,teacher,teacher2]:
            require(client.req('admin/academic.php',{'action':'update-course-status','id':'1','grade':'100'})[0]==410 and client.req('user/academic.php',{'action':'update-course-status','id':'1','grade':'100'})[0]==410,'isolation: retired official-grade routes reject every write')
        require('QA-B' not in teacher.req(f'projects/form.php?classroom_id={classroom}')[1] and 'Outsider Beta' not in teacher.req(f'projects/form.php?classroom_id={classroom}')[1],'isolation: project selector excludes students outside teacher class')
        require(teacher2.req(f'projects/form.php?classroom_id={classroom}')[0]==403 and observer.req(f'user/classes.php?id={c2}')[0]==403,'isolation: class IDs do not expand teacher or student visibility')
        require(student.req(f'user/classes.php?id={c2}')[0]==200 and student.req(f'user/classes.php?id={classroom}')[0]==200,'isolation: one student may belong to different careers and groups simultaneously')
        count=int(php('echo db()->query("SELECT COUNT(*) FROM projects")->fetchColumn();'))
        payload={'action':'create-project','classroom_id':classroom,'title':'Proyecto aislado','description':'Descripcion suficiente para pruebas','objectives':'Objetivos suficientes para pruebas','start_date':php('echo date("Y-m-d");'),'end_date':php('echo date("Y-m-d",strtotime("+10 days"));'),'student_ids[]':[fx['outsider']]}
        teacher.post('projects/form.php',payload)
        require(int(php('echo db()->query("SELECT COUNT(*) FROM projects")->fetchColumn();'))==count,'isolation: forged foreign participant rejected by server')
        payload['classroom_id']=c2;payload['title']='PROYECTO_SECRETO_OTRA_CLASE'
        code,_,url=teacher2.post(f'projects/form.php?classroom_id={c2}',payload)
        foreign=int(php('echo db()->query("SELECT MAX(id) FROM projects")->fetchColumn();'));temporary.append(foreign)
        require(code==200 and 'board.php' in url,'isolation: assigned teacher creates a project using its class roster')
        require(student.req(f'projects/board.php?id={foreign}')[0]==403 and teacher.req(f'projects/board.php?id={foreign}')[0]==403,'isolation: another class or another team cannot access the project')
        require('PROYECTO_SECRETO_OTRA_CLASE' not in teacher.req('user/dashboard.php')[1] and 'Administración' not in teacher.req('user/dashboard.php')[1],'isolation: workload and filter options contain only authorized classes')
        batch=json.loads(teacher.req(f'api/project-metrics.php?batch=1&ids={project},{foreign}')[1])
        require([p['id'] for p in batch['projects']]==[project] and teacher.req('api/project-metrics.php?batch=1&ids='+','.join(['1']*26))[0]==400,'isolation: batched metrics filter foreign IDs and limit request size')
        teacher.post(board,{'action':'update-task-status','task_id':task,'status':'review'})
        grade={'action':'grade-task','task_id':task,'grade_result':'8','grade_comment':'RETROALIMENTACION_PRIVADA_CLASE'}
        require(student.req(board,{**grade,'csrf_token':student.token(page)})[0]==403 and teacher2.req(board,{**grade,'csrf_token':teacher2.token('user/dashboard.php')})[0]==403,'isolation: student and foreign teacher cannot grade task')
        teacher.post(board,grade)
        require('RETROALIMENTACION_PRIVADA_CLASE' in student.req(page)[1] and 'RETROALIMENTACION_PRIVADA_CLASE' not in observer.req(page)[1],'isolation: task feedback visible only to teacher and assigned student')
        require(next(s for s in json.loads(observer.req(f'api/project-metrics.php?id={project}')[1])['students'] if s['id']==fx['student'])['earned'] is None,'isolation: metrics do not reveal another student grade')
        multipart(student,page,{'action':'upload-file','task_id':task},'isolation.txt',b'Entrega que debe conservarse\n')
        file_id=int(php(f'echo db()->query("SELECT COALESCE(MAX(id),0) FROM project_files WHERE project_id={project}")->fetchColumn();'))
        # Graded task is locked, so use teacher upload for a durable retained-history fixture.
        if not file_id:
            multipart(teacher,page,{'action':'upload-file','task_id':task},'isolation.txt',b'Entrega que debe conservarse\n')
            file_id=int(php(f'echo db()->query("SELECT COALESCE(MAX(id),0) FROM project_files WHERE project_id={project}")->fetchColumn();'))
        download=f'projects/download.php?file_id={file_id}'
        paths=[board,page,f'projects/dashboard.php?id={project}',f'projects/calendar.php?id={project}',f'projects/files.php?id={project}',f'projects/file-history.php?id={project}',f'projects/analytics.php?id={project}',f'projects/report.php?id={project}',f'api/project-metrics.php?id={project}',download]
        note=php(f'echo Notification::create({fx["student"]},"comment_added","REVOCACION_CLASE","REVOCACION_CLASE","projects/board.php?id={project}");')
        require(int(note)>0,'isolation: authorized participant receives a project notification')
        stream=student.raw('api/notifications.php?mode=stream')
        try:
            before_feed=stream_event(stream,'notifications')
            admin.post(classes,class_data(roster='observer@beta.test'))
            revoked_feed=stream_event(stream,'notifications')
            require(revoked_feed['version']!=before_feed['version'] and not any(n.get('project_id')==project for n in revoked_feed['items']),'isolation: open SSE stream withdraws project notifications after roster revocation')
        finally:
            stream.close()
        require(all(student.req(path)[0]==403 for path in paths),'isolation: roster withdrawal revokes pages, API, files, history and PDF')
        php(f'db()->exec("INSERT IGNORE INTO project_students(project_id,student_id) VALUES({project},{fx["student"]})");')
        require(student.req(board)[0]==403,'isolation: project membership alone cannot override missing classroom authorization')
        require(not any(n.get('project_id')==project for n in student.feed()['items']) and 'REVOCACION_CLASE' not in student.req('user/notifications.php')[1],'isolation: revoked notifications disappear from feed and paginated list')
        require(student.req(f'user/classes.php?id={c2}')[0]==200 and not json.loads(php(f'echo json_encode(Task::forUser({fx["student"]}));')),'isolation: withdrawal affects only that class and removes personal task results')
        admin.post(classes,class_data())
        # Remove forged membership before checking intentional non-restoration of previous teams.
        php(f'db()->exec("DELETE FROM project_students WHERE project_id={project} AND student_id={fx["student"]}");')
        require(student.req(board)[0]==403,'isolation: restoring class membership does not silently restore project teams')
        restore_team()
        require(student.req(download)[0]==200,'isolation: retained delivery can be downloaded after authorized restoration')
        admin.post(classes,class_data(is_active='0'))
        require(student.req(board)[0]==403 and teacher.req(board)[0]==403 and admin.req(board)[0]==200,'isolation: inactive class revoked for participants but retained for administrative supervision')
        admin.post(classes,class_data(teacher_id=fx['teacher2']))
        require(teacher.req(board)[0]==403 and teacher2.req(board)[0]==200 and int(json.loads(php(f'echo json_encode(Project::find({project}));'))['responsible_teacher_id'])==fx['teacher2'],'isolation: teacher reassignment revokes old teacher and updates project responsibility')
        admin.post(classes,class_data());restore_team()
        # Validate CSV atomically using real multipart requests, including unknown account rollback.
        old=json.loads(php(f'echo json_encode(Classroom::studentIds({classroom}));'))
        multipart(admin,classes,class_data(), 'roster.csv',b'email\nobserver@beta.test\nunknown@beta.test\n','roster_file')
        require(json.loads(php(f'echo json_encode(Classroom::studentIds({classroom}));'))==old,'isolation: invalid CSV leaves complete authorized list unchanged')
        multipart(admin,classes,class_data(), 'roster.csv',b'\xef\xbb\xbfemail\nestudiante@beta.test\nobserver@beta.test\nestudiante@beta.test\n','roster_file')
        require(sorted(json.loads(php(f'echo json_encode(Classroom::studentIds({classroom}));')))==sorted(old),'isolation: valid CSV supports BOM and duplicate emails without duplicate membership')
        # An unclassified historical project must never be published by guessing a class.
        legacy=int(php(f'$q=db()->prepare("INSERT INTO projects(title,description,objectives,start_date,end_date,responsible_teacher_id,created_by_id) VALUES(?,?,?,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 7 DAY),?,?)");$q->execute(["LEGACY_SIN_CLASE","Descripcion legada","Objetivos legados",{fx["teacher"]},{fx["teacher"]}]);echo db()->lastInsertId();'))
        temporary.append(legacy)
        php(f'db()->exec("INSERT INTO project_students(project_id,student_id) VALUES({legacy},{fx["student"]})");')
        require(student.req(f'projects/board.php?id={legacy}')[0]==403 and teacher.req(f'projects/board.php?id={legacy}')[0]==403 and admin.req(f'projects/board.php?id={legacy}')[0]==200,'isolation: legacy projects remain quarantined until administrative classification')
        p={**payload,'action':'update-project','project_id':legacy,'classroom_id':classroom,'student_ids[]':[fx['student']]}
        admin.post(f'projects/form.php?id={legacy}',p)
        require(student.req(f'projects/board.php?id={legacy}')[0]==200 and int(json.loads(php(f'echo json_encode(Project::find({legacy}));'))['responsible_teacher_id'])==fx['teacher'],'isolation: administrator classifies legacy project without becoming responsible teacher')
        admin.post(f'projects/form.php?id={legacy}',{**p,'classroom_id':c2})
        require(int(json.loads(php(f'echo json_encode(Project::find({legacy}));'))['classroom_id'])==classroom,'isolation: classified project cannot be moved across classes')
        from playwright.sync_api import sync_playwright
        import os, shutil
        with sync_playwright() as pw:
            browser=pw.chromium.launch(headless=True,executable_path=os.environ.get('CHROMIUM_BIN') or shutil.which('chromium'),args=['--no-sandbox'])
            try:
                view=browser.new_page();errors=[];view.on('pageerror',lambda e:errors.append(str(e)))
                view.goto(base+'login.php');view.fill('#email','estudiante@beta.test');view.fill('#password','Beta.Test2026!');view.click('button[type=submit]');view.wait_for_url('**/user/dashboard.php')
                view.select_option('select[name=career]','Sistemas');view.wait_for_load_state('networkidle')
                view.select_option('select[name=status]','graded');view.get_by_role('button',name='Aplicar filtros',exact=True).click();view.wait_for_load_state('networkidle')
                require('career=Sistemas' in view.url and 'status=graded' in view.url and view.locator('tbody tr').count()==1,'isolation: browser applies academic and evaluation filters to personal workload')
                view.get_by_role('link',name='Abrir tarea',exact=True).click();view.wait_for_load_state('networkidle');view.go_back();view.wait_for_load_state('networkidle')
                require(view.locator('select[name=career]').input_value()=='Sistemas' and view.locator('select[name=status]').input_value()=='graded','isolation: browser navigation preserves workload filters')
                other=browser.new_page();other.goto(base+'login.php');other.fill('#email','admin@beta.test');other.fill('#password','Beta.Test2026!');other.click('button[type=submit]');other.wait_for_url('**/admin/dashboard.php')
                other.goto(base+classes);other.set_input_files('#roster_file',{'name':'clase.csv','mimeType':'text/csv','buffer':b'email\nestudiante@beta.test\nobserver@beta.test\n'});other.get_by_role('button',name='Guardar clase y participantes',exact=True).click();other.wait_for_load_state('networkidle')
                require('Clase y lista autorizada actualizadas' in other.locator('main').inner_text() and not errors,'isolation: administrator imports authorized CSV through browser form')
            finally:
                browser.close()
        require(int(php(f'echo db()->query("SELECT COUNT(*) FROM classroom_audit WHERE classroom_id={classroom}")->fetchColumn();'))>=7,'isolation: class and membership changes retain administrative audit trail')
    finally:
        admin.post(classes,class_data());restore_team()
        php(f'db()->exec("UPDATE project_tasks SET status=\'{initial_task["status"]}\',grade_result=NULL,grade_comment=NULL WHERE id={task}");')
        for pid in temporary:php(f'db()->exec("DELETE FROM projects WHERE id={pid}");')
        php(f'foreach(db()->query("SELECT * FROM project_files WHERE project_id={project} AND original_name=\'isolation.txt\'")->fetchAll() as $f){{@unlink(ProjectFile::projectDir({project})."/".$f["stored_name"]);db()->exec("DELETE FROM project_files WHERE id=".(int)$f["id"]);}}')
