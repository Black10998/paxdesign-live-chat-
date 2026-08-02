#!/usr/bin/env python3
"""Generate pax-51 … pax-100 animated GIF avatars (batch 2). Keeps batch 1 unchanged."""

from __future__ import annotations

import importlib.util
import math
import re
from pathlib import Path

from PIL import Image, ImageDraw

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "paxdesign-booking/assets/customer-auth/images/avatars"
LABELS_FILE = ROOT / "paxdesign-booking/includes/customer/data/avatar-preset-labels.php"
BATCH1_SCRIPT = ROOT / "scripts/generate-pax-gif-avatars.py"

spec = importlib.util.spec_from_file_location("pax_gif_batch1", BATCH1_SCRIPT)
batch1 = importlib.util.module_from_spec(spec)
spec.loader.exec_module(batch1)

SIZE = batch1.SIZE
FRAMES = batch1.FRAMES
DURATION_MS = batch1.DURATION_MS
_font = batch1._font
gradient_bg = batch1.gradient_bg
draw_glow_circle = batch1.draw_glow_circle

PRESETS_BATCH2 = [
    {"id": "pax-51", "label": "Ransomware — vault lock", "bg": ("#1a0a0a", "#450a0a"), "accent": "#f87171", "theme": "vault_lock"},
    {"id": "pax-52", "label": "Phishing — hook alert", "bg": ("#1c1917", "#44403c"), "accent": "#fb923c", "theme": "phish_hook"},
    {"id": "pax-53", "label": "Dark web — onion route", "bg": ("#09090b", "#27272a"), "accent": "#a1a1aa", "theme": "onion"},
    {"id": "pax-54", "label": "Hash — SHA digest", "bg": ("#0f172a", "#1e293b"), "accent": "#94a3b8", "theme": "hash_block"},
    {"id": "pax-55", "label": "OAuth — token flow", "bg": ("#172554", "#1e3a8a"), "accent": "#60a5fa", "theme": "token_flow"},
    {"id": "pax-56", "label": "JWT — signed badge", "bg": ("#312e81", "#4338ca"), "accent": "#c4b5fd", "theme": "jwt_badge"},
    {"id": "pax-57", "label": "SAML — federation", "bg": ("#134e4a", "#115e59"), "accent": "#5eead4", "theme": "federation"},
    {"id": "pax-58", "label": "LDAP — directory tree", "bg": ("#1e3a8a", "#1e40af"), "accent": "#93c5fd", "theme": "dir_tree"},
    {"id": "pax-59", "label": "SIEM — alert radar", "bg": ("#450a0a", "#7f1d1d"), "accent": "#fca5a5", "theme": "siem_radar"},
    {"id": "pax-60", "label": "SOC — operations pulse", "bg": ("#1e1b4b", "#312e81"), "accent": "#818cf8", "theme": "soc_pulse"},
    {"id": "pax-61", "label": "Incident — response timer", "bg": ("#7c2d12", "#9a3412"), "accent": "#fdba74", "theme": "incident_timer"},
    {"id": "pax-62", "label": "Forensics — magnifier scan", "bg": ("#365314", "#4d7c0f"), "accent": "#bef264", "theme": "forensics_scan"},
    {"id": "pax-63", "label": "Malware — quarantine", "bg": ("#4c0519", "#881337"), "accent": "#fb7185", "theme": "quarantine"},
    {"id": "pax-64", "label": "Honeypot — trap jar", "bg": ("#422006", "#713f12"), "accent": "#fcd34d", "theme": "honeypot"},
    {"id": "pax-65", "label": "DDoS — mitigation wave", "bg": ("#1e293b", "#0f172a"), "accent": "#38bdf8", "theme": "ddos_wave"},
    {"id": "pax-66", "label": "Load balancer — traffic split", "bg": ("#0c4a6e", "#0369a1"), "accent": "#7dd3fc", "theme": "load_split"},
    {"id": "pax-67", "label": "CDN — edge nodes", "bg": ("#083344", "#155e75"), "accent": "#67e8f9", "theme": "cdn_edge"},
    {"id": "pax-68", "label": "DNS — resolver lookup", "bg": ("#1e1b4b", "#4c1d95"), "accent": "#ddd6fe", "theme": "dns_lookup"},
    {"id": "pax-69", "label": "TLS — handshake", "bg": ("#14532d", "#166534"), "accent": "#86efac", "theme": "tls_handshake"},
    {"id": "pax-70", "label": "Certificate — PKI chain", "bg": ("#334155", "#475569"), "accent": "#e2e8f0", "theme": "cert_chain"},
    {"id": "pax-71", "label": "Quantum — qubit spin", "bg": ("#020617", "#1e1b4b"), "accent": "#818cf8", "theme": "qubit"},
    {"id": "pax-72", "label": "RAG — retrieval pipeline", "bg": ("#4a044e", "#701a75"), "accent": "#e879f9", "theme": "rag_pipe"},
    {"id": "pax-73", "label": "LLM — prompt stream", "bg": ("#1a1033", "#4c1d95"), "accent": "#d8b4fe", "theme": "llm_stream"},
    {"id": "pax-74", "label": "Embeddings — vector space", "bg": ("#0f172a", "#312e81"), "accent": "#a5b4fc", "theme": "vector_space"},
    {"id": "pax-75", "label": "Guardrails — prompt shield", "bg": ("#450a0a", "#991b1b"), "accent": "#fecaca", "theme": "guardrail"},
    {"id": "pax-76", "label": "Data lake — layered store", "bg": ("#0c4a6e", "#164e63"), "accent": "#22d3ee", "theme": "data_lake"},
    {"id": "pax-77", "label": "ETL — extract transform", "bg": ("#431407", "#9a3412"), "accent": "#fdba74", "theme": "etl_flow"},
    {"id": "pax-78", "label": "Spark — cluster burst", "bg": ("#7c2d12", "#ea580c"), "accent": "#fed7aa", "theme": "spark_burst"},
    {"id": "pax-79", "label": "Kafka — event stream", "bg": ("#111827", "#374151"), "accent": "#fbbf24", "theme": "kafka_stream"},
    {"id": "pax-80", "label": "Pub/Sub — message bus", "bg": ("#134e4a", "#0f766e"), "accent": "#2dd4bf", "theme": "pubsub"},
    {"id": "pax-81", "label": "Search — index query", "bg": ("#422006", "#78350f"), "accent": "#fde68a", "theme": "search_index"},
    {"id": "pax-82", "label": "Prometheus — metrics ring", "bg": ("#450a0a", "#b91c1c"), "accent": "#fca5a5", "theme": "metrics_ring"},
    {"id": "pax-83", "label": "Grafana — dashboard panel", "bg": ("#1e293b", "#334155"), "accent": "#f97316", "theme": "grafana_panel"},
    {"id": "pax-84", "label": "Ansible — playbook run", "bg": ("#1e1b4b", "#312e81"), "accent": "#c084fc", "theme": "playbook"},
    {"id": "pax-85", "label": "Terraform — infra plan", "bg": ("#083344", "#0e7490"), "accent": "#67e8f9", "theme": "terraform"},
    {"id": "pax-86", "label": "Helm — chart release", "bg": ("#1e3a8a", "#2563eb"), "accent": "#bfdbfe", "theme": "helm_chart"},
    {"id": "pax-87", "label": "Argo — continuous deploy", "bg": ("#4c1d95", "#6d28d9"), "accent": "#e9d5ff", "theme": "argo_sync"},
    {"id": "pax-88", "label": "Jenkins — build pipeline", "bg": ("#7f1d1d", "#991b1b"), "accent": "#fecaca", "theme": "jenkins"},
    {"id": "pax-89", "label": "GitHub Actions — workflow", "bg": ("#111827", "#1f2937"), "accent": "#f472b6", "theme": "gh_actions"},
    {"id": "pax-90", "label": "CI/CD — infinite loop", "bg": ("#0f172a", "#1d4ed8"), "accent": "#93c5fd", "theme": "cicd_loop"},
    {"id": "pax-91", "label": "Code review — diff lens", "bg": ("#14532d", "#15803d"), "accent": "#bbf7d0", "theme": "code_review"},
    {"id": "pax-92", "label": "Merge — branch unify", "bg": ("#312e81", "#4338ca"), "accent": "#a5b4fc", "theme": "merge_branch"},
    {"id": "pax-93", "label": "Protection — branch rules", "bg": ("#450a0a", "#7f1d1d"), "accent": "#f87171", "theme": "branch_protect"},
    {"id": "pax-94", "label": "SemVer — release tag", "bg": ("#1e3a8a", "#1e40af"), "accent": "#60a5fa", "theme": "semver_tag"},
    {"id": "pax-95", "label": "Package — module registry", "bg": ("#7c2d12", "#c2410c"), "accent": "#ffedd5", "theme": "pkg_registry"},
    {"id": "pax-96", "label": "Rust — memory safe", "bg": ("#431407", "#7c2d12"), "accent": "#fdba74", "theme": "rust_gear"},
    {"id": "pax-97", "label": "Go — concurrent routines", "bg": ("#0ea5e9", "#0284c7"), "accent": "#ffffff", "theme": "go_routines"},
    {"id": "pax-98", "label": "WebAssembly — wasm cube", "bg": ("#4c1d95", "#5b21b6"), "accent": "#e9d5ff", "theme": "wasm_cube"},
    {"id": "pax-99", "label": "Edge — fog compute", "bg": ("#064e3b", "#047857"), "accent": "#6ee7b7", "theme": "edge_fog"},
    {"id": "pax-100", "label": "PAXDesign — orbit signature", "bg": ("#005bb5", "#003d82"), "accent": "#ffffff", "theme": "pax_orbit"},
]


