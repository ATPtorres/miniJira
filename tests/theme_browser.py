"""Read-only theme checks. Run with the app started and the demo dataset loaded."""
import os
from playwright.sync_api import sync_playwright, expect

BASE = os.environ.get('THEME_TEST_URL', 'http://127.0.0.1:8080/').rstrip('/') + '/'

with sync_playwright() as pw:
    browser = pw.chromium.launch(
        executable_path=os.environ.get('CHROMIUM_BIN', '/usr/bin/chromium'),
        headless=True, args=['--no-sandbox'])
    context = browser.new_context(viewport={'width': 1440, 'height': 1000},
                                  reduced_motion='reduce', color_scheme='dark')
    page = context.new_page()
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(BASE + 'login.php')
    root = page.locator('html')
    toggle = page.get_by_role('button', name='Tema oscuro', exact=True)
    # Optional means light remains the default, even with a dark OS preference.
    expect(root).to_have_attribute('data-theme', 'light')
    toggle.focus()
    page.keyboard.press('Space')
    expect(root).to_have_attribute('data-theme', 'dark')
    expect(toggle).to_have_attribute('aria-pressed', 'true')
    page.reload()
    expect(root).to_have_attribute('data-theme', 'dark')
    assert page.locator('body').evaluate('e=>getComputedStyle(e).backgroundColor') == 'rgb(16, 24, 39)'
    assert page.locator('#email').evaluate('e=>getComputedStyle(e).color') == 'rgb(228, 234, 243)'

    # Preference follows navigation, and changes in one tab update the other.
    other = context.new_page()
    other.goto(BASE + 'forgot-password.php')
    expect(other.locator('html')).to_have_attribute('data-theme', 'dark')
    other.get_by_role('button', name='Tema oscuro', exact=True).click()
    expect(root).to_have_attribute('data-theme', 'light')
    other.get_by_role('button', name='Tema oscuro', exact=True).click()
    expect(root).to_have_attribute('data-theme', 'dark')
    other.close()

    page.fill('#email', os.environ.get('THEME_TEST_EMAIL', 'ana@demo.minijira.test'))
    page.fill('#password', os.environ.get('THEME_TEST_PASSWORD', 'Clave.2026!'))
    page.click('button[type=submit]')
    page.wait_for_url('**/user/dashboard.php')
    expect(root).to_have_attribute('data-theme', 'dark')
    page.locator('a[href$="projects/index.php"]').first.click()
    page.wait_for_url('**/projects/index.php')
    project_url = page.locator('a[href*="dashboard.php?id="]').first.get_attribute('href')
    project_id = project_url.split('id=')[1]
    for path in ['projects/reports.php', 'user/classes.php',
                 'projects/board.php?id=' + project_id,
                 'projects/calendar.php?id=' + project_id,
                 'projects/dashboard.php?id=' + project_id]:
        response = page.goto(BASE + path)
        assert response.status == 200, path
        expect(root).to_have_attribute('data-theme', 'dark')
    assert page.locator('.history-chart text').first.evaluate('e=>getComputedStyle(e).fill') == 'rgb(183, 196, 215)'
    page.locator('#notif-bell').click()
    expect(page.locator('#notif-dropdown')).to_be_visible()
    assert page.locator('#notif-dropdown').evaluate('e=>getComputedStyle(e).backgroundColor') == 'rgb(25, 36, 56)'
    page.locator('#notif-bell').click()
    page.get_by_role('button', name='Cerrar sesion').click()
    expect(page.locator('[data-logout-dialog]')).to_be_visible()
    assert page.locator('[data-logout-dialog]').evaluate('e=>getComputedStyle(e).backgroundColor') == 'rgb(25, 36, 56)'
    page.locator('[data-cancel-logout]').click()
    page.emulate_media(media='print')
    assert page.locator('body').evaluate('e=>getComputedStyle(e).backgroundColor') == 'rgb(244, 246, 249)'
    page.emulate_media(media='screen')
    page.set_viewport_size({'width': 390, 'height': 844})
    page.locator('[data-nav-toggle]').click()
    expect(toggle).to_be_visible()
    toggle.click()
    expect(root).to_have_attribute('data-theme', 'light')
    page.reload()
    expect(root).to_have_attribute('data-theme', 'light')

    # Disabled storage must not break the switch or other page scripts.
    blocked = browser.new_context()
    blocked.add_init_script("Object.defineProperty(window, 'localStorage', {get(){throw new DOMException('Blocked', 'SecurityError');}})")
    blocked_page = blocked.new_page()
    blocked_page.on('pageerror', lambda error: errors.append(str(error)))
    blocked_page.goto(BASE + 'login.php')
    blocked_page.get_by_role('button', name='Tema oscuro', exact=True).click()
    expect(blocked_page.locator('html')).to_have_attribute('data-theme', 'dark')
    blocked.close()
    nojs = browser.new_context(java_script_enabled=False)
    nojs_page = nojs.new_page()
    nojs_page.goto(BASE + 'login.php')
    expect(nojs_page.locator('[data-theme-toggle]')).to_be_hidden()
    assert nojs_page.locator('body').evaluate('e=>getComputedStyle(e).backgroundColor') == 'rgb(244, 246, 249)'
    nojs.close()
    assert not errors, errors
    browser.close()
    print('OK: theme switching, keyboard, persistence, tabs, pages, mobile, dialogs, print and blocked storage.')
