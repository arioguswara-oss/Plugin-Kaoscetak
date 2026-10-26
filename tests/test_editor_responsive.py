"""Responsive Chromium smoke checks. NOT installed WordPress, checkout or Google Drive validation."""
import json
import os
import shutil
import struct
import zlib
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parent.parent
PLUGIN = ROOT / "plugins" / "kaoscetak-lite-designer"

SHIRT = {
    "rest": "/wp-json/kld/v1/",
    "nonce": "preview",
    "productId": 22,
    "productName": "Cotton Combed 24s",
    "productType": "reguler-24s",
    "garments": {"S": [43, 65], "L": [49, 73]},
    "matrix": {
        "S": {"A5": 62000, "A4": 72000, "A3": 82000, "A3+": 87000},
        "L": {"A5": 68000, "A4": 78000, "A3": 88000, "A3+": 93000},
    },
    "surcharges": {"A5": 15000, "A4": 25000, "A3": 35000, "A3+": 40000},
    "tiers": {"A5": [14.8, 21], "A4": [21, 29.7], "A3": [29.7, 42], "A3+": [32, 48]},
    "sizes": ["S", "L"],
    "colors": ["Hitam", "Putih", "Army Green"],
    "colorSwatches": {"Hitam": "#242527", "Putih": "#f7f7f2", "Army Green": "#586444"},
    "previewOnly": True,
    "cartUrl": "/cart/",
    "maxBytes": 10485760,
    "maxPreviewBytes": 61440,
}

MUG = {
    **SHIRT,
    "productId": 31,
    "productName": "Mug Putih 11 oz",
    "productType": "mug-11oz",
    "matrix": {"ONE": {"MUG": 25000}},
    "garments": {},
    "surcharges": {},
    "tiers": {"MUG": [18, 8.5]},
    "sizes": ["ONE"],
    "colors": ["Putih"],
}

def sample_png():
    def chunk(tag, content):
        return struct.pack("!I", len(content)) + tag + content + struct.pack("!I", zlib.crc32(tag + content) & 0xffffffff)
    header = chunk(b"IHDR", struct.pack("!2I5B", 64, 64, 8, 2, 0, 0, 0))
    pixel_rows = (b"\x00" + b"\x75\xc0\xf2" * 64) * 64
    return b"\x89PNG\r\n\x1a\n" + header + chunk(b"IDAT", zlib.compress(pixel_rows)) + chunk(b"IEND", b"")

def open_editor(browser, viewport, config):
    page = browser.new_page(viewport=viewport, device_scale_factor=1, is_mobile=viewport["width"] <= 430, has_touch=viewport["width"] <= 430)
    errors = []
    page.on("pageerror", lambda err: errors.append(str(err)))
    page.set_content('<meta name="viewport" content="width=device-width, initial-scale=1"><h1>Halaman Produk</h1><button id="kld-open-designer">Custom Desain</button><div id="kld-editor-root"></div>')
    page.add_style_tag(content=(PLUGIN / "assets/editor.css").read_text(encoding="utf-8"))
    page.evaluate("window.KLD_CONFIG = " + json.dumps(config))
    page.add_script_tag(content=(PLUGIN / "assets/editor.js").read_text(encoding="utf-8"))
    page.locator("#kld-open-designer").click()
    expect(page.locator(".kld-overlay")).to_be_visible()
    expect(page.locator(".kld-stage")).to_be_visible()
    dims = page.locator(".kld-stage").bounding_box()
    assert dims and dims["width"] >= 100 and dims["height"] >= 100, f"Canvas too small: {dims}"
    assert dims["x"] >= -1 and dims["x"] + dims["width"] <= viewport["width"] + 2, f"Canvas clipped: {dims}, {viewport}"
    assert page.evaluate("document.documentElement.scrollWidth <= window.innerWidth + 2"), f"Unexpected document horizontal overflow: {viewport}"
    return page, errors

def verify_shirt(browser, viewport):
    page, errors = open_editor(browser, viewport, SHIRT)
    page.locator(".kld-tier").select_option("A5")
    assert page.locator(".kld-total-number").inner_text() == "Rp68.000"
    page.locator(".kld-fileinput").set_input_files({"name": "design.png", "mimeType": "image/png", "buffer": sample_png()})
    expect(page.locator(".kld-image-width")).to_be_enabled(timeout=5000)
    page.locator(".kld-text").fill("DESAIN\nDEPAN")
    canvas_front = page.locator(".kld-stage").screenshot()
    page.locator('[data-side="back"]').click()
    assert page.locator(".kld-tier").input_value() == "none"
    page.locator(".kld-tier").select_option("A3")
    assert page.locator(".kld-total-number").inner_text() == "Rp103.000"
    page.locator(".kld-text").fill("BELAKANG")
    assert page.locator(".kld-text").input_value() == "BELAKANG"
    canvas_back = page.locator(".kld-stage").screenshot()
    assert canvas_back != canvas_front, "Front and back views are identical"
    page.locator('[data-side="front"]').click()
    assert page.locator(".kld-text").input_value() == "DESAIN\nDEPAN", "Side text should be independent"
    assert page.locator(".kld-tier").input_value() == "A5", "Front print tier should be preserved"
    page.locator(".kld-swatch[data-color='Putih']").click()
    assert page.locator(".kld-color").input_value() == "Putih", "Swatch did not update selected garment color"
    page.locator(".kld-size").select_option("S")
    assert page.locator(".kld-total-number").inner_text() == "Rp103.000", "Preview size must not alter ordered quantities"
    page.locator('[data-qty="S"]').fill("2")
    assert page.locator(".kld-total-number").inner_text() == "Rp297.000", "Per-size order prices must be independent"
    page.locator(".kld-save").click()
    expect(page.locator(".kld-status")).to_contain_text("Mode preview")
    assert not errors, f"Console page errors: {errors}"
    page.close()
    return f"shirt {viewport['width']}x{viewport['height']}: PASS"

def verify_mug(browser):
    viewport = {"width": 390, "height": 844}
    page, errors = open_editor(browser, viewport, MUG)
    assert page.locator(".kld-total-number").inner_text() == "Rp25.000"
    assert page.locator("[data-side]").count() == 0, "Mug cannot have garment front/back tabs"
    assert page.locator(".kld-tier").input_value() == "MUG"
    page.locator(".kld-fileinput").set_input_files({"name": "design.png", "mimeType": "image/png", "buffer": sample_png()})
    expect(page.locator(".kld-image-width")).to_be_enabled(timeout=5000)
    page.locator(".kld-text").fill("MUG CUSTOM")
    assert page.locator(".kld-total-number").inner_text() == "Rp25.000"
    assert not errors, errors
    page.close()
    return "mug 390x844: PASS"

if __name__ == "__main__":
    path = os.environ.get("CHROMIUM_PATH") or shutil.which("chromium") or shutil.which("google-chrome") or shutil.which("google-chrome-stable")
    if path and not Path(path).exists():
        raise RuntimeError("Configured Chromium binary not found: " + path)
    with sync_playwright() as driver:
        browser = driver.chromium.launch(headless=True, executable_path=path, args=["--no-sandbox"])
        results = []
        for viewport in (
            {"width": 320, "height": 680},
            {"width": 360, "height": 740},
            {"width": 390, "height": 844},
            {"width": 768, "height": 1024},
        ):
            results.append(verify_shirt(browser, viewport))
        results.append(verify_mug(browser))
        print(json.dumps({"test": "responsive frontend editor isolated from WordPress", "checks": results}, ensure_ascii=False))
        browser.close()
