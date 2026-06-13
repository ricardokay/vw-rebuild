# Vancouver Weekly: Visual Design Brief for Theme Build

## Purpose

This brief specifies the visual system for the Vancouver Weekly Newspack child theme. The direction is festival-fun at a mid-saturation level: candy-bright section colors that are punchier than pastel but short of neon, used full-bleed, with an electric cyan system accent. It draws on principles seen in modern editorial sites (immersive section color, duotone photography, expressive display type) but is built as Vancouver Weekly's own system. Do not replicate any other publication's layouts, type pairings, or brand presentation. The goal is a site that is unmistakably its own title.

## 1. Section color palette (mid-saturation, verified)

Each section gets two colors with defined roles. The field color is the bright background. The deep ink is a darker version of the same hue for use where the field color would fail (colored text, labels, and links on white).

| Section | Field (background) | Text on field | Field contrast | Deep ink (text/links on white) | Ink contrast |
|---|---|---|---|---|---|
| A La Music | #FD8FCE bubblegum pink | near-black ink #1A161E | 8.5:1 | #9A2390 | 7.0:1 |
| Photography | #EDF674 chartreuse-yellow | near-black ink #1A161E | 15.3:1 | #6B6410 | 6.1:1 |
| Food & Drink | #FF9E76 coral | near-black ink #1A161E | 8.8:1 | #B5471A | 5.4:1 |
| Out n About | #AA84F2 lavender | near-black ink #1A161E | 6.2:1 | #5A1FC0 | 8.8:1 |

All combinations pass WCAG 2.1 AA for normal text.

The role rule, which must be enforced in the theme:

- All four fields carry near-black ink text only. White text fails on every field and must never be used on one.
- Anywhere a section color appears as text, a label, or a link on a white or light background, use the deep ink value, never the field value. The field colors fail as small text on white.

Register both values per section as CSS custom properties (for example `--vw-music-field` / `--vw-music-ink`) and reference them everywhere rather than hardcoding hex.

## 2. System accent (electric cyan)

A single cyan, separate from the four section fields, used site-wide for emphasis. It is the one cool color in an otherwise warm palette, so it reads as emphasis without competing with any section. It is not tied to a section.

