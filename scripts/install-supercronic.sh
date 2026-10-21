#!/bin/bash
set -euo pipefail
# Hashes publicados por Aptible en la release v0.2.33:
# https://github.com/aptible/supercronic/releases/tag/v0.2.33
case "$(uname -m)" in
  x86_64) arch=amd64; checksum=71b0d58cc53f6bd72cf2f293e09e294b79c666d8 ;;
  aarch64|arm64) arch=arm64; checksum=e0f0c06ebc5627e43b25475711e694450489ab00 ;;
  *) echo 'Arquitectura no soportada' >&2; exit 1 ;;
esac
target_dir=${1:?Uso: scripts/install-supercronic.sh DIRECTORIO_BIN}
mkdir -p "$target_dir"
tmp=$(mktemp "$target_dir/.supercronic.XXXXXX")
trap 'rm -f "$tmp"' EXIT
curl --fail --silent --show-error --location "https://github.com/aptible/supercronic/releases/download/v0.2.33/supercronic-linux-$arch" --output "$tmp"
printf '%s  %s\n' "$checksum" "$tmp" | sha1sum --check --status
chmod 755 "$tmp"
mv "$tmp" "$target_dir/supercronic"
echo 'Supercronic instalado y checksum verificado.'
