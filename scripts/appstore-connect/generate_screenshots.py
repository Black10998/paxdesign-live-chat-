#!/usr/bin/env python3
"""Generate App Store marketing screenshots (6.7\" iPhone, 1290×2796)."""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFont

ROOT = Path(__file__).resolve().parents[2]
OUT_DIR = ROOT / "docs" / "app-store" / "screenshots" / "6.7-inch"
WIDTH, HEIGHT = 1290, 2796

# PAX classic theme palette
ACCENT = (255, 140, 0)
ACCENT_BLUE = (31, 115, 242)
BG = (245, 247, 250)
SURFACE = (255, 255, 255)
SURFACE_ELEVATED = (240, 242, 247)
TEXT_PRIMARY = (17, 24, 39)
TEXT_SECONDARY = (107, 114, 128)
TEXT_TERTIARY = (156, 163, 175)
SUCCESS = (51, 199, 115)
DANGER = (242, 77, 71)
USER_BUBBLE = (229, 231, 235)


def load_font(size: int, bold: bool = False) -> ImageFont.FreeTypeFont | ImageFont.ImageFont:
    candidates = [
        "/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf" if bold else "/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf",
        "/System/Library/Fonts/Supplemental/SF-Pro-Display-Bold.otf" if bold else "/System/Library/Fonts/Supplemental/SF-Pro-Display-Regular.otf",
        "/Library/Fonts/Arial Bold.ttf" if bold else "/Library/Fonts/Arial.ttf",
    ]
    for path in candidates:
        if Path(path).exists():
            return ImageFont.truetype(path, size)
    return ImageFont.load_default()


def rounded_rect(
    draw: ImageDraw.ImageDraw,
    box: tuple[int, int, int, int],
    radius: int,
    fill: tuple[int, int, int],
    outline: tuple[int, int, int] | None = None,
) -> None:
    draw.rounded_rectangle(box, radius=radius, fill=fill, outline=outline)


def gradient_bg(size: tuple[int, int], top: tuple[int, int, int], bottom: tuple[int, int, int]) -> Image.Image:
    img = Image.new("RGB", size)
    draw = ImageDraw.Draw(img)
    w, h = size
    for y in range(h):
        ratio = y / max(h - 1, 1)
        color = tuple(int(top[i] * (1 - ratio) + bottom[i] * ratio) for i in range(3))
        draw.line([(0, y), (w, y)], fill=color)
    return img


def draw_status_bar(draw: ImageDraw.ImageDraw, origin: tuple[int, int], width: int) -> None:
    x, y = origin
    draw.text((x + 28, y + 18), "9:41", fill=TEXT_PRIMARY, font=load_font(28, bold=True))
    battery_x = x + width - 90
    draw.rounded_rectangle((battery_x, y + 24, battery_x + 44, y + 40), radius=4, outline=TEXT_PRIMARY, width=2)
    draw.rectangle((battery_x + 46, y + 30, battery_x + 50, y + 34), fill=TEXT_PRIMARY)


def draw_tab_bar(draw: ImageDraw.ImageDraw, origin: tuple[int, int], width: int, active: int) -> None:
    x, y = origin
    tabs = ["Dashboard", "Chats", "Team", "Account"]
    rounded_rect(draw, (x, y, x + width, y + 110), 0, SURFACE_ELEVATED)
    draw.line([(x, y), (x + width, y)], fill=(220, 224, 230), width=2)
    slot = width // len(tabs)
    for idx, label in enumerate(tabs):
        color = ACCENT if idx == active else TEXT_TERTIARY
        cx = x + slot * idx + slot // 2
        draw.ellipse((cx - 10, y + 22, cx + 10, y + 42), fill=color)
        draw.text((cx - 36, y + 58), label, fill=color, font=load_font(20))


def draw_nav_title(draw: ImageDraw.ImageDraw, origin: tuple[int, int], width: int, title: str, subtitle: str | None = None) -> None:
    x, y = origin
    draw.text((x + 28, y + 12), title, fill=TEXT_PRIMARY, font=load_font(34, bold=True))
    if subtitle:
        draw.text((x + 28, y + 54), subtitle, fill=TEXT_SECONDARY, font=load_font(22))


