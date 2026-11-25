"""Prueba integral del asistente contextual en portada y calderas."""
from __future__ import annotations

import contextlib
import socket
import subprocess
import time
from pathlib import Path

from playwright.sync_api import sync_playwright


ROOT = Path(__file__).resolve().parents[1]
WEB = ROOT / "Web"
PHP = Path(r"C:\xampp\php\php.exe")
PORT = 8765
BASE = f"http://127.0.0.1:{PORT}"


def wait_server(timeout: float = 8.0) -> None:
    deadline = time.time() + timeout
    while time.time() < deadline:
        with contextlib.closing(socket.socket()) as sock:
            if sock.connect_ex(("127.0.0.1", PORT)) == 0:
                return
        time.sleep(0.1)
    raise RuntimeError("El servidor PHP de prueba no inició a tiempo")


def ask(page, message: str) -> str:
    before = page.locator("#messages .msg:not(.user)").count()
    page.locator("#question").fill(message)
    page.locator("#form").press("Enter")
    page.wait_for_function(
        "count => document.querySelectorAll('#messages .msg:not(.user)').length > count",
        arg=before,
    )
    return page.locator("#messages .msg:not(.user)").last.text_content() or ""


def main() -> None:
    server = subprocess.Popen(
        [str(PHP), "-S", f"127.0.0.1:{PORT}", "-t", str(WEB)],
        stdout=subprocess.DEVNULL,
        stderr=subprocess.DEVNULL,
    )
    try:
        wait_server()
        with sync_playwright() as playwright:
            browser = playwright.chromium.launch(channel="msedge", headless=True)
            desktop = browser.new_context(viewport={"width": 1280, "height": 800}, reduced_motion="reduce")
            page = desktop.new_page()
            errors: list[str] = []
            page.on("pageerror", lambda error: errors.append(str(error)))
            page.goto(BASE + "/", wait_until="networkidle")
            action_tops = page.locator(".solution-card .solution-actions").evaluate_all(
                "nodes => nodes.map(node => node.getBoundingClientRect().top)"
            )
            assert len(action_tops) == 3 and max(action_tops) - min(action_tops) <= 1.5
            page.locator("#launcher").click()
            assert "4 y 7 personas" in ask(page, "Tinajas")
            answer = ask(page, "6")
            assert "7 personas" in answer
            saved = page.evaluate("JSON.parse(sessionStorage.getItem('rpv-contexto-v1:general'))")
            assert saved["datosCliente"]["personas"] == 6
            assert "latitud" not in str(saved).lower()
            page.reload(wait_until="networkidle")
            restored = page.evaluate("JSON.parse(sessionStorage.getItem('rpv-contexto-v1:general'))")
            assert restored["modeloActual"] == "tinaja-7-personas"
            page.locator("#launcher").click()
            page.locator("#clearChat").click()
            assert page.evaluate("sessionStorage.getItem('rpv-contexto-v1:general')") is None
            assert not errors, f"Errores JavaScript en portada: {errors}"
            desktop.close()

            mobile = browser.new_context(viewport={"width": 390, "height": 844}, reduced_motion="reduce")
            page = mobile.new_page()
            errors = []
            page.on("pageerror", lambda error: errors.append(str(error)))
            page.goto(BASE + "/calderas/", wait_until="networkidle")
            page.locator("#launcher").click()
            answer = ask(page, "Precio y despacho de calderas")
            assert "Sobre el despacho" in answer
            page.locator(".location-request input").fill("San Miguel")
            page.get_by_role("button", name="Usar esta ubicación").click()
            location_error = page.locator(".location-note.is-error")
            assert location_error.get_attribute("role") == "alert"
            assert "Falta la región" in (location_error.text_content() or "")
            assert page.locator(".location-request input").get_attribute("aria-invalid") == "true"
            page.locator(".location-request input").fill("San Miguel, Región Metropolitana")
            page.get_by_role("button", name="Usar esta ubicación").click()
            assert "Comuna: San Miguel" in (page.locator("#messages .msg:not(.user)").last.text_content() or "")
            box = page.locator("#chat").bounding_box()
            assert box and box["x"] >= 0 and box["x"] + box["width"] <= 390.5
            assert box["y"] >= 0 and box["y"] + box["height"] <= 844.5
            assert not errors, f"Errores JavaScript en calderas: {errors}"
            mobile.close()

            failure = browser.new_context(viewport={"width": 390, "height": 844}, reduced_motion="reduce")
            page = failure.new_page()
            page.route("**/assets/data/conocimiento-rpv.md*", lambda route: route.abort())
            page.goto(BASE + "/", wait_until="domcontentloaded")
            page.locator("#launcher").click()
            answer = ask(page, "Cuánto cuestan las calderas")
            assert "No pude cargar" in answer or "información suficiente" in answer
            failure.close()
            browser.close()
    finally:
        server.terminate()
        try:
            server.wait(timeout=3)
        except subprocess.TimeoutExpired:
            server.kill()

    print("OK Portada: tarjetas alineadas, contexto, recarga y limpieza")
    print("OK Calderas: respuesta única, ubicación manual y diseño móvil")
    print("OK Fallo del Markdown: respuesta segura")


if __name__ == "__main__":
    main()
