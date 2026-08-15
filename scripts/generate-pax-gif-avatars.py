#!/usr/bin/env python3
"""Generate 50 premium tech-themed animated GIF avatar presets for PAXDesign."""

from __future__ import annotations

import math
import os
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "paxdesign-booking/assets/customer-auth/images/avatars"
LABELS_FILE = ROOT / "paxdesign-booking/includes/customer/data/avatar-preset-labels.php"

SIZE = 128
FRAMES = 10
DURATION_MS = 110

PRESETS = [
    {"id": "pax-01", "label": "Terminal — command line", "bg": ("#0d1117", "#161b22"), "accent": "#58a6ff", "theme": "terminal"},
    {"id": "pax-02", "label": "Code — brackets", "bg": ("#101828", "#1d2939"), "accent": "#12b76a", "theme": "brackets"},
    {"id": "pax-03", "label": "Cybersecurity — shield", "bg": ("#0f172a", "#1e293b"), "accent": "#38bdf8", "theme": "shield"},
    {"id": "pax-04", "label": "AI — neural network", "bg": ("#1a1033", "#2d1b69"), "accent": "#a78bfa", "theme": "neural"},
    {"id": "pax-05", "label": "Web — globe", "bg": ("#0b3d2e", "#14532d"), "accent": "#34d399", "theme": "globe"},
    {"id": "pax-06", "label": "Binary — data stream", "bg": ("#111827", "#374151"), "accent": "#22d3ee", "theme": "binary"},
    {"id": "pax-07", "label": "Circuit — microchip", "bg": ("#1c1917", "#292524"), "accent": "#fbbf24", "theme": "circuit"},
    {"id": "pax-08", "label": "Cloud — sync", "bg": ("#0c4a6e", "#075985"), "accent": "#7dd3fc", "theme": "cloud"},
    {"id": "pax-09", "label": "Database — storage", "bg": ("#172554", "#1e3a8a"), "accent": "#60a5fa", "theme": "database"},
    {"id": "pax-10", "label": "API — endpoints", "bg": ("#3b0764", "#581c87"), "accent": "#e879f9", "theme": "api"},
    {"id": "pax-11", "label": "Firewall — protection", "bg": ("#450a0a", "#7f1d1d"), "accent": "#f87171", "theme": "firewall"},
    {"id": "pax-12", "label": "Encryption — secure key", "bg": ("#134e4a", "#115e59"), "accent": "#2dd4bf", "theme": "key"},
    {"id": "pax-13", "label": "DevOps — pipeline", "bg": ("#1e1b4b", "#312e81"), "accent": "#818cf8", "theme": "pipeline"},
    {"id": "pax-14", "label": "Git — version control", "bg": ("#431407", "#7c2d12"), "accent": "#fb923c", "theme": "git"},
    {"id": "pax-15", "label": "Docker — container", "bg": ("#0c4a6e", "#0369a1"), "accent": "#38bdf8", "theme": "container"},
    {"id": "pax-16", "label": "Kubernetes — orchestration", "bg": ("#1e3a8a", "#1d4ed8"), "accent": "#93c5fd", "theme": "k8s"},
    {"id": "pax-17", "label": "React — interface", "bg": ("#083344", "#164e63"), "accent": "#22d3ee", "theme": "react"},
    {"id": "pax-18", "label": "Node.js — runtime", "bg": ("#14532d", "#166534"), "accent": "#4ade80", "theme": "node"},
    {"id": "pax-19", "label": "Python — automation", "bg": ("#422006", "#713f12"), "accent": "#facc15", "theme": "python"},
    {"id": "pax-20", "label": "TypeScript — typed code", "bg": ("#1e3a8a", "#1e40af"), "accent": "#60a5fa", "theme": "typescript"},
    {"id": "pax-21", "label": "JavaScript — dynamic web", "bg": ("#422006", "#854d0e"), "accent": "#fde047", "theme": "javascript"},
    {"id": "pax-22", "label": "HTML — structure", "bg": ("#7c2d12", "#9a3412"), "accent": "#fdba74", "theme": "html"},
    {"id": "pax-23", "label": "CSS — design system", "bg": ("#1e1b4b", "#4338ca"), "accent": "#a5b4fc", "theme": "css"},
    {"id": "pax-24", "label": "SQL — query engine", "bg": ("#0f172a", "#334155"), "accent": "#94a3b8", "theme": "sql"},
    {"id": "pax-25", "label": "GraphQL — data graph", "bg": ("#500724", "#831843"), "accent": "#f472b6", "theme": "graphql"},
    {"id": "pax-26", "label": "WebSocket — live stream", "bg": ("#064e3b", "#047857"), "accent": "#6ee7b7", "theme": "websocket"},
    {"id": "pax-27", "label": "HTTPS — secure web", "bg": ("#14532d", "#15803d"), "accent": "#86efac", "theme": "https"},
    {"id": "pax-28", "label": "VPN — private tunnel", "bg": ("#1e293b", "#0f172a"), "accent": "#64748b", "theme": "vpn"},
    {"id": "pax-29", "label": "Pen test — scope scan", "bg": ("#450a0a", "#991b1b"), "accent": "#fca5a5", "theme": "pentest"},
    {"id": "pax-30", "label": "Blockchain — ledger", "bg": ("#312e81", "#3730a3"), "accent": "#c4b5fd", "theme": "blockchain"},
    {"id": "pax-31", "label": "Machine learning — model", "bg": ("#4a044e", "#701a75"), "accent": "#f0abfc", "theme": "ml"},
    {"id": "pax-32", "label": "Robotics — automation", "bg": ("#1f2937", "#374151"), "accent": "#d1d5db", "theme": "robot"},
    {"id": "pax-33", "label": "Processor — compute", "bg": ("#0f172a", "#1e293b"), "accent": "#38bdf8", "theme": "cpu"},
    {"id": "pax-34", "label": "Wi‑Fi — connectivity", "bg": ("#0c4a6e", "#0284c7"), "accent": "#bae6fd", "theme": "wifi"},
    {"id": "pax-35", "label": "Satellite — network", "bg": ("#111827", "#030712"), "accent": "#f8fafc", "theme": "satellite"},
    {"id": "pax-36", "label": "QR auth — identity", "bg": ("#18181b", "#27272a"), "accent": "#fafafa", "theme": "qr"},
    {"id": "pax-37", "label": "Biometric — fingerprint", "bg": ("#134e4a", "#0f766e"), "accent": "#5eead4", "theme": "fingerprint"},
    {"id": "pax-38", "label": "Zero trust — access", "bg": ("#1e1b4b", "#0f172a"), "accent": "#6366f1", "theme": "zerotrust"},
    {"id": "pax-39", "label": "Bug bounty — debug", "bg": ("#365314", "#4d7c0f"), "accent": "#bef264", "theme": "debug"},
    {"id": "pax-40", "label": "Deploy — rocket launch", "bg": ("#1e3a8a", "#172554"), "accent": "#93c5fd", "theme": "rocket"},
    {"id": "pax-41", "label": "Monitoring — telemetry", "bg": ("#134e4a", "#134e4a"), "accent": "#14b8a6", "theme": "monitor"},
    {"id": "pax-42", "label": "Cache — performance", "bg": ("#7c2d12", "#9a3412"), "accent": "#fdba74", "theme": "cache"},
    {"id": "pax-43", "label": "Microservices — mesh", "bg": ("#312e81", "#4338ca"), "accent": "#a5b4fc", "theme": "mesh"},
    {"id": "pax-44", "label": "Serverless — function", "bg": ("#4c0519", "#881337"), "accent": "#fb7185", "theme": "lambda"},
    {"id": "pax-45", "label": "IDE — workspace", "bg": ("#0f172a", "#1e293b"), "accent": "#38bdf8", "theme": "ide"},
    {"id": "pax-46", "label": "Open source — community", "bg": ("#14532d", "#166534"), "accent": "#86efac", "theme": "opensource"},
    {"id": "pax-47", "label": "UX — interface design", "bg": ("#831843", "#9d174d"), "accent": "#f9a8d4", "theme": "ux"},
    {"id": "pax-48", "label": "Mobile — responsive", "bg": ("#1e3a8a", "#2563eb"), "accent": "#bfdbfe", "theme": "mobile"},
    {"id": "pax-49", "label": "Digital — pixel grid", "bg": ("#111827", "#1f2937"), "accent": "#22d3ee", "theme": "pixel"},
    {"id": "pax-50", "label": "PAXDesign — signature", "bg": ("#0071e3", "#005bb5"), "accent": "#ffffff", "theme": "paxdesign"},
]


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def lerp_color(c1: str, c2: str, t: float) -> str:
    r1, g1, b1 = int(c1[1:3], 16), int(c1[3:5], 16), int(c1[5:7], 16)
    r2, g2, b2 = int(c2[1:3], 16), int(c2[3:5], 16), int(c2[5:7], 16)
    return "#{:02x}{:02x}{:02x}".format(
        int(lerp(r1, r2, t)), int(lerp(g1, g2, t)), int(lerp(b1, b2, t))
    )