def draw_bubble(
    draw: ImageDraw.ImageDraw,
    *,
    x: int,
    y: int,
    text: str,
    outgoing: bool,
    max_width: int = 420,
) -> int:
    font = load_font(24)
    words = text.split()
    lines: list[str] = []
    current = ""
    for word in words:
        trial = f"{current} {word}".strip()
        if draw.textlength(trial, font=font) <= max_width:
            current = trial
        else:
            if current:
                lines.append(current)
            current = word
    if current:
        lines.append(current)

    line_h = 32
    pad_x, pad_y = 18, 14
    bubble_w = min(max_width, max(int(draw.textlength(line, font=font)) for line in lines) + pad_x * 2)
    bubble_h = len(lines) * line_h + pad_y * 2
    fill = ACCENT_BLUE if outgoing else USER_BUBBLE
    text_color = (255, 255, 255) if outgoing else TEXT_PRIMARY
    bx = x if outgoing else x
    if outgoing:
        bx = x + max_width - bubble_w
    rounded_rect(draw, (bx, y, bx + bubble_w, y + bubble_h), 22, fill)
    ty = y + pad_y
    for line in lines:
        draw.text((bx + pad_x, ty), line, fill=text_color, font=font)
        ty += line_h
    return bubble_h + 14


def draw_voice_bubble(draw: ImageDraw.ImageDraw, x: int, y: int, duration: str = "0:24") -> int:
    w, h = 360, 64
    rounded_rect(draw, (x, y, x + w, y + h), 22, ACCENT_BLUE)
    draw.ellipse((x + 16, y + 14, x + 52, y + 50), fill=(255, 255, 255))
    draw.polygon([(x + 30, y + 22), (x + 30, y + 42), (x + 44, y + 32)], fill=ACCENT_BLUE)
    for i in range(12):
        bar_h = 10 + (i % 4) * 8
        bx = x + 68 + i * 18
        draw.rounded_rectangle((bx, y + 32 - bar_h // 2, bx + 8, y + 32 + bar_h // 2), radius=3, fill=(255, 255, 255))
    draw.text((x + w - 70, y + 20), duration, fill=(255, 255, 255), font=load_font(22))
    return h + 14


def draw_image_bubble(draw: ImageDraw.ImageDraw, x: int, y: int) -> int:
    w, h = 280, 180
    rounded_rect(draw, (x, y, x + w, y + h), 18, (210, 220, 235))
    for i in range(5):
        px = x + 30 + i * 45
        draw.ellipse((px, y + 90, px + 30, y + 120), fill=(ACCENT if i % 2 else ACCENT_BLUE))
    draw.text((x + 18, y + h - 36), "Photo shared", fill=TEXT_SECONDARY, font=load_font(20))
    return h + 14


def draw_location_card(draw: ImageDraw.ImageDraw, x: int, y: int) -> int:
    w, h = 320, 120
    rounded_rect(draw, (x, y, x + w, y + h), 18, SURFACE_ELEVATED, outline=(220, 224, 230))
    draw.ellipse((x + 20, y + 24, x + 56, y + 60), fill=ACCENT)
    draw.text((x + 70, y + 24), "PAXdesign Office", fill=TEXT_PRIMARY, font=load_font(24, bold=True))
    draw.text((x + 70, y + 58), "Vienna, Austria", fill=TEXT_SECONDARY, font=load_font(20))
    draw.text((x + 70, y + 86), "Tap to open in Maps", fill=ACCENT_BLUE, font=load_font(18))
    return h + 14


def draw_ai_suggestion(draw: ImageDraw.ImageDraw, x: int, y: int, text: str) -> int:
    w = 520
    h = 96
    rounded_rect(draw, (x, y, x + w, y + h), 18, (255, 248, 235), outline=ACCENT)
    draw.text((x + 18, y + 14), "AI Suggestion", fill=ACCENT, font=load_font(20, bold=True))
    draw.text((x + 18, y + 44), text[:52] + ("…" if len(text) > 52 else ""), fill=TEXT_PRIMARY, font=load_font(22))
    draw.text((x + w - 120, y + 58), "Use reply", fill=ACCENT_BLUE, font=load_font(20, bold=True))
    return h + 16


def draw_session_row(draw: ImageDraw.ImageDraw, x: int, y: int, title: str, preview: str, badge: int = 0, live: bool = False) -> int:
    h = 96
    rounded_rect(draw, (x, y, x + 560, y + h), 20, SURFACE, outline=(230, 234, 240))
    accent = DANGER if live else ACCENT
    draw.ellipse((x + 20, y + 28, x + 56, y + 64), fill=accent)
    draw.text((x + 76, y + 20), title, fill=TEXT_PRIMARY, font=load_font(24, bold=True))
    draw.text((x + 76, y + 54), preview, fill=TEXT_SECONDARY, font=load_font(20))
    if badge:
        bx = x + 520
        draw.ellipse((bx, y + 24, bx + 36, y + 60), fill=DANGER)
        draw.text((bx + 10, y + 32), str(badge), fill=(255, 255, 255), font=load_font(20, bold=True))
    if live:
        draw.rounded_rectangle((x + 430, y + 22, x + 500, y + 50), radius=12, fill=(255, 235, 235))
        draw.text((x + 442, y + 28), "LIVE", fill=DANGER, font=load_font(18, bold=True))
    return h + 14


def draw_notification_banner(draw: ImageDraw.ImageDraw, x: int, y: int) -> int:
    w, h = 560, 88
    rounded_rect(draw, (x, y, x + w, y + h), 22, SURFACE, outline=(220, 224, 230))
    draw.ellipse((x + 18, y + 22, x + 54, y + 58), fill=ACCENT)
    draw.text((x + 70, y + 18), "PAXDesign Live Chat", fill=TEXT_PRIMARY, font=load_font(22, bold=True))
    draw.text((x + 70, y + 48), "New live customer request", fill=TEXT_SECONDARY, font=load_font(20))
    draw.text((x + w - 70, y + 30), "now", fill=TEXT_TERTIARY, font=load_font(18))
    return h + 12


def render_screen(content_fn) -> Image.Image:
    screen_w, screen_h = 920, 1990
    screen = Image.new("RGB", (screen_w, screen_h), BG)
    draw = ImageDraw.Draw(screen)
    draw_status_bar(draw, (0, 0), screen_w)
    content_fn(draw, screen_w, screen_h)
    return screen


def screen_team_chat(draw: ImageDraw.ImageDraw, width: int, height: int) -> None:
    draw_nav_title(draw, (0, 72), width, "Team Chat", "Design · Support · Sales")
    y = 170
    draw_bubble(draw, x=28, y=y, text="Morning team — new booking requests are up 12% today.", outgoing=False)
    y += 90
    draw_bubble(draw, x=28, y=y, text="On it. I'll take the Vienna leads.", outgoing=True, max_width=480)
    y += 90
    draw_voice_bubble(draw, x=28, y=y)
    y += 90
    draw_bubble(draw, x=28, y=y, text="Voice note received — sounds good!", outgoing=False)
    draw_tab_bar(draw, (0, height - 110), width, active=2)


def screen_ai_assistant(draw: ImageDraw.ImageDraw, width: int, height: int) -> None:
    draw_nav_title(draw, (0, 72), width, "Customer Chat", "Session #4821")
    y = 170
    draw_bubble(draw, x=28, y=y, text="Hi, I need help rescheduling my appointment for next week.", outgoing=False)
    y += 100
    draw_ai_suggestion(draw, 28, y, "Happy to help! Which day works best — Tue 10:00 or Wed 14:30?")
    y += 120
    draw_bubble(draw, x=28, y=y, text="Happy to help! Wednesday 14:30 works perfectly.", outgoing=True, max_width=500)
    y += 100
    draw_bubble(draw, x=28, y=y, text="Perfect, thank you!", outgoing=False)
    draw_tab_bar(draw, (0, height - 110), width, active=1)


def screen_voice_messages(draw: ImageDraw.ImageDraw, width: int, height: int) -> None:
    draw_nav_title(draw, (0, 72), width, "Team Chat", "Quick voice updates")
    y = 180
    draw_bubble(draw, x=28, y=y, text="Can someone cover the afternoon shift?", outgoing=False)
    y += 90
    draw_voice_bubble(draw, x=120, y=y, duration="0:18")
    y += 90
    draw_voice_bubble(draw, x=28, y=y, duration="0:42")
    y += 90
    draw_bubble(draw, x=28, y=y, text="Got it — I'm available after 3 PM.", outgoing=True, max_width=460)
    mic_y = height - 220
    rounded_rect(draw, (width // 2 - 44, mic_y, width // 2 + 44, mic_y + 88), 44, ACCENT)
    draw.ellipse((width // 2 - 18, mic_y + 20, width // 2 + 18, mic_y + 56), fill=(255, 255, 255))
    draw_tab_bar(draw, (0, height - 110), width, active=2)


def screen_media_sharing(draw: ImageDraw.ImageDraw, width: int, height: int) -> None:
    draw_nav_title(draw, (0, 72), width, "Team Chat", "Share photos & location")
    y = 170
    draw_image_bubble(draw, x=28, y=y)
    y += 200
    draw_location_card(draw, x=28, y=y)
    y += 140
    draw_bubble(draw, x=28, y=y, text="On-site now — lobby entrance.", outgoing=True, max_width=460)
    draw_tab_bar(draw, (0, height - 110), width, active=2)


def screen_live_support(draw: ImageDraw.ImageDraw, width: int, height: int) -> None:
    draw_nav_title(draw, (0, 72), width, "Dashboard", "Real-time support")
    y = 150
    draw_notification_banner(draw, 28, y)
    y += 110
    draw_session_row(draw, 28, y, "Live Request", "Customer waiting for agent", badge=1, live=True)
    y += 110
    draw_session_row(draw, 28, y, "Maria K.", "Thanks for the quick reply!", badge=2)
    y += 110
    draw_session_row(draw, 28, y, "Booking #8821", "Can I change my time slot?", badge=0)
    y += 110
    rounded_rect(draw, (28, y, 588, y + 120), 20, (255, 248, 235))
    draw.text((48, y + 24), "Analytics", fill=TEXT_PRIMARY, font=load_font(24, bold=True))
    draw.text((48, y + 62), "12 active · 3 live · 98% response rate", fill=TEXT_SECONDARY, font=load_font(20))
    draw_tab_bar(draw, (0, height - 110), width, active=0)


SCREENS = [
    ("01-team-chat.png", "Live Team Chat", "Coordinate instantly with your support team.", screen_team_chat),
    ("02-ai-assistant.png", "AI Reply Assistant", "Smart suggestions help you respond faster.", screen_ai_assistant),
    ("03-voice-messages.png", "Voice Messages", "Record and play voice notes in one tap.", screen_voice_messages),
    ("04-photo-location.png", "Photos & Location", "Share images and your position securely.", screen_media_sharing),
    ("05-live-support.png", "Live Support & Alerts", "Never miss a customer with push notifications.", screen_live_support),
]


def compose_marketing(screen: Image.Image, headline: str, subtitle: str, accent: tuple[int, int, int]) -> Image.Image:
    canvas = gradient_bg((WIDTH, HEIGHT), (250, 252, 255), (235, 240, 250))
    draw = ImageDraw.Draw(canvas)

    draw.text((WIDTH // 2, 180), headline, fill=TEXT_PRIMARY, font=load_font(62, bold=True), anchor="mm")
    draw.text((WIDTH // 2, 270), subtitle, fill=TEXT_SECONDARY, font=load_font(34), anchor="mm")

    frame_w, frame_h = 960, 2060
    frame_x = (WIDTH - frame_w) // 2
    frame_y = 360
    shadow = Image.new("RGBA", (frame_w + 40, frame_h + 40), (0, 0, 0, 0))
    shadow_draw = ImageDraw.Draw(shadow)
    shadow_draw.rounded_rectangle((20, 20, frame_w + 20, frame_h + 20), radius=72, fill=(0, 0, 0, 45))
    canvas.paste(shadow, (frame_x - 20, frame_y - 10), shadow)

    bezel = Image.new("RGB", (frame_w, frame_h), (20, 20, 22))
    bezel_draw = ImageDraw.Draw(bezel)
    bezel_draw.rounded_rectangle((0, 0, frame_w, frame_h), radius=68, fill=(20, 20, 22))
    inner_x, inner_y = 14, 14
    inner_w, inner_h = frame_w - 28, frame_h - 28
    screen_resized = screen.resize((inner_w, inner_h), Image.Resampling.LANCZOS)
    bezel.paste(screen_resized, (inner_x, inner_y))
    dynamic = Image.new("RGB", (110, 34), (20, 20, 22))
    bezel.paste(dynamic, ((frame_w - 110) // 2, 24))

    canvas.paste(bezel, (frame_x, frame_y))

    draw.ellipse((WIDTH - 120, 320, WIDTH - 60, 380), fill=accent)
    draw.ellipse((80, HEIGHT - 220, 140, HEIGHT - 160), fill=(*accent[:2], min(accent[2] + 40, 255)))

    draw.text((WIDTH // 2, HEIGHT - 120), "PAXDesign Live Chat", fill=TEXT_TERTIARY, font=load_font(28), anchor="mm")
    return canvas


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for filename, headline, subtitle, renderer in SCREENS:
        screen = render_screen(renderer)
        marketing = compose_marketing(screen, headline, subtitle, ACCENT)
        path = OUT_DIR / filename
        marketing.save(path, "PNG", optimize=True)
        print(f"Wrote {path} ({marketing.size[0]}×{marketing.size[1]})")
    print(f"Generated {len(SCREENS)} screenshots in {OUT_DIR}")


if __name__ == "__main__":
    main()
