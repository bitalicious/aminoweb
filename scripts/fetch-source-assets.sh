#!/usr/bin/env bash
#
# Fetch the original Amino Heaven imagery from the Lovable CDN so the WordPress
# store never depends on Lovable-hosted assets at runtime.
#
# The source repository stores product photography as Lovable "asset pointers"
# (*.asset.json). Those files describe the CDN path; the actual binary is only
# available from the published site. This script resolves every pointer against
# the published origin and writes a normal image file next to it.
#
# Usage: scripts/fetch-source-assets.sh [origin]
#
set -euo pipefail

REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
SOURCE_APP="${REPO_ROOT}/amino-heaven-theme-main"
DEST="${REPO_ROOT}/assets-source"
ORIGIN="${1:-https://amino-heaven-theme.lovable.app}"
MANIFEST="${DEST}/manifest.tsv"

mkdir -p "${DEST}/product-images" "${DEST}/brand"

if [[ ! -d "${SOURCE_APP}/src/assets" ]]; then
  echo "error: source app assets not found at ${SOURCE_APP}/src/assets" >&2
  exit 1
fi

printf 'asset_id\tsource_pointer\tdownloaded_file\n' > "${MANIFEST}"

download() {
  local pointer="$1" outdir="$2"
  local url asset_id filename out

  url="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["url"])' "${pointer}")"
  asset_id="$(python3 -c 'import json,sys; print(json.load(open(sys.argv[1]))["asset_id"])' "${pointer}")"
  filename="$(basename "${url}")"
  out="${outdir}/${filename}"

  if [[ -s "${out}" ]]; then
    echo "  = cached ${filename}"
  else
    echo "  + ${filename}"
    curl -fsS -L --retry 3 --retry-delay 1 -o "${out}.part" "${ORIGIN}${url}"
    mv "${out}.part" "${out}"
  fi

  printf '%s\t%s\t%s\n' "${asset_id}" "${pointer#${REPO_ROOT}/}" "${out#${REPO_ROOT}/}" >> "${MANIFEST}"
}

echo "Fetching brand assets from ${ORIGIN}"
for pointer in "${SOURCE_APP}"/src/assets/*.asset.json; do
  download "${pointer}" "${DEST}/brand"
done

echo "Fetching product imagery"
for pointer in "${SOURCE_APP}"/src/assets/products/*.asset.json; do
  download "${pointer}" "${DEST}/product-images"
done

# Imagery that is committed to the repository as real files (generated variants)
# is copied rather than downloaded.
echo "Copying bundled imagery"
cp -n "${SOURCE_APP}/src/assets/products/GLP3-RT60mg.png" "${DEST}/product-images/" 2>/dev/null || true
cp -n "${SOURCE_APP}/src/assets/amino-heaven-vial-branded.jpg" "${DEST}/brand/" 2>/dev/null || true

echo
echo "Manifest: ${MANIFEST}"
echo "Files:"
ls -1 "${DEST}/brand" "${DEST}/product-images"
