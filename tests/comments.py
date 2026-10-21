"""ULAT-23/24: threads, inherited privacy and transactional comment attachments."""
import base64
import json
import urllib.error
import urllib.request
import uuid


def run_comment_checks(base, fx, student, teacher, admin, observer, outsider, php, require, root):
    project, task = fx['project'], fx['task']
    dashboard = f'projects/dashboard.php?id={project}'
    task_page = f'projects/task.php?project_id={project}&task_id={task}'
    directory = root / 'storage/uploads/projects' / str(project)
    initial_paths = set(directory.glob('*'))

    def query(sql):
        encoded = base64.b64encode(sql.encode()).decode()
        return json.loads(php(f'echo json_encode(db()->query(base64_decode("{encoded}"))->fetchAll());'))

    def records():
        return query(f"SELECT * FROM project_comments WHERE project_id={project} AND body LIKE 'ULAT2324%' ORDER BY id")

    def active_files():
        return query(f"SELECT f.* FROM project_files f JOIN project_comments c ON c.id=f.comment_id WHERE c.project_id={project} AND c.body LIKE 'ULAT2324%' AND f.deleted_at IS NULL ORDER BY f.id")

    def last():
        return records()[-1]

    def post(client, path=dashboard, body='ULAT2324 comentario', parent=0, attachments=None, csrf=True, **fields):
        boundary = uuid.uuid4().hex
        values = {'action': 'create-task-comment' if path == task_page else 'create-comment',
                  'body': body, 'parent_id': parent, **fields}
        if csrf:
            values['csrf_token'] = client.token(path)
        data = bytearray()
        for key, value in values.items():
            data.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="{key}"\r\n\r\n{value}\r\n'.encode())
        for name, content, mime in (attachments or []):
            data.extend(f'--{boundary}\r\nContent-Disposition: form-data; name="files[]"; filename="{name}"\r\nContent-Type: {mime}\r\n\r\n'.encode())
            data.extend(content)
            data.extend(b'\r\n')
        data.extend(f'--{boundary}--\r\n'.encode())
        request = urllib.request.Request(base+path, data=bytes(data), headers={'Content-Type': 'multipart/form-data; boundary='+boundary})
        try:
            response = client.o.open(request, timeout=20)
        except urllib.error.HTTPError as exc:
            response = exc
        with response:
            return response.code, response.read().decode()

    text_file = [('ulat2324-informe.txt', b'Informe de comentario\n', 'text/plain')]
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jP1sAAAAASUVORK5CYII=')
    media = [('ulat2324-imagen.png', png, 'image/png'), ('ulat2324-audio.wav', b'RIFF'+(40).to_bytes(4,'little')+b'WAVEfmt '+(16).to_bytes(4,'little')+b'\x01\x00\x01\x00'+(8000).to_bytes(4,'little')+(8000).to_bytes(4,'little')+b'\x01\x00\x08\x00data'+(4).to_bytes(4,'little')+b'\x80'*4, 'audio/wav')]
    try:
        code, body = post(student, body='ULAT2324 hilo público <script>alert(1)</script>', attachments=text_file)
        public = int(last()['id'])
        require(code == 200 and 'id="hilo-'+str(public)+'"' in body and '&lt;script&gt;' in body and '<script>alert(1)</script>' not in body, 'ULAT-23: student opens escaped project thread')
        post(teacher, body='ULAT2324 respuesta docente', parent=public, attachments=media)
        reply = last()
        require(int(reply['parent_id']) == public and len(active_files()) == 3, 'ULAT-23/24: teacher replies with image and audio attachments')
        require('ULAT2324 respuesta docente' in observer.req(dashboard)[1] and 'ulat2324-imagen.png' in student.req(dashboard)[1], 'ULAT-23/24: project members see grouped replies and attachments')
        with student.raw(f'projects/download.php?file_id={active_files()[1]["id"]}') as response:
            require(response.code == 200 and response.read() == png, 'ULAT-24: attachment downloads retain exact binary contents')
        post(student, body='ULAT2324 privado anónimo', visibility='private', is_anonymous=1, attachments=text_file)
        private = int(last()['id'])
        post(teacher, body='ULAT2324 respuesta privada docente', parent=private, visibility='project', attachments=text_file)
        private_reply = last()
        private_file = active_files()[-1]
        require(private_reply['visibility'] == 'private' and 'ULAT2324 respuesta privada docente' in student.req(dashboard)[1], 'ULAT-23: teacher private reply remains visible to thread owner')
        require('ULAT2324 respuesta privada docente' not in observer.req(dashboard)[1] and observer.req(f'projects/download.php?file_id={private_file["id"]}')[0] == 403, 'ULAT-23/24: forged public visibility cannot expose private reply or its attachment')
        require(any('ULAT2324 respuesta privada docente' in n['message'] for n in student.feed()['items']) and not any('ULAT2324 respuesta privada docente' in n['message'] for n in observer.feed()['items']), 'ULAT-23: private reply notifies only its participants')
        post(student, body='ULAT2324 continuación anónima', parent=private)
        require(int(last()['is_anonymous']) == 1 and any('anonimo' in n['message'] for n in teacher.feed()['items'] if 'ULAT2324 continuación' in n['message']), 'ULAT-23: anonymous author keeps anonymity when replying')
        old_count = len(records())
        post(observer, body='ULAT2324 intento privado', parent=private, attachments=text_file)
        post(student, body='ULAT2324 respuesta de tercer nivel', parent=int(reply['id']))
        post(student, body='ULAT2324 sin csrf', csrf=False, attachments=text_file)
        require(len(records()) == old_count, 'ULAT-23: private parent, third level and missing CSRF rejected')
        require(post(outsider, body='ULAT2324 intruso', csrf=False)[0] == 403, 'ULAT-23/24: outsider cannot comment or attach files')

        post(teacher, task_page, body='ULAT2324 hilo tarea', attachments=text_file)
        task_root = int(last()['id'])
        post(student, task_page, body='ULAT2324 respuesta tarea', parent=task_root, attachments=media)
        task_reply = int(last()['id'])
        require(last()['task_id'] == task and len([f for f in active_files() if f['comment_id'] == task_reply]) == 2, 'ULAT-24: task replies save attachments with correct task and comment')
        require('ulat2324-audio.wav' in teacher.req(task_page)[1] and 'ULAT2324 respuesta tarea' in teacher.req(task_page)[1], 'ULAT-24: task thread displays downloadable attachments')
        before = len(records())
        post(student, task_page, body='ULAT2324 padre proyecto en tarea', parent=public, attachments=text_file)
        post(student, body='ULAT2324 padre tarea en proyecto', parent=task_root, attachments=text_file)
        foreign_project = int(query(f'SELECT id FROM projects WHERE id<>{project} AND deleted_at IS NULL LIMIT 1')[0]['id'])
        foreign = int(php(f'echo ProjectComment::create(["project_id"=>{foreign_project},"author_id"=>{fx["teacher"]},"body"=>"foreign thread","visibility"=>"project"]);'))
        try:
            post(student, body='ULAT2324 padre de otro proyecto', parent=foreign, attachments=text_file)
        finally:
            php(f'db()->exec("DELETE FROM project_comments WHERE id={foreign}");')
        require(len(records()) == before, 'ULAT-23: project, task and foreign parent IDs cannot be mixed')
        for attachments, message in [
            ([('ulat2324-bad.php', b'Invalid attachment', 'text/plain')], 'forbidden extension'),
            ([('ulat2324-binary.txt', b'\x00\x01'*200, 'text/plain')], 'forged MIME'),
            ([('ulat2324-large.txt', b'x'*(10*1024*1024+1), 'text/plain')], 'over 10 MB'),
            (text_file+[('ulat2324-bad.php', b'Invalid attachment', 'text/plain')], 'invalid mixed batch'),
        ]:
            _, body = post(student, task_page, body='ULAT2324 inválido', parent=task_root, attachments=attachments)
            require(len(records()) == before and 'alert-error' in body, 'ULAT-24: rejects '+message+' without creating comment')
        quota_file = active_files()[0]
        php(f'db()->exec("UPDATE project_files SET file_size=52428800 WHERE id={quota_file["id"]}");')
        try:
            _, body = post(student, body='ULAT2324 exceso cupo', parent=public, attachments=text_file)
            require(len(records()) == before and '50 MB' in body, 'ULAT-24: comment attachments share project quota')
        finally:
            php(f'db()->exec("UPDATE project_files SET file_size={quota_file["file_size"]} WHERE id={quota_file["id"]}");')
        paths_before = set(directory.glob('*'))
        notifications_before = query('SELECT COUNT(*) AS n FROM notifications')[0]['n']
        php('db()->exec("CREATE TRIGGER ulat2324_fail_file BEFORE INSERT ON project_files FOR EACH ROW BEGIN IF NEW.original_name = \'ulat2324-fail.txt\' THEN SIGNAL SQLSTATE \'45000\' SET MESSAGE_TEXT=\'Simulated failure\'; END IF; END");')
        try:
            post(teacher, body='ULAT2324 fallo atómico', parent=public, attachments=text_file+[('ulat2324-fail.txt', b'Fail\n', 'text/plain')])
            require(len(records()) == before and set(directory.glob('*')) == paths_before and query('SELECT COUNT(*) AS n FROM notifications')[0]['n'] == notifications_before, 'ULAT-24: failed save rolls back comment, all files and notifications')
        finally:
            php('db()->exec("DROP TRIGGER ulat2324_fail_file");')
        require(observer.post(dashboard, {'action':'delete-comment','comment_id':public})[0] == 403, 'ULAT-23: member cannot delete another author thread')
        reply_files = [f for f in active_files() if f['comment_id'] == task_reply]
        student.post(task_page, {'action':'delete-task-comment','comment_id':task_reply})
        require(all(teacher.req(f'projects/download.php?file_id={f["id"]}')[0] == 404 and not (directory/f['stored_name']).exists() for f in reply_files), 'ULAT-24: deleting own reply revokes and cleans attachments')
        for parent, client, path, action in [(public, student, dashboard, 'delete-comment'), (private, student, dashboard, 'delete-comment'), (task_root, teacher, task_page, 'delete-task-comment')]:
            related = query(f'SELECT f.* FROM project_files f JOIN project_comments c ON c.id=f.comment_id WHERE c.id={parent} OR c.parent_id={parent}')
            client.post(path, {'action':action,'comment_id':parent})
            require(all(teacher.req(f'projects/download.php?file_id={f["id"]}')[0] == 404 and not (directory/f['stored_name']).exists() for f in related), 'ULAT-23/24: deleting root removes descendant attachments '+str(parent))
            require(post(student, path, body='ULAT2324 responder eliminado', parent=parent, attachments=text_file)[0] == 200 and len(records()) == before, 'ULAT-23: deleted root cannot receive replies')
        require(not active_files(), 'ULAT-24: deleted comments release project quota')
    finally:
        php(f'db()->exec("DELETE FROM project_comments WHERE project_id={project} AND body LIKE \'ULAT2324%\'");')
        for path in set(directory.glob('*')) - initial_paths:
            path.unlink()
