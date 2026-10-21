"""HTTP, SSE, browser and real scheduler integration. Uses only *_test database fixtures."""
import os, json, time, re, subprocess, signal, tempfile, pathlib, shutil, urllib.request as ur, urllib.parse as up, urllib.error as ue, http.cookiejar, contextlib
from uploads import run_upload_checks
ROOT=pathlib.Path(__file__).resolve().parents[1]
PHP=os.environ.get('PHP_BIN','php')
ENV=os.environ.copy()
if not re.fullmatch(r'[A-Za-z0-9_]+_test',ENV.get('SISTEMA_DB_DATABASE','')): raise SystemExit('Set SISTEMA_DB_DATABASE to a dedicated *_test database')
FX=json.loads((pathlib.Path(tempfile.gettempdir())/'minijira-beta-fixtures.json').read_text())
BASE='http://127.0.0.1:8081/'
ENV['SISTEMA_APP_URL']=BASE.rstrip('/')
def php(code): return subprocess.check_output([PHP,'-r','require "app/bootstrap.php"; '+code],cwd=ROOT,env=ENV,text=True)
class Client:
    def __init__(self): self.o=ur.build_opener(ur.ProxyHandler({}),ur.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def raw(self,path,data=None):
        req=ur.Request(BASE+path,data=up.urlencode(data,doseq=True).encode() if data is not None else None)
        try: return self.o.open(req,timeout=12)
        except ue.HTTPError as exc: return exc
    def req(self,path,data=None):
        with self.raw(path,data) as r: return r.code,r.read().decode(),r.url
    def token(self,path):
        status,body,_=self.req(path); assert status==200,(path,status)
        return re.search(r'name="csrf_token" value="([^"]+)"',body)[1]
    def login(self,role):
        code,_,url=self.req('login.php',{'csrf_token':self.token('login.php'),'email':role+'@beta.test','password':'Beta.Test2026!'})
        assert code==200 and 'dashboard.php' in url,(role,code,url)
    def feed(self):
        code,body,_=self.req('api/notifications.php'); assert code==200; return json.loads(body)
    def post(self,path,data):
        data['csrf_token']=self.token(path)
        return self.req(path,data)

def require(ok,message):
    if not ok: raise AssertionError(message)
    print('PASS '+message,flush=True)

def stream_event(response,wanted):
    name='';deadline=time.monotonic()+10
    while time.monotonic()<deadline:
        line=response.readline().decode().strip()
        if not line: continue
        if line.startswith('event:'): name=line.split(':',1)[1].strip()
        if line.startswith('data:') and name==wanted: return json.loads(line.split(':',1)[1])
    raise AssertionError('Missing SSE event '+wanted)

log=open(pathlib.Path(tempfile.gettempdir())/'minijira-beta-http.log','w')
env=ENV.copy();env['PHP_CLI_SERVER_WORKERS']='4'
server=subprocess.Popen([PHP,'-d','upload_max_filesize=12M','-d','post_max_size=55M','-S','127.0.0.1:8081','-t','public','scripts/dev-router.php'],cwd=ROOT,env=env,stdout=log,stderr=log,start_new_session=True)
try:
    anon=Client()
    for _ in range(60):
        try:
            if anon.req('login.php')[0]==200: break
        except OSError: time.sleep(.1)
    else: raise AssertionError('Test server did not start')
    student=Client();teacher=Client();admin=Client();outsider=Client();observer=Client()
    for c,role in [(student,'estudiante'),(teacher,'docente'),(admin,'admin'),(outsider,'outsider'),(observer,'observer')]:c.login(role)
    require(anon.req('api/notifications.php')[0]==401,'anonymous API rejected')
    require(json.loads(outsider.req('api/project-metrics.php?summary=1')[1])['metrics']['total']==0,'analytics summary excludes other users projects')
    require(student.req('api/notifications.php',{'action':'mark-all-read'})[0]==419,'notification mutations require CSRF')
    project=FX['project']; task=FX['task']
    run_upload_checks(BASE, FX, student, teacher, admin, observer, outsider, php, require, ROOT)
    for path in [f'api/project-metrics.php?id={project}',f'projects/analytics.php?id={project}',f'projects/report.php?id={project}']:
        require(outsider.req(path)[0]==403,'outsider denied '+path.split('?')[0])
    require(student.req('api/project-metrics.php?id='+str(project)+'&from=2026-02-31')[0]==400,'invalid date filter rejected')
    board=f'projects/board.php?id={project}'
    code,body,_=teacher.post(board,{'action':'update-task-status','task_id':task,'status':'done','ajax':'1'})
    require(code==200 and json.loads(body)['ok'],'teacher moves task to done')
    require(json.loads(student.req(f'api/project-metrics.php?id={project}')[1])['metrics']['progress']==50,'metrics API reports 50 percent after task change')
    code,body,_=student.req('api/notifications.php?user_id='+str(FX['teacher']))
    require(code==200 and all(int(n['user_id'])==FX['student'] for n in json.loads(body)['items']),'feed cannot select another user')
    # The open SSE stream must not hold the PHP session lock.
    stream=student.raw('api/notifications.php?mode=stream')
    try:
        initial=stream_event(stream,'notifications')
        dashboard=f'projects/dashboard.php?id={project}'
        teacher.post(dashboard,{'action':'create-comment','body':'Comentario vivo Beta <script>alert(1)</script>','visibility':'project'})
        newer=stream_event(stream,'notifications')
        require(newer['version']!=initial['version'] and any('Comentario vivo Beta' in n['message'] for n in newer['items']),'SSE delivers a newly posted comment without reload')
        require(student.req('api/notifications.php',{'action':'mark-all-read','csrf_token':student.token('user/dashboard.php')})[0]==200,'open SSE does not block session submissions')
        started=time.monotonic(); student.post('logout.php',{})
        stream_event(stream,'expired')
        require(time.monotonic()-started<6,'SSE stops after logout')
    finally: stream.close()
    student.login('estudiante')
    teacher.feed()
    student.post(f'projects/dashboard.php?id={project}',{'action':'create-comment','body':'SECRETO_PRIVADO_BETA','visibility':'private','is_anonymous':'1'})
    notes=teacher.feed()['items']
    private=next(n for n in notes if 'SECRETO_PRIVADO_BETA' in n['message'])
    require('anonimo' in private['message'] and 'Estudiante Beta' not in private['message'],'anonymous private comment notifies teacher without author name')
    require('SECRETO_PRIVADO_BETA' not in observer.req(f'projects/dashboard.php?id={project}')[1] and all('SECRETO_PRIVADO_BETA' not in n['message'] for n in observer.feed()['items']),'private comments hidden from other project members and their feed')
    require('SECRETO_PRIVADO_BETA' not in outsider.req(f'projects/dashboard.php?id={project}')[1],'private comment unavailable to outsider')
    code,body,_=student.req(f'projects/dashboard.php?id={project}')
    require(code==200 and '&lt;script&gt;' in body and '<script>alert(1)</script>' not in body,'comment HTML escaped')
    calendar=f'projects/calendar.php?id={project}'
    date=php('echo date("Y-m-d",strtotime("+2 days"));').strip()
    teacher.post(calendar,{'action':'create-event','title':'Evento HTTP Beta','description':'Prueba del calendario','event_date':date,'event_time':'10:00','event_type':'milestone'})
    event=int(php(f'echo db()->query("SELECT MAX(id) FROM project_events WHERE project_id={project}")->fetchColumn();'))
    require(any(n['type']=='event_created' for n in student.feed()['items']),'event creation sends notification')
    teacher.post(calendar,{'action':'update-event','event_id':event,'title':'Evento HTTP Beta editado','description':'Cambio de prueba','event_date':date,'event_time':'11:00','event_type':'milestone'})
    require(any(n['type']=='event_updated' for n in student.feed()['items']),'event update sends notification')
    teacher.post(calendar,{'action':'delete-event','event_id':event})
    require(any(n['type']=='event_deleted' for n in student.feed()['items']),'event cancellation sends notification')
    for path in ['admin/dashboard.php','projects/analytics.php',f'projects/analytics.php?id={project}']:
        code,body,_=admin.req(path)
        require(code==200 and 'href="#"' not in body,'functional dashboard '+path)
    with student.raw(f'projects/report.php?id={project}') as r:
        pdf=r.read();require(r.code==200 and pdf.startswith(b'%PDF-') and 'attachment' in r.headers['Content-Disposition'],'PDF download has correct content and headers')
    with tempfile.TemporaryDirectory() as tmp:
        filename=pathlib.Path(tmp)/'report.pdf';filename.write_bytes(pdf)
        text=subprocess.check_output(['pdftotext',str(filename),'-'],text=True)
        require('Avance: 50%' in text and 'Álvaro Beta' in text,'PDF matches dashboard and renders accented names')
        require('SECRETO_PRIVADO_BETA' not in text,'PDF excludes private comments')
    # Browser checks use the automatic polling fallback on PHP's dev server.
    from playwright.sync_api import sync_playwright
    with sync_playwright() as pw:
        browser=pw.chromium.launch(headless=True,executable_path=os.environ.get('CHROMIUM_BIN') or shutil.which('chromium'),args=['--no-sandbox'])
        page=browser.new_page();errors=[];page.on('pageerror',lambda exc:errors.append(str(exc)))
        page.goto(BASE+'login.php');page.fill('#email','estudiante@beta.test');page.fill('#password','Beta.Test2026!');page.click('button[type=submit]');page.wait_for_url('**/user/dashboard.php')
        page.goto(BASE+f'projects/analytics.php?id={project}')
        require(page.locator('[data-live-value="progress"]').inner_text()=='50','browser renders initial project chart')
        teacher.post(board,{'action':'update-task-status','task_id':task,'status':'doing','ajax':'1'})
        page.wait_for_function('document.querySelector("[data-live-value=progress]").textContent === "0"',timeout=18000)
        require(True,'browser updates project percentage without reload')
        teacher.post(f'projects/dashboard.php?id={project}',{'action':'create-comment','body':'AVISO_BROWSER_BETA','visibility':'project'})
        page.wait_for_function('document.querySelector(".notif-dropdown-list").textContent.includes("AVISO_BROWSER_BETA")',timeout=12000)
        page.click('#notif-bell');require(page.locator('#notif-dropdown').evaluate('el => el.classList.contains("is-open")'),'bell receives live comment and opens')
        page.goto(BASE+'user/notifications.php');page.wait_for_selector('#notification-results [data-notification-action]')
        first=page.locator('#notification-results [name=action][value=mark-read]').first
        if first.count():first.locator('xpath=..').locator('button').click();page.wait_for_function('document.querySelector("#notification-feedback").textContent.includes("actualizadas")')
        require(not errors,'browser has no JavaScript errors')
        browser.close()
    # Prove actual scheduler execution, independently of any browser request.
    cron=os.environ.get('SUPERCRONIC_BIN')
    if not cron: raise AssertionError('Set SUPERCRONIC_BIN to test the actual scheduler')
    php(f'db()->exec("UPDATE project_tasks SET due_date=DATE_ADD(CURDATE(),INTERVAL 1 DAY) WHERE id={task}");')
    with tempfile.TemporaryDirectory() as tmp:
        cronfile=pathlib.Path(tmp)/'crontab';cronfile.write_text('* * * * * * * '+str(ROOT/'scripts/run-reminders.sh')+'\n')
        cronlog=pathlib.Path(tmp)/'cron.log'
        with cronlog.open('w') as out:
            worker=subprocess.Popen([cron,str(cronfile)],cwd=ROOT,env=ENV,stdout=out,stderr=out,start_new_session=True)
            try:
                for _ in range(100):
                    text=cronlog.read_text()
                    if 'Recordatorios creados: 1' in text and 'Recordatorios creados: 0' in text:break
                    time.sleep(.1)
                else:raise AssertionError('Scheduler did not produce expected deliveries: '+text)
            finally:os.killpg(worker.pid,signal.SIGTERM);worker.wait(timeout=8)
        require(True,'real cron runner sends one reminder and suppresses subsequent duplicates')
    print('HTTP/SSE/browser/scheduler integration passed',flush=True)
finally:
    os.killpg(server.pid,signal.SIGTERM);server.wait(timeout=8);log.close()
