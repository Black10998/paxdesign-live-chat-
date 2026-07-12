#!/usr/bin/env python3
"""Generate short bundled notification tones for APNs + in-app playback."""

from __future__ import annotations

import math
import struct
import wave
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "PAXDesignLiveChat" / "Resources" / "Sounds"
OUT.mkdir(parents=True, exist_ok=True)

SPECS = {
    "pax-message.wav": (880, 0.18, 0.28),
    "pax-live-request.wav": (660, 0.24, 0.34),
    "pax-ai-alert.wav": (990, 0.16, 0.32),
}


def write_tone(path: Path, freq: float, duration: float, volume: float) -> None:
    rate = 44100
    frames = int(rate * duration)
    with wave.open(str(path), "w") as handle:
        handle.setnchannels(1)
        handle.setsampwidth(2)
        handle.setframerate(rate)
        for index in range(frames):
            envelope = min(1.0, index / (rate * 0.02), (frames - index) / (rate * 0.05))
            sample = int(32767 * volume * envelope * math.sin(2 * math.pi * freq * index / rate))
            handle.writeframes(struct.pack("<h", sample))


def main() -> None:
    for name, (freq, duration, volume) in SPECS.items():
        write_tone(OUT / name, freq, duration, volume)
        print(f"Wrote {OUT / name}")


if __name__ == "__main__":
    main()
