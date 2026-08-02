#!/usr/bin/env python3
"""Generate 10 premium animated VIP GIF avatars — luxury emblem / medallion identity."""

from __future__ import annotations

import math
import random
from pathlib import Path

from PIL import Image, ImageDraw, ImageFilter

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "paxdesign-booking/assets/customer-auth/images/avatars-vip"
LABELS_FILE = ROOT / "paxdesign-booking/includes/customer/data/avatar-vip-preset-labels.php"

SIZE = 128
FRAMES = 16
DURATION_MS = 90

LEVELS = [
    {"level": 1, "metal": "Gold", "bg": ("#120c04", "#4a3410"), "accent": "#f6d365", "accent2": "#d4af37", "gem": "#fff4c2"},
    {"level": 2, "metal": "Platinum", "bg": ("#101318", "#3a4352"), "accent": "#f3f4f6", "accent2": "#cbd5e1", "gem": "#ffffff"},
    {"level": 3, "metal": "Diamond", "bg": ("#06101f", "#1b3558"), "accent": "#dbeafe", "accent2": "#60a5fa", "gem": "#e0f2fe"},
    {"level": 4, "metal": "Titanium", "bg": ("#0d1014", "#303742"), "accent": "#e5e7eb", "accent2": "#94a3b8", "gem": "#f8fafc"},
    {"level": 5, "metal": "Sapphire", "bg": ("#050f2a", "#1e3a8a"), "accent": "#93c5fd", "accent2": "#2563eb", "gem": "#bfdbfe"},
    {"level": 6, "metal": "Emerald", "bg": ("#03140f", "#065f46"), "accent": "#6ee7b7", "accent2": "#10b981", "gem": "#a7f3d0"},
    {"level": 7, "metal": "Ruby", "bg": ("#180505", "#7f1d1d"), "accent": "#fda4af", "accent2": "#e11d48", "gem": "#fecdd3"},
    {"level": 8, "metal": "Obsidian", "bg": ("#030303", "#171717"), "accent": "#d4d4d8", "accent2": "#52525b", "gem": "#fafafa"},
    {"level": 9, "metal": "Celestial", "bg": ("#12082a", "#4338ca"), "accent": "#ddd6fe", "accent2": "#8b5cf6", "gem": "#ede9fe"},
    {"level": 10, "metal": "Sovereign", "bg": ("#00102b", "#004080"), "accent": "#ffffff", "accent2": "#38bdf8", "gem": "#fef08a"},
]


def lerp(a: float, b: float, t: float) -> float:
    return a + (b - a) * t


def lerp_color(c1: str, c2: str, t: float) -> tuple[int, int, int]:
    r1, g1, b1 = int(c1[1:3], 16), int(c1[3:5], 16), int(c1[5:7], 16)
    r2, g2, b2 = int(c2[1:3], 16), int(c2[3:5], 16), int(c2[5:7], 16)
    return (int(lerp(r1, r2, t)), int(lerp(g1, g2, t)), int(lerp(b1, b2, t)))


def gradient_bg(draw: ImageDraw.ImageDraw, colors: tuple[str, str]) -> None:
    for y in range(SIZE):
        row = lerp_color(colors[0], colors[1], y / (SIZE - 1))
        draw.line([(0, y), (SIZE, y)], fill=row)


def draw_radial_glow(base: Image.Image, cx: int, cy: int, radius: int, color: str, alpha: int) -> None:
    glow = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    gdraw = ImageDraw.Draw(glow)
    rgb = lerp_color(color, "#000000", 0.2)
    for i in range(radius, 0, -4):
        a = int(alpha * (i / radius))
        gdraw.ellipse((cx - i, cy - i, cx + i, cy + i), fill=rgb + (a,))
    base.alpha_composite(glow)


def draw_ring(draw: ImageDraw.ImageDraw, cx: int, cy: int, radius: int, color: str, width: int = 3) -> None:
    draw.ellipse((cx - radius, cy - radius, cx + radius, cy + radius), outline=color, width=width)


def draw_crown(draw: ImageDraw.ImageDraw, cx: int, cy: int, accent: str, accent2: str, level: int, t: float) -> None:
    pulse = 0.5 + 0.5 * math.sin(t * math.pi * 2)
    h = 10 + int(3 * pulse)
    points = [
        (cx - 18, cy + 6),
        (cx - 12, cy - h),
        (cx - 6, cy + 2),
        (cx, cy - h - 4),
        (cx + 6, cy + 2),
        (cx + 12, cy - h),
        (cx + 18, cy + 6),
        (cx + 14, cy + 12),
        (cx - 14, cy + 12),
    ]
    draw.polygon(points, fill=lerp_color(accent2, accent, 0.35))
    for px in (cx - 12, cx, cx + 12):
        draw.ellipse((px - 3, cy - h - 7, px + 3, cy - h - 1), fill=accent)