def gradient_bg(draw: ImageDraw.ImageDraw, bg: tuple[str, str], phase: float) -> None:
    for y in range(SIZE):
        t = y / (SIZE - 1)
        t = (t + phase * 0.08) % 1.0
        color = lerp_color(bg[0], bg[1], t)
        draw.line([(0, y), (SIZE, y)], fill=color)


def draw_glow_circle(draw, cx, cy, r, color, alpha=40):
    for i in range(3, 0, -1):
        rr = r + i * 4
        c = color + format(min(255, alpha * i), "02x")
        draw.ellipse((cx - rr, cy - rr, cx + rr, cy + rr), fill=c)


def draw_theme(draw: ImageDraw.ImageDraw, theme: str, accent: str, t: float) -> None:
    cx, cy = SIZE // 2, SIZE // 2
    pulse = 0.5 + 0.5 * math.sin(t * math.pi * 2)
    blink = 1.0 if (int(t * FRAMES) % FRAMES) < FRAMES - 1 else 0.2

    if theme == "terminal":
        draw.rounded_rectangle((24, 30, 104, 98), radius=8, fill="#010409", outline=accent, width=2)
        draw.text((34, 40), "> deploy", fill=accent)
        y = 58 + int(6 * pulse)
        draw.rectangle((34, y, 42, y + 10), fill=accent if blink > 0.5 else "#334155")

    elif theme == "brackets":
        draw.text((38, 42), "{", fill=accent, font=_font(48))
        draw.text((72, 42), "}", fill=accent, font=_font(48))
        draw.line((64, 52, 64, 76), fill=accent, width=3)

    elif theme == "shield":
        pts = [(64, 28), (92, 40), (92, 68), (64, 98), (36, 68), (36, 40)]
        draw.polygon(pts, fill=accent + "55", outline=accent, width=3)
        draw.rectangle((58, 58, 70, 72), fill=accent)
        draw.arc((58, 48, 70, 62), 200, 340, fill=accent, width=3)

    elif theme == "neural":
        nodes = [(64, 36), (40, 64), (88, 64), (52, 88), (76, 88)]
        for i, (x, y) in enumerate(nodes):
            for j, (x2, y2) in enumerate(nodes):
                if i < j:
                    draw.line((x, y, x2, y2), fill=accent + "66", width=2)
        for i, (x, y) in enumerate(nodes):
            r = 6 + int(2 * math.sin(t * math.pi * 2 + i))
            draw.ellipse((x - r, y - r, x + r, y + r), fill=accent)

    elif theme == "globe":
        r = 30
        draw.ellipse((cx - r, cy - r, cx + r, cy + r), outline=accent, width=3)
        draw.arc((cx - r, cy - r, cx + r, cy + r), 30 + 40 * t, 210 + 40 * t, fill=accent, width=2)
        draw.line((cx - r, cy, cx + r, cy), fill=accent, width=2)
        draw.line((cx, cy - r, cx, cy + r), fill=accent, width=2)

    elif theme == "binary":
        for i in range(6):
            x = 28 + i * 14
            bit = "1" if (i + int(t * 8)) % 2 else "0"
            y = 34 + int(18 * ((i + t * 3) % 1))
            draw.text((x, y), bit, fill=accent, font=_font(16))

    elif theme == "circuit":
        draw.rectangle((42, 42, 86, 86), outline=accent, width=2)
        for x, y in [(42, 42), (86, 42), (42, 86), (86, 86)]:
            draw.rectangle((x - 3, y - 3, x + 3, y + 3), fill=accent)
        draw.line((64, 42, 64, 64), fill=accent, width=2)
        draw.line((64, 64, 86, 64), fill=accent, width=2)
        draw.ellipse((58, 58, 70, 70), fill=accent)

    elif theme == "cloud":
        y = cy + int(4 * pulse)
        draw.ellipse((34, y - 8, 58, y + 10), fill=accent + "aa")
        draw.ellipse((48, y - 14, 78, y + 8), fill=accent)
        draw.ellipse((68, y - 6, 94, y + 12), fill=accent + "cc")
        draw.line((52, y + 18, 72, y + 18), fill="#ffffff", width=2)

    elif theme == "database":
        for i, yy in enumerate([38, 54, 70]):
            w = 28 - i * 4
            draw.ellipse((cx - w, yy, cx + w, yy + 16), outline=accent, width=2)
            if i < 2:
                draw.rectangle((cx - w, yy + 8, cx + w, yy + 24), fill=accent + "44")

    elif theme == "api":
        draw.ellipse((34, 54, 46, 66), fill=accent)
        draw.ellipse((82, 54, 94, 66), fill=accent)
        draw.ellipse((58, 38, 70, 50), fill=accent)
        draw.line((40, 60, 58, 44), fill=accent, width=2)
        draw.line((88, 60, 70, 44), fill=accent, width=2)

    elif theme == "firewall":
        for i in range(5):
            h = 20 + i * 10
            draw.rectangle((36 + i * 6, 98 - h, 92 - i * 6, 98), outline=accent, width=2)

    elif theme == "key":
        draw.ellipse((44, 44, 64, 64), outline=accent, width=3)
        draw.line((64, 54, 92, 54), fill=accent, width=4)
        draw.line((82, 54, 82, 64), fill=accent, width=4)
        draw.line((74, 54, 74, 62), fill=accent, width=4)

    elif theme == "pipeline":
        x = 30 + int(50 * t)
        draw.line((28, 64, 100, 64), fill=accent, width=4)
        for px in [40, 64, 88]:
            draw.ellipse((px - 8, 56, px + 8, 72), outline=accent, width=2)
        draw.ellipse((x - 6, 58, x + 6, 70), fill=accent)

    elif theme == "git":
        draw.line((64, 34, 64, 88), fill=accent, width=3)
        draw.line((64, 50, 88, 66), fill=accent, width=3)
        draw.line((64, 66, 40, 82), fill=accent, width=3)
        for x, y in [(64, 34), (88, 66), (40, 82)]:
            draw.ellipse((x - 7, y - 7, x + 7, y + 7), fill=accent)

    elif theme == "container":
        draw.rectangle((38, 42, 90, 86), outline=accent, width=3)
        for x in [50, 64, 78]:
            draw.line((x, 42, x, 86), fill=accent, width=2)
        draw.rectangle((42, 48, 48, 54), fill=accent)

    elif theme == "k8s":
        draw.polygon([(64, 30), (90, 78), (38, 78)], outline=accent, width=3)
        draw.ellipse((58, 58, 70, 70), fill=accent)

    elif theme == "react":
        for angle in [0, 60, 120]:
            a = math.radians(angle + t * 120)
            draw.ellipse((cx + 26 * math.cos(a) - 4, cy + 26 * math.sin(a) - 4,
                          cx + 26 * math.cos(a) + 4, cy + 26 * math.sin(a) + 4), fill=accent)
        draw.ellipse((cx - 6, cy - 6, cx + 6, cy + 6), fill=accent)

    elif theme == "node":
        draw.polygon([(64, 34), (88, 78), (40, 78)], fill=accent + "55", outline=accent, width=2)

    elif theme == "python":
        draw.arc((40, 40, 70, 70), 30, 210, fill=accent, width=5)
        draw.arc((58, 58, 88, 88), 210, 390, fill=accent, width=5)

    elif theme == "typescript":
        draw.text((46, 46), "TS", fill=accent, font=_font(28))

    elif theme == "javascript":
        draw.text((48, 46), "JS", fill=accent, font=_font(26))

    elif theme == "html":
        draw.text((44, 46), "<>", fill=accent, font=_font(28))

    elif theme == "css":
        draw.text((48, 46), "#", fill=accent, font=_font(30))

    elif theme == "sql":
        draw.text((44, 48), "SQL", fill=accent, font=_font(22))

    elif theme == "graphql":
        draw.polygon([(64, 36), (88, 88), (40, 88)], outline=accent, width=3)
        draw.text((58, 58), "G", fill=accent, font=_font(20))

    elif theme == "websocket":
        draw.line((34, 64, 64, 44), fill=accent, width=3)
        draw.line((64, 44, 94, 64), fill=accent, width=3)
        draw.ellipse((58, 58, 70, 70), fill=accent if pulse > 0.5 else accent + "88")

    elif theme == "https":
        draw.rectangle((48, 52, 80, 84), outline=accent, width=3)
        draw.arc((48, 40, 80, 68), 0, 180, fill=accent, width=3)

    elif theme == "vpn":
        draw.arc((34, 50, 94, 90), 200, 340, fill=accent, width=4)
        draw.line((64, 50, 64, 88), fill=accent, width=3)

    elif theme == "pentest":
        draw.arc((40, 40, 88, 88), 20 + 300 * t, 120 + 300 * t, fill=accent, width=4)
        draw.ellipse((58, 58, 70, 70), fill=accent)

    elif theme == "blockchain":
        for i in range(3):
            x = 36 + i * 22
            draw.rectangle((x, 52, x + 18, 70), outline=accent, width=2)
            if i < 2:
                draw.line((x + 18, 61, x + 22, 61), fill=accent, width=2)

    elif theme == "ml":
        for i in range(4):
            h = 20 + int(20 * abs(math.sin(t * math.pi * 2 + i)))
            draw.rectangle((38 + i * 14, 88 - h, 48 + i * 14, 88), fill=accent)

    elif theme == "robot":
        draw.rectangle((46, 48, 82, 78), outline=accent, width=3)
        draw.ellipse((52, 34, 76, 50), outline=accent, width=2)
        draw.ellipse((54, 58, 62, 66), fill=accent)
        draw.ellipse((66, 58, 74, 66), fill=accent)

    elif theme == "cpu":
        draw.rectangle((44, 44, 84, 84), outline=accent, width=3)
        for i in range(4):
            yy = 52 + i * 10
            draw.line((44, yy, 36, yy), fill=accent, width=2)
            draw.line((84, yy, 92, yy), fill=accent, width=2)

    elif theme == "wifi":
        for i in range(3):
            r = 12 + i * 10
            draw.arc((cx - r, cy - r, cx + r, cy + r), 220, 320, fill=accent, width=3)

    elif theme == "satellite":
        draw.ellipse((58, 58, 70, 70), fill=accent)
        draw.line((64, 58, 64, 34), fill=accent, width=2)
        draw.line((50, 40, 78, 40), fill=accent, width=2)

    elif theme == "qr":
        draw.rectangle((40, 40, 88, 88), outline=accent, width=2)
        for x in range(40, 88, 8):
            for y in range(40, 88, 8):
                if (x + y + int(t * 8)) % 16 < 8:
                    draw.rectangle((x, y, x + 6, y + 6), fill=accent)

    elif theme == "fingerprint":
        for i in range(4):
            draw.arc((44 + i * 4, 44 + i * 4, 84 - i * 4, 84 - i * 4), 200, 340, fill=accent, width=2)

    elif theme == "zerotrust":
        draw.ellipse((44, 44, 84, 84), outline=accent, width=3)
        draw.line((64, 52, 64, 72), fill=accent, width=3)
        draw.line((54, 62, 74, 62), fill=accent, width=3)

    elif theme == "debug":
        draw.line((64, 36, 48, 72), fill=accent, width=4)
        draw.line((64, 36, 80, 72), fill=accent, width=4)
        draw.line((48, 72, 80, 72), fill=accent, width=4)
        draw.ellipse((58, 76, 70, 88), fill=accent)

    elif theme == "rocket":
        y = 70 - int(18 * pulse)
        draw.polygon([(64, y - 20), (78, y + 10), (64, y + 4), (50, y + 10)], fill=accent)
        draw.polygon([(64, y + 10), (72, y + 24), (64, y + 18), (56, y + 24)], fill=accent + "aa")

    elif theme == "monitor":
        for i in range(5):
            h = 12 + int(16 * abs(math.sin(t * math.pi * 2 + i * 0.7)))
            draw.line((36 + i * 12, 84, 36 + i * 12, 84 - h), fill=accent, width=3)

    elif theme == "cache":
        for i in range(3):
            draw.rectangle((42 + i * 8, 48 + i * 8, 86 - i * 8, 84 - i * 8), outline=accent, width=2)

    elif theme == "mesh":
        pts = [(40, 50), (64, 38), (88, 50), (88, 74), (64, 86), (40, 74)]
        draw.polygon(pts, outline=accent, width=2)
        draw.line((40, 50, 88, 74), fill=accent, width=1)
        draw.line((88, 50, 40, 74), fill=accent, width=1)

    elif theme == "lambda":
        draw.polygon([(64, 40), (84, 80), (64, 68), (44, 80)], fill=accent + "88", outline=accent, width=2)

    elif theme == "ide":
        draw.rectangle((32, 36, 96, 92), outline=accent, width=2)
        draw.line((32, 48, 96, 48), fill=accent, width=2)
        draw.line((44, 36, 44, 92), fill=accent, width=2)
        draw.line((52, 58, 84, 58), fill=accent, width=2)
        draw.line((52, 68, 76, 68), fill=accent, width=2)

    elif theme == "opensource":
        draw.arc((40, 40, 88, 88), 30, 330, fill=accent, width=4)
        draw.line((64, 40, 64, 88), fill=accent, width=3)

    elif theme == "ux":
        draw.rounded_rectangle((40, 44, 88, 84), radius=10, outline=accent, width=3)
        draw.ellipse((52, 56, 60, 64), fill=accent)
        draw.line((68, 72, 80, 60), fill=accent, width=3)

    elif theme == "mobile":
        draw.rounded_rectangle((46, 34, 82, 90), radius=8, outline=accent, width=3)
        draw.ellipse((58, 82, 70, 86), fill=accent)

    elif theme == "pixel":
        for i in range(6):
            for j in range(6):
                if (i + j + int(t * 6)) % 3 == 0:
                    draw.rectangle((34 + i * 10, 34 + j * 10, 42 + i * 10, 42 + j * 10), fill=accent)

    elif theme == "paxdesign":
        draw.ellipse((34, 34, 94, 94), fill="#ffffff22", outline=accent, width=3)
        draw.text((46, 48), "PAX", fill=accent, font=_font(22))
        draw_glow_circle(draw, cx, cy, 36, accent, alpha=30)


