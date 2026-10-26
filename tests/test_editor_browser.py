"""Smoke test on Chromium for the frontend JS only; not WordPress/WooCommerce integration."""
from pathlib import Path
from playwright.sync_api import sync_playwright
import json
import zlib
import struct
import os

root = Path(__file__).resolve().parent.parent
plugin = root / 'plugins' / 'kaoscetak-lite-designer'
config = {
    'rest': '/wp-json/kld/v1/', 'nonce': 'preview', 'productId': 22,
    'productName': 'Cotton Combed 24s',
    'matrix': {'S': {'A5': 62000, 'A4': 72000, 'A3': 82000, 'A3+': 87000},
               'L': {'A5': 68000, 'A4': 78000, 'A3': 88000, 'A3+': 93000}},
    'surcharges': {'A5': 15000, 'A4': 25000, 'A3': 35000, 'A3+': 40000},
    'tiers': {'A5': [14.8, 21], 'A4': [21, 29.7], 'A3': [29.7, 42], 'A3+': [32, 48]},
    'sizes': ['S', 'L'], 'colors': ['Hitam', 'Putih', 'Army Green'],
    'previewOnly': True, 'cartUrl': '/cart/', 'maxBytes': 10485760, 'maxPreviewBytes': 61440,
}

def sample_png():
    # A valid 64×64 RGB PNG; built using the Python standard library.
    def chunk(code, data):
        return struct.pack('!I', len(data)) + code + data + struct.pack('!I', zlib.crc32(code + data) & 0xffffffff)
    header = chunk(b'IHDR', struct.pack('!2I5B', 64, 64, 8, 2, 0, 0, 0))
    rows = (b'\x00' + b'\x75\xc0\xf2' * 64) * 64
    return b'\x89PNG\r\n\x1a\n' + header + chunk(b'IDAT', zlib.compress(rows)) + chunk(b'IEND', b'')

with sync_playwright() as p:
    browser = p.chromium.launch(headless=True, executable_path=os.environ.get('CHROMIUM_PATH', '/usr/bin/chromium'), args=['--no-sandbox'])
    page = browser.new_page(viewport={'width': 390, 'height': 844}, device_scale_factor=1)
    errors = []
    page.on('pageerror', lambda err: errors.append(str(err)))
    page.set_content('<h1>Halaman Produk</h1><button id="kld-open-designer">Custom Desain Kaos</button><div id="kld-editor-root"></div>')
    page.add_style_tag(content=(plugin / 'assets/editor.css').read_text())
    page.evaluate('window.KLD_CONFIG = ' + json.dumps(config))
    page.add_script_tag(content=(plugin / 'assets/editor.js').read_text())
    page.locator('#kld-open-designer').click()
    assert page.locator('.kld-overlay').is_visible(), 'Fullscreen editor not visible'
    page.locator('.kld-tier').select_option('A5')
    page.locator('.kld-fileinput').set_input_files({'name':'design.png','mimeType':'image/png','buffer':sample_png()})
    page.wait_for_timeout(250)
    assert page.locator('.kld-image-width').is_enabled(), 'PNG image not rendered'
    assert page.locator('.kld-total-number').inner_text() == 'Rp68.000'
    page.locator('[data-side="back"]').click()
    page.locator('.kld-tier').select_option('A3')
    assert page.locator('.kld-total-number').inner_text() == 'Rp103.000'
    page.locator('.kld-text').fill('CUSTOM\nKAOSCETAK')
    assert not errors, errors
    print(json.dumps({'browser': 'PASS', 'preview_image': 'PASS', 'price': 'Rp103.000',
                      'multiline_text': 'PASS', 'page_errors': errors}))
    browser.close()