#!/usr/bin/env bash
# Baut das Installationspaket für Webspaces: Quellcode + vendor (ohne Entwicklerwerkzeuge).
# Aufruf: scripts/release.sh [Ausgabeordner]   Ergebnis: nova-cms-<version>.zip
set -euo pipefail

root="$(cd "$(dirname "$0")/.." && pwd)"
out="${1:-$root/dist}"
version="$(sed -n "s/.*'version' => '\([^']*\)'.*/\1/p" "$root/config/nova.php" | head -1)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

mkdir -p "$out" "$work/nova-cms"
# Nur eingecheckte Dateien (inkl. public/build), ohne Tests und Entwicklerdateien
git -C "$root" archive HEAD | tar -x -C "$work/nova-cms"
cd "$work/nova-cms"
rm -rf tests .github scripts phpunit.xml .editorconfig .styleci.yml .gitattributes package.json package-lock.json vite.config.js .npmrc resources/js resources/css
composer install --prefer-dist --no-dev --optimize-autoloader --no-interaction --no-progress --quiet
find vendor -type d \( -name .git -o -name tests -o -name Tests -o -name docs \) -prune -exec rm -rf {} +
# Beim Paket-Erkennen startet Laravel einmal und legt eine .env mit Schlüssel an: die darf nicht ins Paket,
# sonst hätten alle Installationen denselben geheimen Schlüssel.
rm -f .env
find storage -type f ! -name .gitignore -delete

zip="$out/nova-cms-$version.zip"
rm -f "$zip"
(cd "$work" && zip -qr -9 "$zip" nova-cms)
echo "$zip ($(du -h "$zip" | cut -f1))"
