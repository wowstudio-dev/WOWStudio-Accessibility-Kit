"""
Builds the WordPress.org icon and banner from the plugin's own mark and palette.

Checked in so these can be regenerated rather than redrawn. Every colour is a
token from assets/src/style.scss and the zigzag is the exact path
assets/src/app.js draws in the admin header — an icon that does not match the
screen it opens is a small lie told at the first moment somebody meets the
plugin, and a hand-drawn copy drifts the moment the palette moves.

Output goes to .wordpress-org/, which .distignore keeps out of the plugin zip:
WordPress.org reads these from the assets/ directory beside the plugin folder
in SVN, not from inside it.

Development-only, and deliberately outside the toolchain the gate runs: it needs
Python with Pillow, and it reads Helvetica Neue from a macOS system path with an
Arial fallback. Nothing in the plugin depends on it.

    python3 bin/make-directory-art.py
"""

import math
from PIL import Image, ImageDraw, ImageFont

OUT = "/Users/w/Documents/projects/WOWStudio Accessibility Kit/.wordpress-org"

LAVENDER = (185, 169, 247)
VIOLET = (123, 92, 240)
INDIGO = (91, 63, 217)
NAVY = (42, 38, 82)
WHITE = (255, 255, 255)

SS = 4  # supersample factor

# The mark, exactly as the admin header draws it, in a 24x24 box.
MARK = [(3, 7.6), (7.2, 16.6), (12, 9.4), (16.8, 16.6), (21, 7.6)]
MARK_STROKE = 2.6


def lerp(a, b, t):
    return tuple(round(a[i] + (b[i] - a[i]) * t) for i in range(3))


def gradient(size, stops, angle_deg):
    """A linear gradient with positioned stops, at an angle in degrees."""
    w, h = size
    img = Image.new("RGB", size)
    px = img.load()
    rad = math.radians(angle_deg)
    dx, dy = math.cos(rad), math.sin(rad)
    # Project every corner so the ramp spans the whole box.
    projections = [x * dx + y * dy for x in (0, w) for y in (0, h)]
    lo, hi = min(projections), max(projections)
    span = (hi - lo) or 1

    for y in range(h):
        for x in range(w):
            t = ((x * dx + y * dy) - lo) / span
            for i in range(len(stops) - 1):
                p0, c0 = stops[i]
                p1, c1 = stops[i + 1]
                if p0 <= t <= p1:
                    local = (t - p0) / ((p1 - p0) or 1)
                    px[x, y] = lerp(c0, c1, local)
                    break
            else:
                px[x, y] = stops[-1][1] if t > stops[-1][0] else stops[0][1]
    return img


def rounded_mask(size, radius):
    mask = Image.new("L", size, 0)
    ImageDraw.Draw(mask).rounded_rectangle(
        (0, 0, size[0] - 1, size[1] - 1), radius=radius, fill=255
    )
    return mask


def draw_mark(draw, box, colour, stroke_scale=1.0):
    """Draws the zigzag inside `box` = (x, y, side), with round caps and joins."""
    x, y, side = box
    unit = side / 24.0
    pts = [(x + px * unit, y + py * unit) for px, py in MARK]
    width = max(1, round(MARK_STROKE * unit * stroke_scale))

    draw.line(pts, fill=colour, width=width, joint="curve")

    # joint="curve" rounds the joins but leaves butt caps; these are the caps.
    r = width / 2
    for cx, cy in (pts[0], pts[-1]):
        draw.ellipse((cx - r, cy - r, cx + r, cy + r), fill=colour)


# Helvetica Neue by index, because the .ttc holds fourteen faces and asking for
# the wrong number silently gives you an oblique one.
FACES = {
    "regular": 0,
    "bold": 1,
    "medium": 10,
    "light": 7,
}


def helvetica(size, weight="bold"):
    try:
        return ImageFont.truetype(
            "/System/Library/Fonts/HelveticaNeue.ttc", size, index=FACES[weight]
        )
    except Exception:
        upright = {
            "bold": "Arial Bold.ttf",
            "medium": "Arial Bold.ttf",
            "regular": "Arial.ttf",
            "light": "Arial.ttf",
        }[weight]
        return ImageFont.truetype(f"/System/Library/Fonts/Supplemental/{upright}", size)


def build_icon(px):
    size = (px * SS, px * SS)
    base = gradient(size, [(0.0, LAVENDER), (0.45, VIOLET), (1.0, INDIGO)], 20)

    layer = Image.new("RGBA", size, (0, 0, 0, 0))
    draw = ImageDraw.Draw(layer)
    side = size[0] * 0.62
    draw_mark(draw, ((size[0] - side) / 2, (size[1] - side) / 2, side), WHITE + (255,), 1.05)

    icon = Image.new("RGBA", size, (0, 0, 0, 0))
    icon.paste(base, (0, 0))
    icon = Image.alpha_composite(icon.convert("RGBA"), layer)
    icon.putalpha(rounded_mask(size, radius=round(size[0] * 0.22)))

    return icon.resize((px, px), Image.LANCZOS)


def build_banner(w, h):
    size = (w * 2, h * 2)
    img = gradient(size, [(0.0, NAVY), (0.55, (58, 48, 112)), (1.0, INDIGO)], 12)
    draw = ImageDraw.Draw(img, "RGBA")

    # A soft wash of the lavender end, kept well away from any text.
    glow = Image.new("RGBA", size, (0, 0, 0, 0))
    gd = ImageDraw.Draw(glow)
    for i in range(60, 0, -1):
        r = size[1] * (0.30 + i * 0.016)
        gd.ellipse(
            (size[0] - r * 0.75, -r * 0.45, size[0] + r * 0.9, size[1] + r * 0.5),
            fill=LAVENDER + (2,),
        )
    img = Image.alpha_composite(img.convert("RGBA"), glow)
    draw = ImageDraw.Draw(img, "RGBA")

    pad = size[1] * 0.20
    tile = size[1] * 0.34

    tile_img = build_icon(round(tile))
    img.paste(tile_img, (round(pad), round((size[1] - tile) / 2)), tile_img)

    x = pad + tile + size[1] * 0.115
    title = helvetica(round(size[1] * 0.180), "bold")
    sub = helvetica(round(size[1] * 0.078), "regular")
    eyebrow = helvetica(round(size[1] * 0.062), "medium")

    # Letter-spaced by hand: PIL has no tracking, and an eyebrow set solid at
    # this size reads as a word rather than as a label.
    ex = x
    for ch in "WOWSTUDIO":
        draw.text((ex, size[1] * 0.250), ch, font=eyebrow, fill=LAVENDER + (255,))
        ex += draw.textlength(ch, font=eyebrow) + size[1] * 0.013

    draw.text((x, size[1] * 0.350), "Accessibility Kit", font=title, fill=WHITE + (255,))
    draw.text(
        (x, size[1] * 0.615),
        "Find, fix and document WCAG issues at the code level",
        font=sub,
        fill=(206, 199, 240, 255),
    )

    return img.convert("RGB").resize((w, h), Image.LANCZOS)


for px in (256, 128):
    build_icon(px).save(f"{OUT}/icon-{px}x{px}.png")
    print(f"icon-{px}x{px}.png")

for w, h in ((1544, 500), (772, 250)):
    build_banner(w, h).save(f"{OUT}/banner-{w}x{h}.png")
    print(f"banner-{w}x{h}.png")
