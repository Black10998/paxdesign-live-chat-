#!/usr/bin/env python3
"""Generate 10 exclusive animated VIP SVG avatars for PAXDesign."""

from __future__ import annotations

from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "paxdesign-booking/assets/customer-auth/images/avatars-vip"
LABELS_FILE = ROOT / "paxdesign-booking/includes/customer/data/avatar-vip-preset-labels.php"

PRESETS = [
    {"id": "pax-vip-01", "label": "VIP — Quantum Nexus", "bg1": "#050816", "bg2": "#1a0f3d", "accent": "#a78bfa", "theme": "quantum"},
    {"id": "pax-vip-02", "label": "VIP — Cyber Crown", "bg1": "#0a0f1a", "bg2": "#1c1917", "accent": "#fbbf24", "theme": "crown"},
    {"id": "pax-vip-03", "label": "VIP — Neural Sovereign", "bg1": "#0f0520", "bg2": "#2e1065", "accent": "#e879f9", "theme": "neural"},
    {"id": "pax-vip-04", "label": "VIP — Zero Trust Elite", "bg1": "#020617", "bg2": "#0f172a", "accent": "#38bdf8", "theme": "zerotrust"},
    {"id": "pax-vip-05", "label": "VIP — AI Architect", "bg1": "#0c0a1f", "bg2": "#312e81", "accent": "#818cf8", "theme": "architect"},
    {"id": "pax-vip-06", "label": "VIP — Cipher Phantom", "bg1": "#0a0a0a", "bg2": "#171717", "accent": "#22d3ee", "theme": "cipher"},
    {"id": "pax-vip-07", "label": "VIP — Digital Apex", "bg1": "#001a12", "bg2": "#064e3b", "accent": "#34d399", "theme": "apex"},
    {"id": "pax-vip-08", "label": "VIP — Sentinel Prime", "bg1": "#1a0505", "bg2": "#450a0a", "accent": "#f87171", "theme": "sentinel"},
    {"id": "pax-vip-09", "label": "VIP — Code Oracle", "bg1": "#0b1020", "bg2": "#1e3a8a", "accent": "#60a5fa", "theme": "oracle"},
    {"id": "pax-vip-10", "label": "VIP — PAXDesign Elite", "bg1": "#001433", "bg2": "#003d82", "accent": "#ffffff", "theme": "elite"},
]


def svg_header(p: dict) -> str:
    return f'''<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 128 128" width="128" height="128" role="img" aria-label="{p['label']}">
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="{p['bg1']}"/>
      <stop offset="100%" stop-color="{p['bg2']}"/>
    </linearGradient>
    <radialGradient id="glow" cx="50%" cy="45%" r="55%">
      <stop offset="0%" stop-color="{p['accent']}" stop-opacity="0.35"/>
      <stop offset="100%" stop-color="{p['accent']}" stop-opacity="0"/>
    </radialGradient>
    <clipPath id="circle"><circle cx="64" cy="64" r="64"/></clipPath>
    <filter id="soft"><feGaussianBlur stdDeviation="1.2" result="b"/><feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge></filter>
  </defs>
  <g clip-path="url(#circle)">
    <rect width="128" height="128" fill="url(#bg)"/>
    <circle cx="64" cy="64" r="58" fill="url(#glow)"/>
'''


def svg_footer() -> str:
    return "  </g>\n</svg>\n"


