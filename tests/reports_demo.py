# Read-only integration checks against an unchanged database/populate_demo.php dataset.
import urllib.request,urllib.parse,http.cookiejar,re,subprocess
import os
base=os.environ.get('REPORT_TEST_URL','http://127.0.0.1:8080/').rstrip('/')+'/'
def client(name):
 o=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
 h=o.open(base+'login.php').read().decode();token=re.search(r'name="csrf_token" value="([^"]+)"',h)[1]
 o.open(base+'login.php',urllib.parse.urlencode(dict(csrf_token=token,email=name+'@demo.minijira.test',password='Clave.2026!')).encode()).read()
 return o
ana=client('ana');a=ana.open(base+'projects/reports.php').read().decode()
assert 'Programación I' in a and 'Contabilidad I' not in a
assert '2 proyectos · 2 / 8 tareas terminadas · Avance: 25%' in a
student=client('alejandro');h=student.open(base+'projects/reports.php?student_id=99999').read().decode()
assert 'Alejandro Torres' in h and 'José Chambi' not in h and 'Camila Pérez' not in h
assert '3 proyectos' in h
pdf=ana.open(base+'projects/reports.php?format=pdf').read();assert pdf.startswith(b'%PDF-')
open('/tmp/consolidado.pdf','wb').write(pdf)
text=subprocess.check_output(['pdftotext','/tmp/consolidado.pdf','-']).decode()
assert 'Desempeño individual' in text and 'Contabilidad' not in text
assert 'No hay resultados' in ana.open(base+'projects/reports.php?classroom_id=999999').read().decode()
try: ana.open(base+'projects/reports.php?student_id=abc');raise AssertionError('expected 400')
except urllib.error.HTTPError as e: assert e.code==400
print('OK: reportes HTML/PDF, aislamiento docente/estudiante, filtro vacío y validación.')
