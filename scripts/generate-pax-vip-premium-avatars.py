#!/usr/bin/env python3
"""Generate 10 premium portrait-style animated VIP GIF avatars with gold/metallic identity."""

from __future__ import annotations

import math
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter, ImageFont

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "paxdesign-booking/assets/customer-auth/images/avatars-vip"
LABELS_FILE = ROOT / "paxdesign-booking/includes/customer/data/avatar-vip-preset-labels.php"
LEVELS_FILE = ROOT / "paxdesign-booking/includes/customer/data/customer-level-definitions.php"

SIZE = 128
FRAMES = 12
DURATION_MS = 120

LEVELS = [
    {"level": 1, "metal": "Gold", "bg": ("#1a1208", "#3d2b0f"), "accent": "#f6d365", "skin": ("#c8956c", "#8b5e3c"), "hair": "#2a1808", "variant": 0},
    {"level": 2, "metal": "Platinum", "bg": ("#141820", "#2a3140"), "accent": "#e5e7eb", "skin": ("#d4a574", "#9a6848"), "hair": "#1f2937", "variant": 1},
    {"level": 3, "metal": "Diamond", "bg": ("#0a1628", "#1e3a5f"), "accent": "#bae6fd", "skin": ("#e0b890", "#b88968"), "hair": "#0f172a", "variant": 2},
    {"level": 4, "metal": "Titanium", "bg": ("#101418", "#2b3038"), "accent": "#cbd5e1", "skin": ("#bf9168", "#8f6344"), "hair": "#111827", "variant": 3},
    {"level": 5, "metal": "Sapphire", "bg": ("#081028", "#1e3a8a"), "accent": "#60a5fa", "skin": ("#d2a078", "#a06b4a"), "hair": "#172554", "variant": 4},
    {"level": 6, "metal": "Emerald", "bg": ("#041510", "#064e3b"), "accent": "#34d399", "skin": ("#cc9868", "#966242"), "hair": "#052e16", "variant": 5},
    {"level": 7, "metal": "Ruby", "bg": ("#180808", "#7f1d1d"), "accent": "#fb7185", "skin": ("#daaa80", "#a87350"), "hair": "#450a0a", "variant": 6},
    {"level": 8, "metal": "Obsidian", "bg": ("#050505", "#171717"), "accent": "#a3a3a3", "skin": ("#b88860", "#805838"), "hair": "#0a0a0a", "variant": 7},
    {"level": 9, "metal": "Celestial", "bg": ("#0f0820", "#312e81"), "accent": "#c4b5fd", "skin": ("#e8be98", "#c08a62"), "hair": "#1e1b4b", "variant": 8},
    {"level": 10, "metal": "Sovereign", "bg": ("#001433", "#003d82"), "accent": "#ffffff", "skin": ("#f0c898", "#d4a070"), "hair": "#001a4d", "variant": 9},
]


def _font(size: int):
    for name in ("DejaVuSans-Bold.ttf", "Arial Bold.ttf", "Arial.ttf"):
        try:
            return ImageFont.truetype(name, size)
        except OSError:
            continue
    return ImageFont.load_default()


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def lerp_color(c1: str, c2: str, t: float) -> tuple[int, int, int]:
    r1, g1, b1 = int(c1[1:3], 16), int(c1[3:5], 16), int(c1[5:7], 16)
    r2, g2, b2 = int(c2[1:3], 16), int(c2[3:5], 16), int(c2[5:7], 16)
    return (
        int(lerp(r1, r2, t)),
        int(lerp(g1, g2, t)),
        int(lerp(b1, b2, t)),
    )


def gradient_bg(draw: ImageDraw.ImageDraw, colors: tuple[str, str], t: float) -> None:
    for y in range(SIZE):
        row = lerp_color(colors[0], colors[1], y / (SIZE - 1))
        draw.line([(0, y), (SIZE, y)], fill=row)


def draw_metallic_ring(draw, cx, cy, radius, accent, t, width=3):
    pulse = 0.5 + 0.5 * math.sin(t * math.pi * 2)
    for i in range(3):
        r = radius + i * 2
        alpha = int(120 + 80 * pulse) - i * 30
        col = accent if i == 0 else accent + "88"
        draw.ellipse((cx - r, cy - r, cx + r, cy + r), outline=col, width=max(1, width - i))


