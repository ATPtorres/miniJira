"""Read-only class selector checks against the standard demo dataset."""
import os
import re
import subprocess
import tempfile
from playwright.sync_api import sync_playwright, expect

BASE = os.environ.get('UI_TEST_URL', 'http://127.0.0.1:8080/').rstrip('/') + '/'
with sync_playwright() as pw:
    browser = pw.chromium.launch(executable_path=os.environ.get('CHROMIUM_BIN', '/usr/bin/chromium'),
                                headless=True, args=['--no-sandbox'])

    def login(name):
        context = browser.new_context(viewport={'width': 1280, 'height': 900}, reduced_motion='reduce')
        page = context.new_page()
        page.goto(BASE + 'login.php')
        page.fill('#email', name + '@demo.minijira.test')
        page.fill('#password', 'Clave.2026!')
        page.click('button[type=submit]')
        page.wait_for_url('**/dashboard.php')
        page.goto(BASE + 'projects/reports.php')
        return context, page

    def options(page):
        return page.locator('select[name="classroom_id"] option[value]:not([value=""])').evaluate_all(
            'rows=>rows.map(e=>({id:e.value,label:e.textContent.trim()}))')

    teacher_context, teacher = login('ana')
    expect(teacher.locator('input[name="classroom_id"]')).to_have_count(0)
    selector = teacher.get_by_role('combobox', name='Clase', exact=True)
    choices = options(teacher)
    assert len(choices) == 2
    assert all('Programación I' in item['label'] and 'Ana Rojas' in item['label'] for item in choices)
    a = next(item for item in choices if 'Grupo A' in item['label'])
    b = next(item for item in choices if 'Grupo B' in item['label'])
    selector.select_option(a['id'])
    teacher.get_by_role('button', name='Aplicar filtros').click()
    expect(selector).to_have_value(a['id'])
    expect(teacher.locator('.report-content')).to_contain_text('1 proyectos · 1 / 4 tareas terminadas')
    expect(teacher.locator('.report-content')).to_contain_text('Clase: ' + a['label'])
    assert len(options(teacher)) == 2  # Selecting one class must not remove the alternative.
    pdf_url = teacher.get_by_role('link', name='Descargar PDF', exact=True).get_attribute('href')
    response = teacher_context.request.get(BASE + 'projects/reports.php' + pdf_url)
    assert response.status == 200
    with tempfile.NamedTemporaryFile(suffix='.pdf') as output:
        output.write(response.body()); output.flush()
        text = re.sub(r'\s+', ' ', subprocess.check_output(['pdftotext', output.name, '-']).decode())
    assert 'Clase: ' + a['label'] in text and 'Grupo B' not in text
    selector.select_option(b['id'])
    teacher.get_by_role('button', name='Aplicar filtros').click()
    expect(selector).to_have_value(b['id'])
    expect(teacher.locator('.report-content')).to_contain_text('Clase: ' + b['label'])
    selector.select_option('')
    teacher.get_by_role('button', name='Aplicar filtros').click()
    expect(teacher.locator('.report-content')).to_contain_text('2 proyectos · 2 / 8 tareas terminadas')
    for width in [320, 390]:
        teacher.set_viewport_size({'width': width, 'height': 844})
        teacher.reload()
        teacher.locator('[data-report-filters] summary').click()
        selector.select_option(a['id'])
        teacher.get_by_role('button', name='Aplicar filtros').click()
        assert teacher.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
        expect(teacher.locator('.report-content')).to_contain_text('Clase: ' + a['label'])

    student_context, student = login('sofia')
    assert a['id'] not in [item['id'] for item in options(student)]
    assert b['id'] in [item['id'] for item in options(student)]
    student.goto(BASE + 'projects/reports.php?classroom_id=' + a['id'])
    expect(student.locator('.report-content')).to_contain_text('No hay resultados')
    expect(student.locator('.report-content')).to_contain_text('Clase no disponible con estos filtros')
    assert a['label'] not in student.locator('body').inner_text()
    assert student_context.request.get(BASE + 'projects/reports.php?classroom_id=abc').status == 400

    _, multiple = login('alejandro')
    multiple.goto(BASE + 'projects/reports.php?career=Administraci%C3%B3n')
    assert len(options(multiple)) == 1
    assert 'Gestión de proyectos' in options(multiple)[0]['label']

    _, admin = login('admin')
    archived = next(item for item in options(admin) if '(inactiva)' in item['label'])
    assert archived['id'] not in [item['id'] for item in options(student)]
    admin.get_by_role('combobox', name='Clase', exact=True).select_option(archived['id'])
    admin.get_by_role('button', name='Aplicar filtros').click()
    expect(admin.locator('.report-content')).to_contain_text('Clase: ' + archived['label'])
    browser.close()
    print('OK: named classes, groups, contextual filters, mobile, PDF and access isolation.')
