#!/usr/bin/env python3
"""
Generate the abstract hero images used by the demo content.

These are drawn, not sourced: a stock photo of a boardroom would be a licence
problem the moment the site went live, and the client's own photography
replaces every one of these anyway.
"""

import math
import os
import random

from PIL import Image, ImageDraw, ImageFilter

W, H = 1600, 1200
OUT = os.path.join(os.path.dirname(__file__), "..", "assets", "placeholders")

# Deliberately narrow: everything is drawn from the site palette so the grid
# reads as one system rather than six unrelated pictures.
PALETTES = [
    ("ardent", (14, 35, 56), (34, 66, 96), (168, 118, 58)),
    ("northbank", (18, 46, 52), (38, 84, 90), (196, 152, 92)),
    ("verity", (26, 34, 62), (52, 62, 104), (150, 132, 190)),
    ("halliwell", (46, 32, 40), (86, 58, 66), (200, 148, 110)),
    ("meridian", (12, 40, 44), (28, 76, 78), (140, 178, 168)),
    ("caldwell", (34, 38, 44), (68, 74, 84), (176, 142, 96)),
]


def lerp(a, b, t):
    return tuple(int(round(a[i] + (b[i] - a[i]) * t)) for i in range(3))


def gradient(base, mid):
    img = Image.new("RGB", (W, H), base)
    draw = ImageDraw.Draw(img)
    for y in range(H):
        t = (y / H) ** 1.25
        draw.line([(0, y), (W, y)], fill=lerp(base, mid, t))
    return img


def add_grain(img, amount=6):
    noise = Image.effect_noise((W, H), amount).convert("L")
    return Image.composite(img, Image.blend(img, noise.convert("RGB"), 0.06), Image.new("L", (W, H), 255))


def draw_arcs(img, accent, seed):
    rng = random.Random(seed)
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(layer)

    cx = int(W * rng.uniform(0.55, 0.85))
    cy = int(H * rng.uniform(0.15, 0.45))

    for i in range(7):
        r = int(min(W, H) * (0.22 + i * 0.14))
        alpha = max(22, 112 - i * 13)
        draw.ellipse(
            [cx - r, cy - r, cx + r, cy + r],
            outline=accent + (alpha,),
            width=3,
        )
    return Image.alpha_composite(img.convert("RGBA"), layer).convert("RGB")


def draw_bars(img, accent, seed):
    rng = random.Random(seed + 99)
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(layer)

    n = 9
    slot = W / n
    for i in range(n):
        h = int(H * rng.uniform(0.08, 0.52))
        x0 = int(i * slot + slot * 0.22)
        x1 = int(i * slot + slot * 0.78)
        alpha = int(38 + 58 * (i / n))
        draw.rectangle([x0, H - h, x1, H], fill=accent + (alpha,))
    return Image.alpha_composite(img.convert("RGBA"), layer).convert("RGB")


def draw_grid(img, accent, seed):
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(layer)
    step = 96
    for x in range(0, W + step, step):
        draw.line([(x, 0), (x, H)], fill=accent + (26,), width=1)
    for y in range(0, H + step, step):
        draw.line([(0, y), (W, y)], fill=accent + (26,), width=1)

    rng = random.Random(seed + 7)
    for _ in range(5):
        gx = rng.randrange(0, W // step) * step
        gy = rng.randrange(0, H // step) * step
        draw.rectangle([gx, gy, gx + step, gy + step], fill=accent + (64,))
    return Image.alpha_composite(img.convert("RGBA"), layer).convert("RGB")


def draw_wave(img, accent, seed):
    layer = Image.new("RGBA", (W, H), (0, 0, 0, 0))
    draw = ImageDraw.Draw(layer)
    rng = random.Random(seed + 21)

    for line in range(16):
        pts = []
        base_y = H * (0.25 + line * 0.045)
        amp = H * rng.uniform(0.02, 0.06)
        phase = rng.uniform(0, math.tau)
        for x in range(0, W + 8, 8):
            y = base_y + math.sin(x / W * math.tau * 1.6 + phase) * amp
            pts.append((x, y))
        draw.line(pts, fill=accent + (max(14, 72 - line * 3),), width=2)
    return Image.alpha_composite(img.convert("RGBA"), layer).convert("RGB")


STYLES = [draw_arcs, draw_bars, draw_grid, draw_wave, draw_arcs, draw_bars]


def vignette(img):
    mask = Image.new("L", (W, H), 0)
    draw = ImageDraw.Draw(mask)
    draw.ellipse([-W * 0.25, -H * 0.35, W * 1.25, H * 1.35], fill=255)
    mask = mask.filter(ImageFilter.GaussianBlur(180))
    dark = Image.new("RGB", (W, H), (0, 0, 0))
    return Image.composite(img, Image.blend(img, dark, 0.35), mask)


def main():
    os.makedirs(OUT, exist_ok=True)
    for index, (name, base, mid, accent) in enumerate(PALETTES):
        img = gradient(base, mid)
        img = STYLES[index % len(STYLES)](img, accent, index * 1000 + 3)
        img = vignette(img)
        img = add_grain(img)
        path = os.path.abspath(os.path.join(OUT, f"{name}.jpg"))
        img.save(path, "JPEG", quality=86, optimize=True, progressive=True)
        print(path)


if __name__ == "__main__":
    main()