- Accent field: #1FD6E0
- Accent ink: #0A7C8A
- Use the field for: breaking and featured flags, new or deal tags, and campaign microsite theming (World Cup first). Use the ink for: cyan text, links, and call-to-action color on white.
- Role rule, same as the section fields: the cyan field is light, so text on a cyan badge is dark (use a very dark teal such as #0A3034, not pure black). White text fails on the cyan field and must never be used. For cyan text or links on white, use the ink value #0A7C8A, never the bright field.
- Verified: dark text on the field 10.0:1; ink on white 4.9:1.
- Register as `--vw-accent-field` and `--vw-accent-ink`.

If a fifth section is ever added, this cyan can be promoted to a section field, keeping the dark-text rule and its existing ink.

## 3. Full-bleed section landing pages

Each section landing page uses its field color as a full-bleed page background, with near-black ink text on all four. Article cards within a section page sit on the colored background; give cards a white or near-white fill so card content stays legible against the field. The accent cyan may appear on these pages as flags or tags, never as a competing background.

The homepage stays neutral (off-white) so that section color does the work of signalling where you are once you navigate in.

## 4. Duotone image treatment (core of the system)

This is the most important decision for the recovered archive. The 2,789 posts pulled from the Wayback Machine have inconsistent source image quality, dimensions, and color. A per-section duotone treatment unifies the entire archive visually and masks quality problems in low-res or poorly-colored source images.

The duotone is two-tone: dark pixels take the section shadow color, light pixels take a bright tint of the section field. This keeps each section's hue identity while producing a consistent poster look.

Per-section duotone ramps:

| Section | Shadow (dark end) | Highlight (light end) |
|---|---|---|
| A La Music | #641B5F | #FDBADD |
| Photography | #474212 | #F3F8A7 |
| Food & Drink | #743118 | #FEC3A8 |
| Out n About | #3D197C | #CBB3F2 |

Implementation: use an SVG `<filter>` per section combining `feColorMatrix` (desaturate the source to luminance) with `feComponentTransfer` (map the luminance ramp to the shadow and highlight colors). Apply via a CSS class on archive card thumbnails and section hero images. The SVG filter approach is preferred over a flat colored overlay because it produces a true tonal map and handles bad source color far better.

Apply duotone to: archive card thumbnails, section hero images, and feature lead images outside the Photography section.

## 5. Image fallback ladder

The duotone treatment hides a lot, but not everything. Apply this ladder based on the recovered source image's longest dimension:

1. Source >= 1200px: eligible for full-bleed lead, feature hero, or large card. Apply section duotone.
2. Source 600px to 1199px: card thumbnail and section hero only, never a full-bleed lead. Apply section duotone.
3. Source < 600px: do not display the image at large size. Use a type-forward card instead: the section field color as a solid block with the headline set in large display type (near-black ink), no image. This is a deliberate design state, not an error state.
4. No usable image at all: same type-forward treatment as case 3.

Build the type-forward fallback as a reusable card variant.

## 6. Photography section exception

The Photography section is the one place where images are the content, not decoration. The self-hosted masonry gallery in this section must:

- Preserve full images at native aspect ratio. No forced crop.
- Not apply the duotone filter. Photography runs in full original color.

The Photography field color (#EDF674) and its duotone ramp still apply to the section landing page chrome, navigation, and any non-gallery cards, but never to the gallery images themselves.

## 7. Typography

A two-face editorial system: a dramatic high-contrast serif mixed with a cool grotesque, plus a clean body face. All three are OFL licensed and self-hosted as woff2 in the child theme. Do not hot-link from a font CDN.

Roles, which must be applied consistently:

- Masthead / wordmark: the real Vancouver Weekly logo asset (supplied separately, ideally as SVG). The display serif below is not used for the wordmark.
- Display serif, Instrument Serif: section names and large editorial display moments. Set large and high-contrast, in the Mic "section name" register. This is where the serif drama lives.
- Headline grotesque, Space Grotesk (bold, 700): article headlines, kickers, and UI labels. This carries the cool, music-press confidence.
- Body, Inter (400 regular, 500 for emphasis): article body, metadata, captions, navigation.

Color usage in type (color should appear in several places, not only as backgrounds):

- Section names are set in their own deep ink color, not boxed in pills: Music #9A2390, Photography #6B6410, Food & Drink #B5471A, Out n About #5A1FC0.
- Kickers and section labels above headlines are set in Space Grotesk caps with wide letterspacing, in the cyan accent ink #0A7C8A, or in the relevant section deep ink when inside a section.
- A single headline word may be highlighted with a section field color block behind near-black text (for example one word on #EDF674). Use this sparingly, one highlight per headline at most.

Keep heavy uppercase treatments selective. Bold display weight is for headlines and section moments, not for long runs of text.

## 8. What to avoid

- Do not replicate another publication's layout structure, type pairing, color usage, or overall composition.
- Do not put white text on any section field, or on the cyan accent field. Near-black ink on section fields; dark teal text on cyan badges.
- Do not use a section field color as small text or links on white. Use the deep ink value.
- Do not let the accent cyan become a section background; it is an emphasis color only.
- Do not apply duotone to the Photography gallery.
- Do not stretch or upscale sub-600px recovered images. Use the type-forward fallback.
- Do not hardcode section or accent hex values in templates. Use the CSS custom properties.

## Implementation order

1. Register section field colors, deep inks, the accent cyan, and duotone ramps as CSS custom properties and SVG filters in the child theme.
2. Build the full-bleed section landing template, enforcing the near-black-text role rule.
3. Build the three card variants: standard (duotone image), large/hero (duotone image), type-forward (no image).
4. Wire the fallback ladder to the recovered image dimensions during template rendering.
5. Build the Photography masonry gallery as the documented exception.
6. Add the accent-cyan badge, flag, and link components.
7. Self-host Instrument Serif, Space Grotesk, and Inter, apply them per the role table, and place the supplied Vancouver Weekly logo in the masthead.