_FONT_CACHE: dict[int, ImageFont.FreeTypeFont | ImageFont.ImageFont] = {}


def _font(size: int):
    if size not in _FONT_CACHE:
        for path in (
            "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf",
            "/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf",
        ):
            if os.path.exists(path):
                _FONT_CACHE[size] = ImageFont.truetype(path, size)
                break
        else:
            _FONT_CACHE[size] = ImageFont.load_default()
    return _FONT_CACHE[size]


def render_frame(preset: dict, frame_idx: int) -> Image.Image:
    t = frame_idx / FRAMES
    img = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    gradient_bg(draw, preset["bg"], t)
    draw_theme(draw, preset["theme"], preset["accent"], t)

    # Circular mask for clean edges in circular UI.
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


def write_labels() -> None:
    entries = "\n".join(
        f"\tarray(\n\t\t'id'    => '{p['id']}',\n\t\t'label' => '{p['label']}',\n\t),"
        for p in PRESETS
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

    # Remove legacy SVG presets.
    for svg in OUT_DIR.glob("pax-*.svg"):
        svg.unlink()

    total_bytes = 0
    for preset in PRESETS:
        out = OUT_DIR / f"{preset['id']}.gif"
        save_gif(preset, out)
        size = out.stat().st_size
        total_bytes += size
        print(f"  {preset['id']}.gif  {size // 1024:>3} KB  {preset['label']}")

    write_labels()
    avg_kb = total_bytes / len(PRESETS) / 1024
    print(f"\nGenerated {len(PRESETS)} tech GIF avatars (avg {avg_kb:.1f} KB each, total {total_bytes // 1024} KB).")


if __name__ == "__main__":
    main()
