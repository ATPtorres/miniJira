"""Browser regression checks against database/populate_demo.php. No academic data is changed."""
import os
from playwright.sync_api import sync_playwright, expect

BASE = os.environ.get('UI_TEST_URL', 'http://127.0.0.1:8080/').rstrip('/') + '/'
with sync_playwright() as pw:
    browser = pw.chromium.launch(executable_path=os.environ.get('CHROMIUM_BIN', '/usr/bin/chromium'),
                                headless=True, args=['--no-sandbox'])
    context = browser.new_context(viewport={'width': 1280, 'height': 900}, reduced_motion='reduce')
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    for path, title in [('', 'Tus tareas, entregas y evaluaciones en un solo lugar'),
                        ('help.php', 'Ayuda y soporte'), ('privacy.php', 'Privacidad y uso de la información')]:
        response = page.goto(BASE + path)
        assert response.status == 200
        expect(page.get_by_role('heading', level=1)).to_have_text(title)
        expect(page.get_by_role('navigation', name='Ayuda e información')).to_be_visible()
    # The skip link moves focus, rather than only scrolling.
    page.goto(BASE + 'help.php')
    page.keyboard.press('Tab')
    expect(page.locator('[data-skip-link]')).to_be_focused()
    page.keyboard.press('Enter')
    expect(page.locator('main')).to_be_focused()
    page.goto(BASE + 'login.php')
    page.fill('#email', 'ana@demo.minijira.test')
    page.fill('#password', 'Clave.2026!')
    page.click('button[type=submit]')
    page.wait_for_url('**/user/dashboard.php')
    page.goto(BASE + 'projects/reports.php')
    tables = page.get_by_role('table')
    expect(tables).to_have_count(2)
    expect(tables.nth(1).get_by_role('columnheader')).to_have_count(8)
    original = tables.nth(1).locator('tbody').text_content()
    # Long names/context and all metrics remain readable without page overflow.
    for dark in [False, True]:
        if dark:
            page.locator('[data-theme-toggle]').click()
        for width in [320, 390, 700, 768, 1280]:
            page.set_viewport_size({'width': width, 'height': 900})
            assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1'), (dark, width)
            assert tables.nth(1).locator('tbody').text_content() == original
            first_cell = tables.nth(1).locator('tbody td').first
            label = first_cell.locator('.report-cell-label')
            if width <= 700:
                expect(label).to_be_visible()
                assert first_cell.bounding_box()['width'] <= width / 2
            else:
                expect(label).to_be_hidden()
        page.emulate_media(media='print')
        assert tables.nth(1).evaluate('e=>getComputedStyle(e).display') == 'table'
        expect(page.locator('.report-cell-label').first).to_be_hidden()
        page.emulate_media(media='screen')
    # Stress wrapping without modifying stored project or student information.
    page.set_viewport_size({'width': 320, 'height': 900})
    tables.nth(0).locator('tbody th').first.evaluate("e=>e.textContent='ProyectoUniversitarioConUnNombreExtenso'.repeat(8)")
    assert page.evaluate('document.documentElement.scrollWidth <= window.innerWidth + 1')
    page.set_viewport_size({'width': 1280, 'height': 900})
    page.goto(BASE + 'projects/index.php')
    url = page.locator('a[href*="dashboard.php?id="]').first.get_attribute('href')
    project_id = url.split('id=')[1]
    page.goto(BASE + 'projects/calendar.php?id=' + project_id)
    create = page.locator('.calendar-create').first
    create.focus()
    page.keyboard.press('Enter')
    dialog = page.get_by_role('dialog', name='Nuevo evento')
    expect(dialog).to_be_visible()
    expect(page.locator('#event-title-input')).to_be_focused()
    expect(page.locator('#event-modal-delete')).to_be_hidden()
    for key in ['Shift+Tab'] * 3 + ['Tab'] * 12:
        page.keyboard.press(key)
        assert dialog.evaluate('e=>e.contains(document.activeElement)'), key
    page.keyboard.press('Escape')
    expect(dialog).to_be_hidden()
    expect(create).to_be_focused()
    event = page.locator('button.event-dot[data-event-id]').first
    event.focus()
    page.keyboard.press('Space')
    expect(page.get_by_role('dialog', name='Editar evento')).to_be_visible()
    page.get_by_role('button', name='Cancelar', exact=True).click()
    expect(event).to_be_focused()
    # Escape closes the innermost disclosure before closing the mobile menu.
    page.set_viewport_size({'width': 390, 'height': 844})
    menu = page.locator('[data-nav-toggle]')
    menu.click()
    bell = page.locator('#notif-bell')
    bell.focus()
    page.keyboard.press('Enter')
    expect(bell).to_have_attribute('aria-expanded', 'true')
    page.keyboard.press('Tab')
    page.keyboard.press('Escape')
    expect(bell).to_be_focused()
    expect(bell).to_have_attribute('aria-expanded', 'false')
    expect(menu).to_have_attribute('aria-expanded', 'true')
    page.keyboard.press('Escape')
    expect(menu).to_be_focused()
    expect(menu).to_have_attribute('aria-expanded', 'false')
    page.goto(BASE + 'projects/reports.php')
    assert not page.locator('[data-report-filters]').evaluate('e=>e.open')
    page.locator('[data-report-filters] summary').focus()
    page.keyboard.press('Enter')
    assert page.locator('[data-report-filters]').evaluate('e=>e.open')
    # Report downloads preserve PDF presentation and exclude mobile-only labels.
    page.goto(BASE + 'projects/reports.php')
    with page.expect_download() as pending:
        page.get_by_role('link', name='Descargar PDF', exact=True).click()
    download = pending.value
    with open(download.path(), 'rb') as report:
        assert report.read(5) == b'%PDF-'
    assert not errors, errors
    browser.close()
    print('OK: public help/privacy, keyboard, mobile reports, dark theme, print and PDF.')
