"""ULAT-19: real multipart requests against the HTTP suite's isolated fixtures."""
import json
import urllib.request
import urllib.error
import uuid


def run_upload_checks(base, fx, student, teacher, admin, observer, outsider, php, require, root):
    project, task = fx['project'], fx['task']
    page = f'projects/task.php?project_id={project}&task_id={task}'
    files_page = f'projects/files.php?id={project}'
    directory = root / 'storage/uploads/projects' / str(project)
    initial_paths = set(directory.glob('*'))
    marker = 'ulat19-'

    def count():
        return int(php(f'echo db()->query("SELECT COUNT(*) FROM project_files WHERE project_id={project}")->fetchColumn();'))

    def upload(client, path=page, task_id=task, files=None, token=True, **fields):
        boundary = uuid.uuid4().hex
        values = {'action': 'upload-file', 'task_id': str(task_id), **fields}
        if token:
            values['csrf_token'] = client.token(path)
        body = bytearray()
        for name, value in values.items():
            body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{name}"\r\n\r\n{value}\r\n'.encode())
        for name, data, mime in (files if files is not None else [(marker+'entrega.txt', b'Entrega universitaria de prueba\n', 'text/plain')]):
            body.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="files[]"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode())
            body.extend(data)
            body.extend(b'\r\n')
        body.extend(f'--{boundary}--\r\n'.encode())
        request = urllib.request.Request(base+path, data=bytes(body), headers={'Content-Type': 'multipart/form-data; boundary='+boundary})
        try:
            response = client.o.open(request, timeout=20)
        except urllib.error.HTTPError as exc:
            response = exc
        with response:
            return response.code, response.read().decode(), response.url

    before = count()
    try:
        require('name="files[]"' in student.req(page)[1], 'ULAT-19: assigned student sees submission form with default permissions')
        require('name="files[]"' not in observer.req(page)[1], 'ULAT-19: unassigned member has no submission form')
        status, body, url = upload(student, description='Entregable ULAT-19', created_at='2000-01-01', uploaded_by_id=fx['teacher'])
        require(status == 200 and url == base+page and 'subido(s) correctamente' in body, 'ULAT-19: assigned student submits and returns to own task')
        record = json.loads(php(f'echo json_encode(db()->query("SELECT *, ABS(TIMESTAMPDIFF(SECOND,created_at,NOW())) AS age FROM project_files WHERE project_id={project} ORDER BY id DESC LIMIT 1")->fetch());'))
        require(int(record['task_id']) == task and int(record['uploaded_by_id']) == fx['student'] and int(record['age']) < 15,
                'ULAT-19: server records task, actual author and current date/time; forged metadata ignored')
        visible_date = php(f'echo date("d/m/Y H:i",strtotime("{record["created_at"]}"));')
        require(visible_date in body and 'Entregable ULAT-19' in body, 'ULAT-19: submission description and timestamp visible')
        download = f'projects/download.php?file_id={record["id"]}'
        with teacher.raw(download) as response:
            require(response.code == 200 and response.read() == b'Entrega universitaria de prueba\n' and 'attachment' in response.headers['Content-Disposition'],
                    'ULAT-19: teacher downloads exact submitted contents')
        require(outsider.req(download)[0] == 403, 'ULAT-19: outsider cannot download submission')
        require(any(n['type'] == 'file_uploaded' and n['link'] == page for n in teacher.feed()['items']), 'ULAT-19: teacher notified with link to submitted task')
        accepted = count()
        _, csrf_body, _ = upload(student, token=False)
        require(count() == accepted and 'La sesion expiro' in csrf_body, 'ULAT-19: multipart upload requires CSRF')
        require(upload(observer)[0] == 403, 'ULAT-19: unassigned member cannot submit')
        require(upload(outsider, token=False)[0] == 403, 'ULAT-19: outsider cannot submit')
        require(upload(student, path=files_page, task_id=0)[0] == 403, 'ULAT-19: student cannot upload general project resources')
        other_task = int(php(f'echo db()->query("SELECT id FROM project_tasks WHERE project_id={project} AND id<>{task} AND deleted_at IS NULL LIMIT 1")->fetchColumn();'))
        require(upload(student, task_id=other_task)[0] == 403, 'ULAT-19: URL task and posted task must agree')
        foreign_task = int(php(f'echo db()->query("SELECT id FROM project_tasks WHERE project_id<>{project} AND deleted_at IS NULL LIMIT 1")->fetchColumn();'))
        require(upload(teacher, path=files_page, task_id=foreign_task)[0] == 403, 'ULAT-19: even teacher cannot attach task from another project')
        require(upload(teacher, path=files_page, task_id=2147483647)[0] == 403, 'ULAT-19: nonexistent task rejected')
        require(student.post(page, {'action': 'delete-file', 'file_id': record['id']})[0] == 403, 'ULAT-19: submission permission does not grant deletion')
        require(count() == accepted, 'ULAT-19: rejected access attempts create no files')

        for files, fields, message in [
            ([], {}, 'missing files'),
            ([(marker+'invalid.php', b'<?php echo 1;', 'text/plain')], {}, 'forbidden extension'),
            ([(marker+'binary.txt', b'\x00\x01\x02\x03'*100, 'text/plain')], {}, 'forged MIME type'),
            ([(marker+'large.txt', b'x'*(10*1024*1024+1), 'text/plain')], {}, 'file larger than 10 MB'),
            (None, {'description': 'x'*501}, 'description longer than 500 characters'),
            ([(marker+'valid.txt', b'Primero valido\n', 'text/plain'), (marker+'invalid.php', b'Invalid\n', 'text/plain')], {}, 'mixed batch without partial submission'),
        ]:
            status, body, _ = upload(student, files=files, **fields)
            require(status == 200 and count() == accepted and 'alert-error' in body, 'ULAT-19: rejects '+message)

        old_size = int(record['file_size'])
        php(f'db()->exec("UPDATE project_files SET file_size=52428800 WHERE id={record["id"]}");')
        try:
            _, body, _ = upload(student)
            require(count() == accepted and '50 MB' in body, 'ULAT-19: project quota enforced')
        finally:
            php(f'db()->exec("UPDATE project_files SET file_size={old_size} WHERE id={record["id"]}");')

        original = json.loads(php(f'echo json_encode(Task::find({task}));'))
        for change, message in [("due_date=DATE_SUB(CURDATE(),INTERVAL 1 DAY)", 'overdue task'), ("status=\"done\",grade_result=8", 'graded task')]:
            php(f'db()->exec(\'UPDATE project_tasks SET {change} WHERE id={task}\');')
            try:
                require(upload(student)[0] == 403 and 'name="files[]"' not in student.req(page)[1], 'ULAT-19: closes student submission for '+message)
            finally:
                php(f'db()->exec("UPDATE project_tasks SET due_date=\'{original["due_date"]}\',status=\'{original["status"]}\',grade_result=NULL WHERE id={task}");')
        end_date = php(f'echo Project::find({project})["end_date"];')
        php(f'db()->exec("UPDATE projects SET end_date=DATE_SUB(CURDATE(),INTERVAL 1 DAY) WHERE id={project}");')
        try:
            require(upload(student)[0] == 403, 'ULAT-19: expired project closes student submissions')
        finally:
            php(f'db()->exec("UPDATE projects SET end_date=\'{end_date}\' WHERE id={project}");')

        # Database failure after moving two files must leave neither rows nor physical files.
        paths_before = set(directory.glob('*'))
        php('db()->exec("CREATE TRIGGER ulat19_fail_file BEFORE INSERT ON project_files FOR EACH ROW BEGIN IF NEW.original_name = \'ulat19-fail.txt\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Simulated failure\'; END IF; END");')
        try:
            _, body, _ = upload(student, files=[(marker+'first.txt', b'Primera entrega\n', 'text/plain'), (marker+'fail.txt', b'Segunda entrega\n', 'text/plain')])
            require(count() == accepted and set(directory.glob('*')) == paths_before and 'No se pudo registrar' in body, 'ULAT-19: failed batch rolls back database and physical files')
        finally:
            php('db()->exec("DROP TRIGGER ulat19_fail_file");')
        require(upload(teacher, path=files_page, task_id=0)[0] == 200 and count() == accepted+1, 'ULAT-19: teacher still uploads general resources')
        require(upload(admin)[0] == 200 and count() == accepted+2, 'ULAT-19: administrator still uploads task attachments')
        require(upload(student, files=[(marker+'one.txt', b'Primera entrega\n', 'text/plain'), (marker+'two.txt', b'Segunda entrega\n', 'text/plain')])[0] == 200 and count() == accepted+4,
                'ULAT-19: multiple valid files saved together')
    finally:
        php(f'db()->exec("DELETE FROM project_files WHERE project_id={project} AND original_name LIKE \'ulat19-%\'");')
        for path in set(directory.glob('*')) - initial_paths:
            path.unlink()
    require(count() == before, 'ULAT-19: upload fixtures cleaned')
