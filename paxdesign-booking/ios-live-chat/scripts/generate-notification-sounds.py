#!/usr/bin/env python3
"""Generate short bundled notification tones for APNs + in-app playback."""

from __future__ import annotations

import math
import struct
import wave
from pathlib import Path

OUT = Path(__file__).resolve().parent.parent / "PAXDesignLiveChat" / "Resources" / "Sounds"
OUT.mkdir(parents=True, exist_ok=True)

# (filename, freq_hz, duration_s, volume, optional second beep)
SPECS = {
    "pax-message.wav": (880, 0.18, 0.32, None),
    "pax-live-request.wav": (660, 0.42, 0.38, 880),
    "pax-ai-alert.wav": (990, 0.22, 0.34, 1240),
    "pax-send.wav": (720, 0.10, 0.22, None),
    "pax-typing.wav": (540, 0.08, 0.16, None),
}


def write_tone(path: Path, freq: float, duration: float, volume: float, second: float | None) -> None:
    rate = 44100
    frames = int(rate * duration)
    with wave.open(str(path), "w") as handle:
        handle.setnchannels(1)
        handle.setsampwidth(2)
        handle.setframerate(rate)
        for index in range(frames):
            envelope = min(1.0, index / (rate * 0.012), (frames - index) / (rate * 0.04))
            t = index / rate
            sample = math.sin(2 * math.pi * freq * t)
            if second is not None and t > duration * 0.45:
                sample = math.sin(2 * math.pi * second * t)
            value = int(32767 * volume * envelope * sample)
            handle.writeframes(struct.pack("<h", value))


def main() -> None:
    for name, (freq, duration, volume, second) in SPECS.items():
        write_tone(OUT / name, freq, duration, volume, second)
        print(f"Wrote {OUT / name}")


if __name__ == "__main__":
    main()
