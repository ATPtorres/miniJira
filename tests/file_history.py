"""ULAT-21: real file versions, protected history, deletion audit and concurrent updates."""
import base64
import concurrent.futures
import json
import os
import pathlib
import shutil
import urllib.error
import urllib.request
import uuid


def run_file_history_checks(base, fx, student, teacher, observer, outsider, php, require, root):
    project, task = fx['project'], fx['task']
    task_page = f'projects/task.php?project_id={project}&task_id={task}'
    files_page = f'projects/files.php?id={project}'
    history = f'projects/file-history.php?id={project}'
    directory = root / 'storage/uploads/projects' / str(project)
    initial_paths = set(directory.glob('*'))
    private_comment = None

    def query(sql):
        value = base64.b64encode(sql.encode()).decode()
        return json.loads(php(f'echo json_encode(db()->query(base64_decode("{value}"))->fetchAll());'))

    def rows():
        return query(f"SELECT * FROM project_files WHERE project_id={project} AND original_name LIKE 'ulat21-%' ORDER BY id")

    def upload(client, name='ulat21-v1.txt', content=b'Primera entrega\n', replaces=0, path=task_page, task_id=task, files=None, **fields):
        boundary = uuid.uuid4().hex
        values = {'action':'upload-file','task_id':task_id,'replaces_file_id':replaces,'csrf_token':client.token(path), **fields}
        data = bytearray()
        for key, value in values.items():
            data.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        for filename, contents in (files if files is not None else [(name,content)]):
            data.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="files[]"; filename="{filename}"\r\nContent-Type: text/plain\r\n\r\n'.encode())
            data.extend(contents)
            data.extend(b'\r\n')
        data.extend(f'--{boundary}--\r\n'.encode())
        req = urllib.request.Request(base+path, data=bytes(data), headers={'Content-Type':'multipart/form-data; boundary='+boundary})
        try:
            response = client.o.open(req, timeout=20)
        except urllib.error.HTTPError as exc:
            response = exc
        with response:
            return response.code, response.read().decode()

    try:
        upload(student, version_number=999, version_root_id=123)
        first = rows()[-1]
        upload(student, name='ulat21-v2.txt', content=b'Segunda entrega\n', replaces=first['id'])
        second = rows()[-1]
        require(first['version_number'] == 1 and first['version_root_id'] is None and second['version_number'] == 2 and second['version_root_id'] == first['id'], 'ULAT-21: server assigns version numbers and links revised file, ignoring forged metadata')
        code, body, _ = student.req(task_page)
        require(code == 200 and 'ulat21-v2.txt' in body and 'ulat21-v1.txt' not in body, 'ULAT-21: task lists only current file version')
        for entry, data in [(first,b'Primera entrega\n'),(second,b'Segunda entrega\n')]:
            with teacher.raw(f'projects/download.php?file_id={entry["id"]}') as response:
                require(response.code == 200 and response.read() == data, 'ULAT-21: historical and current versions download original bytes v'+str(entry['version_number']))
        _, body, _ = student.req(history+f'&file_id={second["id"]}&task_id={task}')
        require('ulat21-v1.txt' in body and 'ulat21-v2.txt' in body and 'Anterior' in body and 'Actual' in body and 'Estudiante Beta' in body, 'ULAT-21: version history shows author, dates, prior and current status')
        require(int(php(f'echo ProjectFile::totalSize({project});')) >= len(b'Primera entrega\nSegunda entrega\n'), 'ULAT-21: retained historical versions consume project quota')
        before = len(rows())
        _, body = upload(student, replaces=first['id'])
        require(len(rows()) == before and 'versión más reciente' in body, 'ULAT-21: stale upload rejected without branching history')
        require(upload(observer, replaces=second['id'])[0] == 403, 'ULAT-21: unassigned member cannot revise submission')
        upload(teacher, name='ulat21-teacher.txt')
        teacher_file = rows()[-1]
        require(upload(student, replaces=teacher_file['id'])[0] == 403, 'ULAT-21: student cannot revise teacher-owned resource')
        upload(teacher, name='ulat21-teacher-revision.txt', replaces=second['id'])
        teacher_revision = rows()[-1]
        upload(student, name='ulat21-v4.txt', replaces=teacher_revision['id'])
        require(rows()[-1]['version_number'] == 4, 'ULAT-21: original owner can continue own submission after teacher revision')
        latest = rows()[-1]
        before = len(rows())
        upload(student, replaces=latest['id'], files=[('ulat21-a.txt',b'A\n'),('ulat21-b.txt',b'B\n')])
        require(len(rows()) == before, 'ULAT-21: new version requires exactly one uploaded file')
        upload(student, name='ulat21-v4.txt')
        require(rows()[-1]['version_root_id'] is None and rows()[-1]['version_number'] == 1, 'ULAT-21: identical filename creates independent file unless version explicitly selected')
        require(outsider.req(history)[0] == 403 and outsider.req(history+f'&file_id={first["id"]}')[0] == 403, 'ULAT-21: outsider cannot inspect history')
        other_project = int(query(f'SELECT id FROM projects WHERE id<>{project} AND deleted_at IS NULL LIMIT 1')[0]['id'])
        require(teacher.req(f'projects/file-history.php?id={other_project}&file_id={first["id"]}')[0] == 403, 'ULAT-21: file IDs cannot expose history through another project')
        other_task = int(query(f'SELECT id FROM project_tasks WHERE project_id={project} AND id<>{task} AND deleted_at IS NULL LIMIT 1')[0]['id'])
        require(student.req(history+f'&task_id={other_task}&file_id={first["id"]}')[0] == 403 and upload(student,replaces=latest['id'],path=files_page,task_id=other_task)[0] == 403, 'ULAT-21: task context cannot be changed while revising or viewing a file')
        private_comment = int(php(f'echo ProjectComment::create(["project_id"=>{project},"author_id"=>{fx["student"]},"body"=>"Private history fixture","visibility"=>"private"]);'))
        secret_file = int(php(f'echo ProjectFile::create(["project_id"=>{project},"task_id"=>null,"comment_id"=>{private_comment},"uploaded_by_id"=>{fx["student"]},"original_name"=>"ulat21-secret.txt","stored_name"=>"ulat21-secret.txt","mime_type"=>"text/plain","file_size"=>0,"description"=>""]);'))
        require('ulat21-secret.txt' not in observer.req(history)[1] and observer.req(history+f'&file_id={secret_file}')[0] == 403 and upload(teacher,path=files_page,task_id=0,replaces=secret_file)[0] == 403, 'ULAT-21: comment attachment privacy survives history and revision endpoints')

        upload(student,name='ulat21-race.txt')
        race_root = rows()[-1]
        clients = [type(student)(),type(student)()]
        for client in clients:
            client.login('estudiante')
        before = len(rows())
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            results = list(pool.map(lambda client:upload(client,name='ulat21-race-v2.txt',replaces=race_root['id']),clients))
        race_versions = [row for row in rows() if row['version_root_id'] == race_root['id']]
        require(len(rows()) == before+1 and len(race_versions) == 1 and race_versions[0]['version_number'] == 2 and sum('subido(s) correctamente' in body for _,body in results) == 1, 'ULAT-21: concurrent revisions create exactly one next version')
        before = len(rows())
        paths_before = set(directory.glob('*'))
        php('db()->exec("CREATE TRIGGER ulat21_fail_file BEFORE INSERT ON project_files FOR EACH ROW BEGIN IF NEW.original_name = \'ulat21-fail.txt\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Simulated failure\'; END IF; END");')
        try:
            upload(student,name='ulat21-fail.txt',replaces=latest['id'])
            require(len(rows()) == before and set(directory.glob('*')) == paths_before, 'ULAT-21: failed revision leaves prior version and physical files intact')
        finally:
            php('db()->exec("DROP TRIGGER ulat21_fail_file");')
        php(f'db()->exec("CREATE TRIGGER ulat21_fail_delete BEFORE UPDATE ON project_files FOR EACH ROW BEGIN IF NEW.id={latest["id"]} AND NEW.deleted_at IS NOT NULL THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Simulated failure\'; END IF; END");')
        try:
            teacher.post(files_page,{'action':'delete-file','file_id':latest['id']})
            require(all(row['deleted_at'] is None and (directory/row['stored_name']).exists() for row in rows() if row['id']==first['id'] or row['version_root_id']==first['id']), 'ULAT-21: failed deletion preserves complete version chain')
        finally:
            php('db()->exec("DROP TRIGGER ulat21_fail_delete");')
        teacher.post(files_page,{'action':'delete-file','file_id':latest['id']})
        deleted = [row for row in rows() if row['id']==first['id'] or row['version_root_id']==first['id']]
        require(all(row['deleted_at'] and row['deleted_by_id']==fx['teacher'] and not (directory/row['stored_name']).exists() for row in deleted), 'ULAT-21: deletion records actor and date, revokes entire chain and cleans binaries')
        require(all(teacher.req(f'projects/download.php?file_id={row["id"]}')[0] == 404 for row in deleted), 'ULAT-21: deleted versions cannot be downloaded')
        _, body, _ = student.req(history+f'&file_id={first["id"]}')
        require('Eliminado el' in body and 'Álvaro Beta' in body and 'Descargar v' not in body, 'ULAT-21: deleted metadata remains visible without download links')
        require(upload(student,replaces=latest['id'])[0] == 403, 'ULAT-21: deleted file cannot be revived by forged revision')

        # Verify the actual upload selector, navigation and older-version download in Chromium.
        from playwright.sync_api import sync_playwright
        upload(student,name='ulat21-browser-v1.txt',content=b'Original del navegador\n')
        browser_root = rows()[-1]
        with sync_playwright() as pw:
            browser = pw.chromium.launch(headless=True, executable_path=os.environ.get('CHROMIUM_BIN') or shutil.which('chromium'),args=['--no-sandbox'])
            try:
                page=browser.new_page(); page.on('dialog',lambda dialog:dialog.accept())
                page.goto(base+'login.php');page.fill('#email','estudiante@beta.test');page.fill('#password','Beta.Test2026!');page.click('button[type=submit]');page.wait_for_url('**/user/dashboard.php')
                page.goto(base+task_page)
                page.select_option('#task-file-version',str(browser_root['id']))
                page.set_input_files('#file-input',{'name':'ulat21-browser-v2.txt','mimeType':'text/plain','buffer':b'Nueva version del navegador\n'})
                page.get_by_role('button',name='Subir',exact=True).click();page.wait_for_load_state('networkidle')
                page.locator('.file-item').filter(has_text='ulat21-browser-v2.txt').get_by_role('link',name='Historial',exact=True).click()
                require(page.get_by_role('link',name='Descargar v1',exact=True).count()==1 and page.get_by_role('link',name='Descargar v2',exact=True).count()==1, 'ULAT-21: browser creates revision and opens complete history')
                with page.expect_download() as download:
                    page.get_by_role('link',name='Descargar v1',exact=True).click()
                require(pathlib.Path(download.value.path()).read_bytes()==b'Original del navegador\n','ULAT-21: browser downloads retained previous version')
            finally:
                browser.close()
        php(f'for($i=0;$i<51;$i++)ProjectFile::create(["project_id"=>{project},"task_id"=>{task},"uploaded_by_id"=>{fx["teacher"]},"original_name"=>"ulat21-page-".$i.".txt","stored_name"=>"ulat21-page-".$i,"mime_type"=>"text/plain","file_size"=>0,"description"=>""]);')
        _, body, _ = student.req(history+f'&task_id={task}')
        _, second_page, _ = student.req(history+f'&task_id={task}&page=999999')
        require('page=2' in body and f'task_id={task}' in body and 'Página 2 de 2' in second_page, 'ULAT-21: history paginates and preserves task filter, clamping out-of-range pages')
        saved = rows()
        php('require "database/migrate_beta.php";')
        php('require "database/migrate_beta.php";')
        require(rows() == saved, 'ULAT-21: repeated migration preserves files, versions and deletion metadata')
    finally:
        if private_comment:
            php(f'db()->exec("DELETE FROM project_comments WHERE id={private_comment}");')
        php(f'db()->exec("DELETE FROM project_files WHERE project_id={project} AND original_name LIKE \'ulat21-%\'");')
        for path in set(directory.glob('*')) - initial_paths:
            path.unlink()