def _pulse(t: float) -> float:
    return 0.5 + 0.5 * math.sin(t * math.pi * 2)


def _orbit(draw, cx, cy, accent, t, radius, count, size=5):
    for i in range(count):
        a = math.radians(i * (360 / count) + t * 360)
        x = cx + radius * math.cos(a)
        y = cy + radius * math.sin(a)
        draw.ellipse((x - size, y - size, x + size, y + size), fill=accent)


def draw_theme_batch2(draw: ImageDraw.ImageDraw, theme: str, accent: str, t: float) -> None:
    cx, cy = SIZE // 2, SIZE // 2
    pulse = _pulse(t)

    if theme == "vault_lock":
        draw.rounded_rectangle((40, 38, 88, 82), radius=8, outline=accent, width=3)
        draw.arc((52, 28, 76, 52), 0, 180, fill=accent, width=3)
        draw.rectangle((58, 56, 70, 68), fill=accent if pulse > 0.55 else accent + "88")

    elif theme == "phish_hook":
        draw.arc((44, 44, 84, 84), 180 + 40 * t, 300 + 40 * t, fill=accent, width=4)
        draw.line((64, 44, 64, 34), fill=accent, width=3)
        draw.polygon([(58, 34), (70, 34), (64, 26)], fill=accent)

    elif theme == "onion":
        for r in [28, 22, 16]:
            draw.ellipse((cx - r, cy - r, cx + r, cy + r), outline=accent, width=2)
        _orbit(draw, cx, cy, accent, t, 10, 4, 3)

    elif theme == "hash_block":
        for row in range(4):
            for col in range(4):
                if (row + col + int(t * 8)) % 3 != 0:
                    x = 36 + col * 14
                    y = 36 + row * 14
                    draw.rectangle((x, y, x + 10, y + 10), fill=accent)

    elif theme == "token_flow":
        x = 34 + int(44 * t)
        draw.rounded_rectangle((30, 52, 98, 76), radius=10, outline=accent, width=2)
        draw.ellipse((x - 8, 56, x + 8, 72), fill=accent)

    elif theme == "jwt_badge":
        draw.rounded_rectangle((36, 40, 92, 84), radius=10, fill=accent + "33", outline=accent, width=2)
        draw.text((46, 50), "JWT", fill=accent, font=_font(22))
        draw.line((36, 72, 92, 72), fill=accent, width=2)

    elif theme == "federation":
        for x, y in [(40, 50), (88, 50), (64, 34)]:
            draw.ellipse((x - 10, y - 10, x + 10, y + 10), outline=accent, width=2)
        draw.line((50, 50, 74, 50), fill=accent, width=2)
        draw.line((64, 44, 64, 78), fill=accent, width=2)
        draw.ellipse((58, 72, 70, 84), fill=accent if pulse > 0.5 else accent + "66")

    elif theme == "dir_tree":
        draw.line((64, 34, 64, 52), fill=accent, width=3)
        draw.line((64, 52, 44, 66), fill=accent, width=2)
        draw.line((64, 52, 84, 66), fill=accent, width=2)
        draw.line((44, 66, 44, 84), fill=accent, width=2)
        draw.line((84, 66, 84, 78), fill=accent, width=2)

    elif theme == "siem_radar":
        draw.ellipse((32, 32, 96, 96), outline=accent, width=2)
        draw.line((64, 64, 64 + 28 * math.cos(math.radians(30 + 360 * t)), 64 + 28 * math.sin(math.radians(30 + 360 * t))), fill=accent, width=3)
        draw.ellipse((78, 46, 86, 54), fill=accent)

    elif theme == "soc_pulse":
        for i in range(6):
            r = 8 + i * 6 + int(4 * pulse)
            draw.ellipse((cx - r, cy - r, cx + r, cy + r), outline=accent, width=2)

    elif theme == "incident_timer":
        draw.ellipse((40, 40, 88, 88), outline=accent, width=3)
        a = 90 - 360 * t
        draw.line((64, 64, 64 + 22 * math.cos(math.radians(a)), 64 + 22 * math.sin(math.radians(a))), fill=accent, width=3)

    elif theme == "forensics_scan":
        draw.rectangle((38, 38, 90, 90), outline=accent, width=2)
        sx = 38 + int(52 * t)
        draw.line((sx, 38, sx, 90), fill=accent, width=2)
        draw.ellipse((72, 72, 92, 92), outline=accent, width=3)

    elif theme == "quarantine":
        draw.polygon([(64, 32), (92, 88), (36, 88)], outline=accent, width=3)
        draw.line((52, 68, 76, 52), fill=accent, width=4)
        draw.line((52, 52, 76, 68), fill=accent, width=4)

    elif theme == "honeypot":
        draw.arc((44, 58, 84, 92), 180, 360, fill=accent, width=4)
        draw.line((44, 74, 84, 74), fill=accent, width=3)
        _orbit(draw, 64, 68, accent, t, 8, 3, 3)

    elif theme == "ddos_wave":
        for i in range(4):
            y = 48 + i * 10
            w = 20 + int(16 * abs(math.sin(t * math.pi * 2 + i)))
            draw.line((32, y, 32 + w, y), fill=accent, width=3)

    elif theme == "load_split":
        draw.line((64, 34, 64, 52), fill=accent, width=3)
        draw.line((64, 52, 44, 72), fill=accent, width=2)
        draw.line((64, 52, 84, 72), fill=accent, width=2)
        for x in [44, 64, 84]:
            draw.ellipse((x - 6, 72, x + 6, 84), fill=accent if pulse > 0.5 else accent + "77")

    elif theme == "cdn_edge":
        draw.ellipse((54, 54, 74, 74), fill=accent)
        _orbit(draw, cx, cy, accent + "aa", t, 26, 6, 4)

    elif theme == "dns_lookup":
        draw.text((48, 44), "DNS", fill=accent, font=_font(20))
        y = 72 + int(6 * math.sin(t * math.pi * 2))
        draw.rectangle((40, y, 88, y + 8), fill=accent)

    elif theme == "tls_handshake":
        draw.line((36, 70, 64, 44), fill=accent, width=3)
        draw.line((92, 70, 64, 44), fill=accent, width=3)
        draw.ellipse((58, 66, 70, 78), fill=accent if pulse > 0.5 else accent + "66")

    elif theme == "cert_chain":
        for i in range(3):
            draw.rounded_rectangle((42 + i * 6, 44 + i * 6, 86 - i * 6, 84 - i * 6), radius=6, outline=accent, width=2)

    elif theme == "qubit":
        draw.ellipse((52, 52, 76, 76), outline=accent, width=3)
        draw.line((40, 64, 88, 64), fill=accent, width=2)
        draw.line((64, 40, 64, 88), fill=accent, width=2)
        a = t * 360
        draw.line((64, 64, 64 + 20 * math.cos(math.radians(a)), 64 + 20 * math.sin(math.radians(a))), fill=accent, width=3)

    elif theme == "rag_pipe":
        for i, x in enumerate([34, 54, 74]):
            draw.rectangle((x, 50, x + 16, 78), outline=accent, width=2)
            if i < 2:
                draw.polygon([(x + 16, 64), (x + 22, 60), (x + 22, 68)], fill=accent)

    elif theme == "llm_stream":
        draw.rounded_rectangle((34, 40, 94, 88), radius=10, outline=accent, width=2)
        for i in range(4):
            w = 10 + int(30 * abs(math.sin(t * math.pi * 2 + i * 0.8)))
            draw.line((44, 52 + i * 8, 44 + w, 52 + i * 8), fill=accent, width=3)

    elif theme == "vector_space":
        for i in range(8):
            a = math.radians(i * 45 + t * 180)
            draw.line((64, 64, 64 + 26 * math.cos(a), 64 + 26 * math.sin(a)), fill=accent, width=2)
        draw.ellipse((60, 60, 68, 68), fill=accent)

    elif theme == "guardrail":
        pts = [(64, 30), (92, 44), (92, 72), (64, 92), (36, 72), (36, 44)]
        draw.polygon(pts, outline=accent, width=3)
        draw.line((48, 64, 80, 64), fill=accent, width=4)

    elif theme == "data_lake":
        for i in range(4):
            draw.arc((36, 48 + i * 8, 92, 88), 0, 180, fill=accent, width=2)

    elif theme == "etl_flow":
        x = 34 + int(48 * t)
        draw.ellipse((34, 56, 50, 72), fill=accent + "55", outline=accent, width=2)
        draw.ellipse((78, 56, 94, 72), fill=accent + "55", outline=accent, width=2)
        draw.ellipse((x - 8, 58, x + 8, 70), fill=accent)

    elif theme == "spark_burst":
        for i in range(8):
            a = math.radians(i * 45 + t * 360)
            draw.line((64, 64, 64 + 30 * math.cos(a), 64 + 30 * math.sin(a)), fill=accent, width=3)

    elif theme == "kafka_stream":
        for i in range(5):
            x = 36 + i * 12
            h = 12 + int(20 * abs(math.sin(t * math.pi * 2 + i)))
            draw.rectangle((x, 88 - h, x + 8, 88), fill=accent)

    elif theme == "pubsub":
        draw.ellipse((54, 54, 74, 74), fill=accent)
        for i in range(4):
            a = math.radians(i * 90 + t * 360)
            draw.line((64, 64, 64 + 28 * math.cos(a), 64 + 28 * math.sin(a)), fill=accent + "aa", width=2)

    elif theme == "search_index":
        draw.ellipse((48, 48, 72, 72), outline=accent, width=3)
        draw.line((68, 68, 86, 86), fill=accent, width=4)
        draw.line((40, 78, 88, 78), fill=accent, width=2)

    elif theme == "metrics_ring":
        draw.arc((34, 34, 94, 94), 30, 30 + 300 * t, fill=accent, width=6)
        draw.text((52, 52), "%", fill=accent, font=_font(24))

    elif theme == "grafana_panel":
        draw.rounded_rectangle((34, 38, 94, 90), radius=8, outline=accent, width=2)
        for i in range(4):
            h = 10 + int(22 * abs(math.sin(t * math.pi * 2 + i * 0.6)))
            draw.rectangle((44 + i * 12, 78 - h, 52 + i * 12, 78), fill=accent)

    elif theme == "playbook":
        draw.rectangle((40, 36, 88, 92), outline=accent, width=2)
        for i in range(4):
            draw.line((48, 48 + i * 12, 80, 48 + i * 12), fill=accent if (i + int(t * 4)) % 4 else accent + "44", width=2)

    elif theme == "terraform":
        for i in range(3):
            off = int(6 * math.sin(t * math.pi * 2 + i))
            draw.rectangle((42 + i * 14 + off, 50, 54 + i * 14 + off, 78), outline=accent, width=2)

    elif theme == "helm_chart":
        draw.polygon([(64, 34), (88, 78), (40, 78)], fill=accent + "44", outline=accent, width=2)
        draw.line((64, 78, 64, 88), fill=accent, width=3)

    elif theme == "argo_sync":
        draw.arc((38, 38, 90, 90), 60 + 300 * t, 240 + 300 * t, fill=accent, width=4)
        draw.polygon([(58, 58), (70, 58), (64, 70)], fill=accent)

    elif theme == "jenkins":
        draw.rectangle((44, 40, 84, 86), outline=accent, width=2)
        draw.line((44, 52, 84, 52), fill=accent, width=2)
        y = 58 + int(18 * t)
        draw.rectangle((52, y, 76, y + 8), fill=accent)

    elif theme == "gh_actions":
        draw.circle((64, 64), 22, outline=accent, width=3)
        draw.line((64, 42, 64, 64), fill=accent, width=3)
        draw.line((64, 64, 80, 72), fill=accent, width=3)

    elif theme == "cicd_loop":
        draw.arc((36, 36, 92, 92), 30, 330, fill=accent, width=4)
        draw.polygon([(82, 40), (92, 34), (88, 48)], fill=accent)

    elif theme == "code_review":
        draw.rectangle((38, 42, 90, 86), outline=accent, width=2)
        draw.line((38, 56, 90, 56), fill=accent, width=2)
        draw.line((56, 56, 56, 86), fill=accent + "55", width=8)

    elif theme == "merge_branch":
        draw.line((64, 36, 64, 60), fill=accent, width=3)
        draw.line((64, 60, 44, 78), fill=accent, width=2)
        draw.line((64, 60, 84, 78), fill=accent, width=2)
        draw.ellipse((58, 78, 70, 90), fill=accent)

    elif theme == "branch_protect":
        draw.rectangle((46, 48, 82, 80), outline=accent, width=3)
        draw.arc((46, 36, 82, 60), 0, 180, fill=accent, width=3)

    elif theme == "semver_tag":
        draw.rounded_rectangle((36, 50, 92, 78), radius=12, outline=accent, width=2)
        draw.text((44, 54), "v2.0", fill=accent, font=_font(18))

    elif theme == "pkg_registry":
        draw.rectangle((40, 44, 88, 84), outline=accent, width=2)
        draw.line((40, 58, 88, 58), fill=accent, width=2)
        draw.line((64, 58, 64, 84), fill=accent, width=2)

    elif theme == "rust_gear":
        r = 22 + int(3 * pulse)
        draw.ellipse((cx - r, cy - r, cx + r, cy + r), outline=accent, width=3)
        for i in range(6):
            a = math.radians(i * 60 + t * 360)
            draw.line((cx, cy, cx + 30 * math.cos(a), cy + 30 * math.sin(a)), fill=accent, width=4)

    elif theme == "go_routines":
        for i in range(3):
            x = 44 + i * 16
            y = 56 + int(8 * math.sin(t * math.pi * 2 + i))
            draw.ellipse((x, y, x + 12, y + 12), outline=accent, width=2)

    elif theme == "wasm_cube":
        s = 18 + int(4 * pulse)
        draw.polygon([(64, cy - s), (cx + s, cy), (64, cy + s), (cx - s, cy)], outline=accent, width=3)
        draw.text((52, 58), "Wa", fill=accent, font=_font(16))

    elif theme == "edge_fog":
        draw.ellipse((36, 58, 92, 88), fill=accent + "33")
        draw.ellipse((48, 48, 80, 68), fill=accent + "55")
        _orbit(draw, cx, 58, accent, t, 14, 3, 4)

    elif theme == "pax_orbit":
        draw.ellipse((30, 30, 98, 98), outline=accent, width=2)
        _orbit(draw, cx, cy, accent, t, 30, 5, 5)
        draw.text((46, 52), "PAX", fill=accent, font=_font(20))
        draw_glow_circle(draw, cx, cy, 34, accent, alpha=25)