def theme_art(theme: str, accent: str) -> str:
    a = accent
    if theme == "quantum":
        return f'''
    <g transform="translate(64 64)" filter="url(#soft)">
      <circle r="10" fill="{a}" opacity="0.9">
        <animate attributeName="r" values="9;12;9" dur="3s" repeatCount="indefinite"/>
      </circle>
      <g>
        <ellipse rx="34" ry="14" fill="none" stroke="{a}" stroke-width="1.5" opacity="0.85"/>
        <animateTransform attributeName="transform" type="rotate" from="0" to="360" dur="8s" repeatCount="indefinite"/>
      </g>
      <g transform="rotate(60)">
        <ellipse rx="28" ry="10" fill="none" stroke="{a}" stroke-width="1.2" opacity="0.6"/>
        <animateTransform attributeName="transform" type="rotate" from="60" to="420" dur="11s" repeatCount="indefinite"/>
      </g>
      <circle r="3" fill="{a}" cx="34" cy="0">
        <animateTransform attributeName="transform" type="rotate" from="0" to="360" dur="8s" repeatCount="indefinite"/>
      </circle>
    </g>'''
    if theme == "crown":
        return f'''
    <g filter="url(#soft)">
      <path d="M34 78 L44 48 L54 62 L64 38 L74 62 L84 48 L94 78 Z" fill="none" stroke="{a}" stroke-width="2.2" stroke-linejoin="round">
        <animate attributeName="opacity" values="0.65;1;0.65" dur="2.8s" repeatCount="indefinite"/>
      </path>
      <rect x="38" y="78" width="52" height="10" rx="3" fill="{a}" opacity="0.75"/>
      <circle cx="64" cy="38" r="4" fill="{a}">
        <animate attributeName="r" values="3.5;5;3.5" dur="2s" repeatCount="indefinite"/>
      </circle>
    </g>'''
    if theme == "neural":
        nodes = [(40, 52), (88, 52), (64, 36), (48, 84), (80, 84), (64, 64)]
        lines = ""
        for i, (x1, y1) in enumerate(nodes):
            for x2, y2 in nodes[i + 1 :]:
                lines += f'<line x1="{x1}" y1="{y1}" x2="{x2}" y2="{y2}" stroke="{a}" stroke-width="1" opacity="0.35"/>'
        dots = ""
        for i, (x, y) in enumerate(nodes):
            dots += f'<circle cx="{x}" cy="{y}" r="4" fill="{a}"><animate attributeName="opacity" values="0.5;1;0.5" dur="{2+i*0.3}s" repeatCount="indefinite"/></circle>'
        return f'<g filter="url(#soft)">{lines}{dots}</g>'
    if theme == "zerotrust":
        return f'''
    <g transform="translate(64 64)" filter="url(#soft)">
      <polygon points="0,-36 31,-18 31,18 0,36 -31,18 -31,-18" fill="none" stroke="{a}" stroke-width="2">
        <animateTransform attributeName="transform" type="rotate" from="0" to="360" dur="16s" repeatCount="indefinite"/>
      </polygon>
      <polygon points="0,-22 19,-11 19,11 0,22 -19,11 -19,-11" fill="{a}" opacity="0.2">
        <animate attributeName="opacity" values="0.15;0.35;0.15" dur="3s" repeatCount="indefinite"/>
      </polygon>
      <circle r="6" fill="{a}"/>
    </g>'''
    if theme == "architect":
        return f'''
    <g transform="translate(64 64)" filter="url(#soft)">
      <polygon points="0,-30 26,15 -26,15" fill="none" stroke="{a}" stroke-width="2"/>
      <polygon points="0,-18 16,9 -16,9" fill="{a}" opacity="0.25">
        <animate attributeName="opacity" values="0.15;0.45;0.15" dur="2.5s" repeatCount="indefinite"/>
      </polygon>
      <g>
        <line x1="-26" y1="15" x2="26" y2="15" stroke="{a}" stroke-width="1.5"/>
        <animateTransform attributeName="transform" type="rotate" from="0" to="360" dur="20s" repeatCount="indefinite"/>
      </g>
    </g>'''
    if theme == "cipher":
        return f'''
    <g transform="translate(64 64)" filter="url(#soft)">
      <circle r="28" fill="none" stroke="{a}" stroke-width="1.5" stroke-dasharray="8 6">
        <animateTransform attributeName="transform" type="rotate" from="0" to="360" dur="10s" repeatCount="indefinite"/>
      </circle>
      <circle r="18" fill="none" stroke="{a}" stroke-width="1.2" stroke-dasharray="5 5">
        <animateTransform attributeName="transform" type="rotate" from="360" to="0" dur="7s" repeatCount="indefinite"/>
      </circle>
      <text x="0" y="6" text-anchor="middle" font-family="ui-monospace,monospace" font-size="16" fill="{a}">&#x29BF;</text>
    </g>'''
    if theme == "apex":
        bars = ""
        for i in range(5):
            x = 38 + i * 12
            bars += f'<rect x="{x}" y="88" width="8" height="12" fill="{a}" opacity="0.5"><animate attributeName="height" values="12;{28+i*4};12" dur="{1.8+i*0.2}s" repeatCount="indefinite"/><animate attributeName="y" values="88;{72-i*2};88" dur="{1.8+i*0.2}s" repeatCount="indefinite"/></rect>'
        return f'''
    <g filter="url(#soft)">
      <path d="M64 28 L78 88 L50 88 Z" fill="none" stroke="{a}" stroke-width="2"/>
      <circle cx="64" cy="28" r="5" fill="{a}">
        <animate attributeName="cy" values="28;24;28" dur="2.2s" repeatCount="indefinite"/>
      </circle>
      {bars}
    </g>'''
    if theme == "sentinel":
        return f'''
    <g filter="url(#soft)">
      <circle cx="64" cy="64" r="30" fill="none" stroke="{a}" stroke-width="1.5" opacity="0.5"/>
      <line x1="64" y1="64" x2="64" y2="34" stroke="{a}" stroke-width="2.5" stroke-linecap="round">
        <animateTransform attributeName="transform" type="rotate" from="0 64 64" to="360 64 64" dur="4s" repeatCount="indefinite"/>
      </line>
      <circle cx="82" cy="48" r="4" fill="{a}">
        <animate attributeName="opacity" values="0;1;0" dur="4s" repeatCount="indefinite"/>
      </circle>
    </g>'''
    if theme == "oracle":
        runes = "⟨/⟩"
        return f'''
    <g filter="url(#soft)">
      <rect x="36" y="40" width="56" height="48" rx="8" fill="none" stroke="{a}" stroke-width="2"/>
      <text x="64" y="72" text-anchor="middle" font-family="ui-monospace,monospace" font-size="22" fill="{a}">{runes}
        <animate attributeName="opacity" values="0.55;1;0.55" dur="2.4s" repeatCount="indefinite"/>
      </text>
      <line x1="44" y1="52" x2="84" y2="52" stroke="{a}" stroke-width="1" opacity="0.5">
        <animate attributeName="x2" values="84;56;84" dur="3s" repeatCount="indefinite"/>
      </line>
    </g>'''
    if theme == "elite":
        return f'''
    <g filter="url(#soft)">
      <circle cx="64" cy="64" r="36" fill="none" stroke="{a}" stroke-width="1.5" opacity="0.45">
        <animate attributeName="r" values="34;38;34" dur="3.5s" repeatCount="indefinite"/>
      </circle>
      <text x="64" y="58" text-anchor="middle" font-family="system-ui,sans-serif" font-size="18" font-weight="700" fill="{a}">PAX</text>
      <text x="64" y="76" text-anchor="middle" font-family="system-ui,sans-serif" font-size="10" letter-spacing="2" fill="{a}" opacity="0.85">ELITE</text>
      <g transform="translate(64 64)">
        <circle r="2.5" fill="{a}" cx="0" cy="-42">
          <animateTransform attributeName="transform" type="rotate" from="0" to="360" dur="6s" repeatCount="indefinite"/>
        </circle>
      </g>
    </g>'''
    return ""


def render_svg(p: dict) -> str:
    return svg_header(p) + theme_art(p["theme"], p["accent"]) + svg_footer()


def write_labels() -> None:
    entries = "\n".join(
        f"\tarray(\n\t\t'id'    => '{p['id']}',\n\t\t'label' => '{p['label']}',\n\t),"
        for p in PRESETS
    )
    content = f"""<?php
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
"""
    LABELS_FILE.write_text(content, encoding="utf-8")


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    for preset in PRESETS:
        path = OUT_DIR / f"{preset['id']}.svg"
        path.write_text(render_svg(preset), encoding="utf-8")
        print(f"  {path.name}  {path.stat().st_size // 1024:>2} KB  {preset['label']}")
    write_labels()
    print(f"\nGenerated {len(PRESETS)} VIP animated SVG avatars.")


if __name__ == "__main__":
    main()