def draw_portrait(draw, level: dict, t: float) -> None:
    cx, cy = SIZE // 2, SIZE // 2 + 6
    skin1, skin2 = level["skin"]
    variant = level["variant"]
    pulse = 0.5 + 0.5 * math.sin(t * math.pi * 2)
    accent = level["accent"]

    # shoulders
    draw.polygon(
        [(24, 118), (104, 118), (92, 88), (36, 88)],
        fill=lerp_color(skin2, "#000000", 0.35),
    )

    # neck
    draw.rectangle((58, 82, 70, 96), fill=lerp_color(skin1, skin2, 0.4))

    # face base with subtle tilt per variant
    tilt = (variant - 4.5) * 0.6
    face_box = (38 + tilt, 34, 90 + tilt, 92)
    draw.ellipse(face_box, fill=lerp_color(skin1, skin2, 0.25))

    # cheek shading
    draw.ellipse((42 + tilt, 58, 58 + tilt, 78), fill=lerp_color(skin2, "#000000", 0.12))
    draw.ellipse((70 + tilt, 58, 86 + tilt, 78), fill=lerp_color(skin2, "#000000", 0.12))

    # hair styles per variant
    hair = level["hair"]
    if variant % 3 == 0:
        draw.polygon([(34 + tilt, 48), (94 + tilt, 48), (88 + tilt, 28), (40 + tilt, 28)], fill=hair)
        draw.ellipse((38 + tilt, 26, 90 + tilt, 58), fill=hair)
    elif variant % 3 == 1:
        draw.ellipse((36 + tilt, 24, 92 + tilt, 62), fill=hair)
        draw.rectangle((36 + tilt, 40, 92 + tilt, 52), fill=hair)
    else:
        draw.ellipse((34 + tilt, 28, 94 + tilt, 66), fill=hair)
        draw.polygon([(40 + tilt, 36), (88 + tilt, 36), (82 + tilt, 22), (46 + tilt, 22)], fill=hair)

    # eyes with subtle blink animation
    blink = 1.0 if (t * FRAMES) % FRAMES != 10 else 0.15
    for ex in (50 + tilt, 72 + tilt):
        draw.ellipse((ex - 6, 54, ex + 6, 54 + int(8 * blink)), fill="#ffffff")
        draw.ellipse((ex - 3, 56, ex + 3, 60), fill="#1f2937")

    # premium metallic accent — lapel / collar
    draw.polygon(
        [(64, 88), (52, 118), (58, 96), (64, 92), (70, 96), (76, 118)],
        fill=lerp_color(accent, "#000000", 0.25),
    )

    # glowing accent jewel
    jr = 4 + int(2 * pulse)
    draw.ellipse((cx - jr, 72 - jr, cx + jr, 72 + jr), fill=accent)

    draw_metallic_ring(draw, cx, cy - 4, 48, accent, t, width=2)


def render_frame(level: dict, frame_idx: int) -> Image.Image:
    t = frame_idx / FRAMES
    img = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    gradient_bg(draw, level["bg"], t)
    draw_portrait(draw, level, t)

    # vignette
    mask = Image.new("L", (SIZE, SIZE), 0)
    mask_draw = ImageDraw.Draw(mask)
    mask_draw.ellipse((0, 0, SIZE - 1, SIZE - 1), fill=255)
    img.putalpha(mask)
    return img


def save_gif(level: dict, path: Path) -> None:
    frames = [render_frame(level, i) for i in range(FRAMES)]
    rgb_frames = []
    for fr in frames:
        bg = Image.new("RGB", (SIZE, SIZE), level["bg"][0])
        bg.paste(fr, mask=fr.split()[3])
        rgb_frames.append(bg.quantize(colors=96, method=Image.Quantize.MEDIANCUT))
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
        f"\tarray(\n\t\t'id'    => 'pax-vip-{level['level']:02d}',\n\t\t'label' => 'PAXDesign Level {level['level']:02d} — {level['metal']}',\n\t),"
        for level in LEVELS
    )
    LABELS_FILE.write_text(
        f"""<?php
/**
 * PAXDesign exclusive VIP avatar preset labels.
 *
 * @package PAXdesign_Booking
 */

if ( ! defined( 'ABSPATH' ) ) {{
\texit;
}}

return array(
{entries}
);
""",
        encoding="utf-8",
    )


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for old in OUT_DIR.glob("*.svg"):
        old.unlink()
    total = 0
    for level in LEVELS:
        preset_id = f"pax-vip-{level['level']:02d}"
        out = OUT_DIR / f"{preset_id}.gif"
        save_gif(level, out)
        size = out.stat().st_size
        total += size
        print(f"  {preset_id}.gif  {size // 1024:>3} KB  Level {level['level']:02d} — {level['metal']}")
    write_labels()
    print(f"\nGenerated {len(LEVELS)} premium VIP portrait GIFs (avg {total / len(LEVELS) / 1024:.1f} KB).")


if __name__ == "__main__":
    main()