def render_frame(preset: dict, frame_idx: int) -> Image.Image:
    t = frame_idx / FRAMES
    img = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    gradient_bg(draw, preset["bg"], t)
    draw_theme_batch2(draw, preset["theme"], preset["accent"], t)
    mask = Image.new("L", (SIZE, SIZE), 0)
    mask_draw = ImageDraw.Draw(mask)
    mask_draw.ellipse((0, 0, SIZE - 1, SIZE - 1), fill=255)
    img.putalpha(mask)
    return img


def save_gif(preset: dict, path: Path) -> None:
    frames = [render_frame(preset, i) for i in range(FRAMES)]
    rgb_frames = []
    for fr in frames:
        bg = Image.new("RGB", (SIZE, SIZE), preset["bg"][0])
        bg.paste(fr, mask=fr.split()[3])
        rgb_frames.append(bg.quantize(colors=64, method=Image.Quantize.MEDIANCUT))
    rgb_frames[0].save(
        path,
        save_all=True,
        append_images=rgb_frames[1:],
        duration=DURATION_MS,
        loop=0,
        optimize=True,
        disposal=2,
    )


def load_batch1_labels() -> list[dict]:
    text = LABELS_FILE.read_text(encoding="utf-8")
    entries = []
    for match in re.finditer(r"'id'\s*=>\s*'(pax-\d+)',\s*'label'\s*=>\s*'([^']*)'", text):
        pid, label = match.groups()
        if int(pid.replace("pax-", "")) <= 50:
            entries.append({"id": pid, "label": label.replace("\\'", "'")})
    return entries