def draw_emblem_core(draw: ImageDraw.ImageDraw, level: dict, t: float) -> None:
    cx, cy = SIZE // 2, SIZE // 2
    accent = level["accent"]
    accent2 = level["accent2"]
    gem = level["gem"]
    lvl = level["level"]
    pulse = 0.5 + 0.5 * math.sin(t * math.pi * 2)
    spin = t * math.pi * 2

    draw_radial_glow(draw._image if hasattr(draw, "_image") else None, cx, cy, 52, accent, 70)  # noqa: handled below

    for ring, w in ((46, 4), (38, 2), (30, 2)):
        c = accent if ring > 35 else accent2
        draw_ring(draw, cx, cy, ring, c, width=w)

    # Inner medallion
    draw.ellipse((cx - 24, cy - 24, cx + 24, cy + 24), fill=lerp_color(accent2, "#000000", 0.55))
    draw.ellipse((cx - 20, cy - 20, cx + 20, cy + 20), fill=lerp_color(accent, "#000000", 0.25))

    # Rotating shimmer arc
    for i in range(3):
        angle = spin + i * (math.pi * 2 / 3)
        sx = cx + int(math.cos(angle) * 16)
        sy = cy + int(math.sin(angle) * 16)
        draw.line([(cx, cy), (sx, sy)], fill=gem, width=2)

    # Center jewel
    jr = 7 + int(2 * pulse)
    draw.ellipse((cx - jr, cy - jr, cx + jr, cy + jr), fill=gem)
    draw.ellipse((cx - jr + 2, cy - jr + 2, cx - 3, cy - 3), fill=lerp_color(gem, "#ffffff", 0.55))

    # Level-specific ornament
    if lvl >= 8:
        draw_crown(draw, cx, cy - 10, accent, accent2, lvl, t)
    elif lvl >= 5:
        draw.polygon(
            [(cx, cy - 16), (cx - 10, cy + 8), (cx + 10, cy + 8)],
            fill=lerp_color(accent, gem, 0.35),
        )
    else:
        draw.rectangle((cx - 8, cy - 10, cx + 8, cy + 10), fill=lerp_color(accent2, "#000000", 0.15))

    # Orbiting sparkles
    rng = random.Random(lvl * 9973)
    for i in range(6 + lvl):
        angle = spin * (1.2 + i * 0.07) + i * 1.4
        dist = 28 + (i % 3) * 7
        sx = cx + int(math.cos(angle) * dist)
        sy = cy + int(math.sin(angle) * dist)
        size = 1 + (1 if (i + int(t * FRAMES)) % 3 == 0 else 0)
        draw.ellipse((sx - size, sy - size, sx + size, sy + size), fill=gem if i % 2 else accent)


def render_frame(level: dict, frame_idx: int) -> Image.Image:
    t = frame_idx / FRAMES
    img = Image.new("RGBA", (SIZE, SIZE), (0, 0, 0, 0))
    draw = ImageDraw.Draw(img)
    gradient_bg(draw, tuple(level["bg"]))
    draw_radial_glow(img, SIZE // 2, SIZE // 2, 56, level["accent"], 80)
    emblem_draw = ImageDraw.Draw(img)
    emblem_draw._image = img  # type: ignore[attr-defined]

    cx, cy = SIZE // 2, SIZE // 2
    accent = level["accent"]
    accent2 = level["accent2"]
    gem = level["gem"]
    lvl = level["level"]
    pulse = 0.5 + 0.5 * math.sin(t * math.pi * 2)
    spin = t * math.pi * 2

    for ring, w in ((48, 4), (40, 2), (32, 2)):
        c = accent if ring > 36 else accent2
        draw_ring(emblem_draw, cx, cy, ring, c, width=w)

    emblem_draw.ellipse((cx - 26, cy - 26, cx + 26, cy + 26), fill=lerp_color(accent2, "#000000", 0.58))
    emblem_draw.ellipse((cx - 22, cy - 22, cx + 22, cy + 22), fill=lerp_color(accent, "#000000", 0.22))

    for i in range(4):
        angle = spin + i * (math.pi / 2)
        sx = cx + int(math.cos(angle) * 18)
        sy = cy + int(math.sin(angle) * 18)
        emblem_draw.line([(cx, cy), (sx, sy)], fill=gem, width=2)

    jr = 8 + int(2 * pulse)
    emblem_draw.ellipse((cx - jr, cy - jr, cx + jr, cy + jr), fill=gem)
    emblem_draw.ellipse((cx - jr + 2, cy - jr + 2, cx - 4, cy - 4), fill=lerp_color(gem, "#ffffff", 0.6))

    if lvl >= 8:
        draw_crown(emblem_draw, cx, cy - 10, accent, accent2, lvl, t)
    elif lvl >= 5:
        emblem_draw.polygon(
            [(cx, cy - 18), (cx - 12, cy + 10), (cx + 12, cy + 10)],
            fill=lerp_color(accent, gem, 0.35),
        )
    else:
        emblem_draw.rectangle((cx - 9, cy - 11, cx + 9, cy + 11), fill=lerp_color(accent2, "#000000", 0.12))

    for i in range(6 + lvl):
        angle = spin * (1.15 + i * 0.06) + i * 1.25
        dist = 30 + (i % 3) * 6
        sx = cx + int(math.cos(angle) * dist)
        sy = cy + int(math.sin(angle) * dist)
        size = 1 + (1 if (i + frame_idx) % 4 == 0 else 0)
        emblem_draw.ellipse((sx - size, sy - size, sx + size, sy + size), fill=gem if i % 2 else accent)

    mask = Image.new("L", (SIZE, SIZE), 0)
    ImageDraw.Draw(mask).ellipse((2, 2, SIZE - 3, SIZE - 3), fill=255)
    img.putalpha(mask)
    return img.filter(ImageFilter.SMOOTH)


def save_gif(level: dict, path: Path) -> None:
    frames = [render_frame(level, i) for i in range(FRAMES)]
    rgb_frames = []
    for fr in frames:
        bg = Image.new("RGB", (SIZE, SIZE), level["bg"][0])
        bg.paste(fr, mask=fr.split()[3])
        rgb_frames.append(bg.quantize(colors=128, method=Image.Quantize.MEDIANCUT))
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
        f"\tarray(\n\t\t'id'    => 'pax-vip-{level['level']:02d}',\n\t\t'label' => 'Level {level['level']:02d} {level['metal']}',\n\t),"
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
    print(f"\nGenerated {len(LEVELS)} premium VIP emblem GIFs (avg {total / len(LEVELS) / 1024:.1f} KB).")


if __name__ == "__main__":
    main()
