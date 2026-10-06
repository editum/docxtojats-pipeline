#!/usr/bin/env bash
set -euo pipefail

# TODO capture errors
# TODO trap to clean files in case of error

# === CONFIGURACIÓN ===
ROOT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
JATS2HTML_XPL="$ROOT_DIR/stylesheets/resources/JATSPreviewStylesheets/shells/xproc/jats-APAcit-html.xpl"
THEME_DIR="$ROOT_DIR/stylesheets/themes/default"

# === ARGUMENTOS ===
if [ $# -ne 1 ]; then
  echo "Uso: $0 archivo.jats.xml"
  exit 1
fi

INPUT_XML="$(readlink -f "$1")"
OUTDIR="$(dirname "$INPUT_XML")"

# === COPIAR RECURSOS DEL TEMA ===
echo "[INFO] Copiando recursos de tema a $OUTDIR ..."
cp -ar "$THEME_DIR/." "$OUTDIR/"

# === CONVERSIÓN JATS -> HTML ===
echo "[INFO] Convirtiendo $INPUT_XML a HTML ..."
calabash \
    -p css="style.css" \
    -i source="$INPUT_XML" \
    -o result="$OUTDIR/article.html" \
    "$JATS2HTML_XPL"
[ $? -eq 0 ] || exit 1

# === ACTUALIZAR CONFIG DE VIVLIOSTYLE ===
echo "[INFO] Ajustando configuración de Vivliostyle ..."
# TODO parsear el vivliostyle.config.js para ajustar language, title y otros si es necesario

# === HTML -> PDF con Vivliostyle ===
echo "[INFO] Generando PDF ..."
cd "$OUTDIR"
vivliostyle build
[ $? -eq 0 ] || exit 1

echo "[OK] Conversión completada:"
echo " - HTML: $OUTDIR/article.html"
echo " - PDF : $OUTDIR/article.pdf"