def write_labels(batch1_labels: list[dict]) -> None:
    all_presets = batch1_labels + [{"id": p["id"], "label": p["label"]} for p in PRESETS_BATCH2]
    entries = "\n".join(
        f"\tarray(\n\t\t'id'    => '{p['id']}',\n\t\t'label' => '{p['label'].replace(chr(39), chr(92)+chr(39))}',\n\t),"
        for p in all_presets
    )
    content = f"""<?php
/**
 * PAXDesign customer avatar preset labels.
 *
 * @package PAXdesign_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {{
\texit;
}}

return array(
{entries}
);
"""
    LABELS_FILE.write_text(content, encoding="utf-8")


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    batch1_labels = load_batch1_labels()
    if len(batch1_labels) != 50:
        raise SystemExit(f"Expected 50 batch1 labels, found {len(batch1_labels)}")

    total_bytes = 0
    for preset in PRESETS_BATCH2:
        out = OUT_DIR / f"{preset['id']}.gif"
        save_gif(preset, out)
        size = out.stat().st_size
        total_bytes += size
        print(f"  {preset['id']}.gif  {size // 1024:>3} KB  {preset['label']}")

    write_labels(batch1_labels)
    avg_kb = total_bytes / len(PRESETS_BATCH2) / 1024
    print(f"\nGenerated {len(PRESETS_BATCH2)} batch-2 GIF avatars (avg {avg_kb:.1f} KB). Total presets: 100.")


if __name__ == "__main__":
    main()
