#!/bin/bash
set -e

# ------------------------------
# Instalar Calabash
# ------------------------------
CALABASH_VERSION="1.5.7-120"
CALABASH_DIR="/opt/xmlcalabash"

echo "⚙️ Instalando XML Calabash $CALABASH_VERSION..."

wget -O /tmp/xmlcalabash.zip "https://github.com/ndw/xmlcalabash1/releases/download/$CALABASH_VERSION/xmlcalabash-$CALABASH_VERSION.zip"
unzip -q /tmp/xmlcalabash.zip -d "$CALABASH_DIR"
rm /tmp/xmlcalabash.zip

tee /usr/local/bin/calabash >/dev/null <<'EOF'
#!/bin/sh
java -cp "/opt/xmlcalabash/xmlcalabash-1.5.7-120/xmlcalabash-1.5.7-120.jar" com.xmlcalabash.drivers.Main "$@"
EOF

chmod +x /usr/local/bin/calabash
echo "✅ Calabash instalado correctamente."

# ------------------------------
# Instalar Node.js 18 LTS (necesario para Vivliostyle)
# ------------------------------
echo "⚙️ Instalando Node.js 18 LTS..."
curl -fsSL https://deb.nodesource.com/setup_18.x | bash -
apt-get install -y nodejs

# ------------------------------
# Actualizar npm si es necesario
# ------------------------------
NPM_VERSION=$(npm -v)
NPM_MAJOR=$(echo "$NPM_VERSION" | cut -d. -f1)
if [ "$NPM_MAJOR" -lt 8 ]; then
    echo "Actualizando npm (versión $NPM_VERSION detectada)..."
    npm install -g npm@latest
fi

echo "✅ Node.js versión: $(node -v)"
echo "✅ npm versión: $(npm -v)"

# ------------------------------
# Instalar Vivliostyle CLI
# ------------------------------
echo "📙 Instalando Vivliostyle CLI..."
npm install -g @vivliostyle/cli
echo "✅ Vivliostyle CLI instalado correctamente."

# ------------------------------
# Instalar dependencias de Chromium
# ------------------------------
echo "📦 Instalando dependencias del sistema necesarias para Chromium..."
apt-get install -y \
    libnss3 libxss1 libasound2 libatk1.0-0 libatk-bridge2.0-0 libcups2 libdrm2 libxkbcommon0 libgbm1 \
    libgtk-3-0 libx11-xcb1 libxcb-dri3-0 libxcomposite1 libxdamage1 libxrandr2 libpangocairo-1.0-0 \
    libpango-1.0-0 libxshmfence1 libwayland-client0 libwayland-cursor0 libwayland-egl1 \
    libcurl4 libwoff1 libharfbuzz0b libjpeg-turbo8 libpng16-16 libwebp6
echo "✅ Dependencias instaladas."
