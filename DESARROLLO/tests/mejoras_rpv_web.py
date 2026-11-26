"""Pruebas responsive de opiniones, carrusel y acceso administrativo."""
from __future__ import annotations

import contextlib
import json
import socket
import subprocess
import time
from pathlib import Path

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[1]
PHP = Path(r"C:\xampp\php\php.exe")
WEB_PORT = 8000
ADMIN_PORT = 8767


def wait_port(port: int, timeout: float = 8.0) -> None:
    deadline = time.time() + timeout
    while time.time() < deadline:
        with contextlib.closing(socket.socket()) as sock:
            if sock.connect_ex(("127.0.0.1", port)) == 0:
                return
        time.sleep(0.1)
    raise RuntimeError(f"El servidor {port} no inicio a tiempo")


def no_horizontal_overflow(page) -> None:
    values = page.evaluate("[document.documentElement.scrollWidth, document.documentElement.clientWidth]")
    assert values[0] <= values[1] + 1, f"Desborde horizontal: {values}"


def main() -> None:
    web = subprocess.Popen([str(PHP), "-S", f"127.0.0.1:{WEB_PORT}", "-t", str(ROOT / "Web")], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    admin = subprocess.Popen([str(PHP), "-S", f"127.0.0.1:{ADMIN_PORT}", "-t", str(ROOT / "Admin")], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    try:
        wait_port(WEB_PORT); wait_port(ADMIN_PORT)
        opinions = {"ok": True, "opiniones": [
            {"nombre": "Cliente Uno", "comentario": "Excelente acompanamiento durante todo el proyecto.", "calificacion": 5, "empresa": "Empresa Uno", "url_empresa": "https://empresa.example/"},
            {"nombre": "Cliente Dos", "comentario": "La orientacion fue clara y el resultado muy bueno.", "calificacion": 4, "empresa": "Empresa Dos"},
            {"nombre": "Cliente Tres", "comentario": "Buena comunicacion y respuesta a nuestras dudas.", "calificacion": 5},
        ]}
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(channel="msedge", headless=True)
            for width in (320, 375, 430, 768, 1366):
                context = browser.new_context(viewport={"width": width, "height": 900}, reduced_motion="reduce")
                context.route("**/opiniones/listado.php", lambda route: route.fulfill(status=200, content_type="application/json", body=json.dumps(opinions)))
                context.route("https://**", lambda route: route.abort())
                page = context.new_page(); errors: list[str] = []
                page.on("pageerror", lambda error: errors.append(str(error)))
                page.goto(f"http://127.0.0.1:{WEB_PORT}/", wait_until="domcontentloaded")
                assert page.locator("#launcher").count() == 1
                page.locator("#opiniones-clientes:not([hidden])").wait_for()
                assert page.locator(".customer-opinion").count() == 3
                assert page.locator(".customer-opinion__business").count() == 1
                assert page.locator(".customer-opinion__business a").count() == 1
                assert "noopener" in (page.locator(".customer-opinion__business a").get_attribute("rel") or "")
                no_horizontal_overflow(page)
                for internal_path in ("/calderas/", "/tinajas/", "/estufas-a-pellets/", "/guia-caldera/", "/opiniones/"):
                    page.goto(f"http://127.0.0.1:{WEB_PORT}{internal_path}", wait_until="domcontentloaded")
                    assert page.locator("#launcher, #chat, .launcher").count() == 0, f"ChatBot visible fuera de la portada: {internal_path}"
                    assistant_scripts = page.locator('script[src*="asistente-rpv"]')
                    assert assistant_scripts.count() == 0, f"Script del ChatBot cargado fuera de la portada: {internal_path}"
                    no_horizontal_overflow(page)
                page.goto(f"http://127.0.0.1:{WEB_PORT}/opiniones/", wait_until="domcontentloaded")
                assert page.locator("input[name=rating]").count() == 5
                assert page.locator("input[name=company_name]").count() == 1
                assert page.locator("input[name=company_url]").count() == 1
                assert page.locator("input[name=promotion_consent]").count() == 0
                assert page.locator("input[name=consent]").count() == 1
                assert page.locator("input[name=company_name]").input_value() == ""
                assert page.locator("input[name=company_url]").input_value() == ""
                assert page.locator(".app-only").first.is_visible() == (width <= 767)
                assert page.locator(".desktop-only").first.is_visible() == (width > 767)
                page.locator("input[name=company_name]").fill("Negocio de prueba")
                assert not page.locator("input[name=company_url]").evaluate("element => element.checkValidity()")
                assert "sitio web" in page.locator("input[name=company_url]").evaluate("element => element.validationMessage").lower()
                page.locator("input[name=company_url]").fill("negocio.example")
                page.locator("input[name=company_url]").blur()
                assert page.locator("input[name=company_url]").input_value() == "https://negocio.example"
                assert page.locator("#preview-business:not([hidden])").count() == 1
                assert "Enviar opinión" in page.locator(".submit-review").inner_text()
                no_horizontal_overflow(page)
                page.goto(f"http://127.0.0.1:{ADMIN_PORT}/login.php", wait_until="domcontentloaded")
                no_horizontal_overflow(page)
                assert not errors, f"Errores JavaScript a {width}px: {errors}"
                context.close()
            browser.close()
        print("OK: responsive validado en 320, 375, 430, 768 y 1366 px; carrusel, formulario y acceso sin desborde.")
    finally:
        for process in (web, admin):
            process.terminate()
            try: process.wait(timeout=5)
            except subprocess.TimeoutExpired: process.kill()


if __name__ == "__main__":
    main()
