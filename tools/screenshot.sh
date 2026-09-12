#!/usr/bin/env bash
#
# Headless screenshot with a fixed write path.
#
# The bare Chrome invocation takes --screenshot=<any path>, which is why it was
# kept out of the read-only allowlist: no prefix pattern can constrain where it
# writes. This wrapper does what the pattern cannot — the output directory is
# hardcoded here and the caller supplies only a filename leaf, so allowlisting
# tools/screenshot.sh grants exactly one writable directory rather than the
# whole filesystem.
#
#   tools/screenshot.sh <url> <width> <leaf.png>
#
set -euo pipefail

readonly OUT_DIR="$HOME/vw-screenshots"
readonly CHROME="/Applications/Google Chrome.app/Contents/MacOS/Google Chrome"
readonly HEIGHT=4000

if [ "$#" -ne 3 ]; then
	echo "usage: tools/screenshot.sh <url> <width> <leaf.png>" >&2
	exit 64
fi

url=$1
width=$2
leaf=$3

case $url in
	http://*|https://*) ;;
	*) echo "refusing: url must be http:// or https:// — got '$url'" >&2; exit 65 ;;
esac

case $width in
	''|*[!0-9]*) echo "refusing: width must be a positive integer — got '$width'" >&2; exit 65 ;;
esac
if [ "$width" -lt 100 ] || [ "$width" -gt 5000 ]; then
	echo "refusing: width must be 100-5000 — got '$width'" >&2
	exit 65
fi

# The whole point of the wrapper: the leaf may not escape OUT_DIR.
case $leaf in
	*/*)   echo "refusing: filename must be a leaf, no slashes — got '$leaf'" >&2; exit 65 ;;
	.*)    echo "refusing: filename must not start with a dot — got '$leaf'" >&2; exit 65 ;;
	-*)    echo "refusing: filename must not start with a dash — got '$leaf'" >&2; exit 65 ;;
	*..*)  echo "refusing: filename must not contain '..' — got '$leaf'" >&2; exit 65 ;;
	*.png) ;;
	*)     echo "refusing: filename must end in .png — got '$leaf'" >&2; exit 65 ;;
esac

if [ ! -x "$CHROME" ]; then
	echo "refusing: Chrome not found at $CHROME" >&2
	exit 69
fi

mkdir -p "$OUT_DIR"
dest="$OUT_DIR/$leaf"

"$CHROME" --headless --disable-gpu --hide-scrollbars \
	--virtual-time-budget=6000 \
	--window-size="${width},${HEIGHT}" \
	--screenshot="$dest" \
	"$url" >/dev/null 2>&1

if [ ! -s "$dest" ]; then
	echo "failed: no image written to $dest" >&2
	exit 70
fi

echo "$dest"
