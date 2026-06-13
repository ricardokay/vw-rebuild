# Vancouver Weekly: Visual Design Brief for Theme Build

## Purpose

This brief specifies the visual system for the Vancouver Weekly Newspack child theme, as built and approved in the design preview. The direction is clean, classic editorial: off-white pages, sturdy bold serif headlines, a single red accent, and an uncluttered line-free layout. It is built as Vancouver Weekly's own identity. Do not replicate another publication's layout, type pairing, or overall composition.

This document supersedes all earlier color and typography directions (the four-color festival palette, the cyan accent, the per-section duotone filters, the full-bleed section backgrounds, and the Instrument Serif / Space Grotesk / Cormorant / Fraunces explorations). Those were iterated through and dropped. What is below is the current, agreed design.

## 1. Typography

- Headlines and titles: PT Serif Bold (weight 700), near-black. Used for the masthead wordmark, the large section titles, feature headlines, and article headlines. Sturdy and plain, not high-contrast or decorative.
- Body and metadata: Inter (400 regular, 500/600 for emphasis).
- Self-host both as woff2 in the child theme. Both are OFL licensed. Do not hot-link from a font CDN.

Note: the masthead currently uses PT Serif Bold as a placeholder wordmark. The intent is to replace it with the real Vancouver Weekly logo asset (ideally SVG) when available.

## 2. Color

A single accent, no section-specific colors.

- Accent red: #C41230. Used for kickers, section labels, links, and breaking/featured flags.
- Verified: white text on the red scores 6.0:1 (works as a filled badge); red as text or links on white scores 6.0:1; on the off-white page background it scores 5.6:1. All pass WCAG 2.1 AA.
- Register as a CSS custom property (e.g. `--vw-red`) and reference it everywhere. Same for the ink and background values below.

Backgrounds and ink:

- Page background: off-white #F7F6F4.
- White #FFFFFF for cards and surfaces where separation from the page helps.
- Text: near-black ink (the theme's `--vw-ink`), with muted gray for secondary metadata.

## 3. Layout and structure

- Clean and line-free. No divider lines between rows, no heavy rules, no full-bleed color blocks. At most a single thin hairline only where genuinely needed.
- Off-white page throughout; color appears only as the red accent in small, deliberate places (kickers, tags, links, flags).

Top navigation:

- Bigger nav: taller bar, generous horizontal padding, larger nav text.
- Same off-white background as the body, with no border or divider between the nav and the content, so it blends seamlessly into the page.
- Holds the masthead wordmark and the four section links, with the red accent on the active item.

## 4. Cards (article grid)

- Internal padding on all sides so content is not jammed against the image or card edge (approx. 16px top / 18px sides / 20px bottom on the body).
- Subtle border: 1px solid #E8E8E8 with a 4px corner radius. Just enough to define the card edge without weight. The image bleeds to the card edges with the corners clipped to the radius.
- Kicker: the category in red caps, letterspaced, above the headline.
- Headline: PT Serif Bold, near-black.
- Byline hierarchy: the author NAME is emphasized (weight 600, near-black ink); the "By" and the date stay muted gray. The name reads as the named element.

## 5. Images

- Show images in natural full color. No duotone treatment in the current design.
- Open item / fallback option: the recovered archive (2,789 posts from the Wayback Machine) has inconsistent source image quality and color. If that inconsistency looks rough once real images are placed, a single-color treatment (a subtle red or grayscale duotone) can be reintroduced later as a unifying fix. It is not part of the current design.
- For very low-resolution recovered images, prefer a type-forward card (headline only, no stretched image) over upscaling.

## 6. Photography section note

When the Photography section is built, its gallery should preserve full images at native aspect ratio (no forced crop) and run in full original color. This was a constant across earlier directions and still holds.

## 7. What to avoid

- Do not reintroduce the four-color section palette, the cyan accent, full-bleed color backgrounds, or the duotone filters unless deliberately decided.
- Do not use Fraunces, Cormorant, Instrument Serif, or Space Grotesk. Headlines are PT Serif Bold.
- Do not add divider lines between rows or a border between the nav and the body.
- Do not put the red on large areas; it is a small accent only.
- Do not hardcode hex values in templates; use the CSS custom properties.

## Status and next steps

- Built and approved on the A La Music section landing and the single article view, as a static preview in the child theme (theme/assets/css). Committed and pushed to GitHub (ricardokay/vw-rebuild, main).
- Next: apply this same system to the other three sections (Photography, Food & Drink, Out n About), then wire the templates into the actual WordPress (Newspack child theme).
- The real Vancouver Weekly logo still needs to be supplied for the masthead.
