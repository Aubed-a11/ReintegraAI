#!/bin/bash
# ================================================================
# HorizonAI — Script de configuration Raspberry Pi
# Transforme un Raspberry Pi 4 en borne kiosque physique OIM
#
# Usage :
#   chmod +x setup-raspberry-pi.sh
#   sudo ./setup-raspberry-pi.sh
#
# Pré-requis :
#   - Raspberry Pi OS (Bookworm, 64-bit)
#   - Accès internet pendant l'installation
#   - Token généré depuis l'interface Admin HorizonAI
# ================================================================

set -e

HORIZONAI_URL="${HORIZONAI_URL:-http://votre-serveur-oim.ma}"
DEVICE_TOKEN="${DEVICE_TOKEN:-REMPLACEZ_PAR_LE_TOKEN}"
KIOSK_USER="kiosk"

echo "============================================================"
echo "  HorizonAI — Installation Borne Kiosque OIM"
echo "============================================================"
echo ""
echo "  URL serveur : $HORIZONAI_URL"
echo "  Token       : ${DEVICE_TOKEN:0:8}..."
echo ""

# ── 1. Mise à jour système ────────────────────────────────────
echo "[1/8] Mise à jour du système..."
apt-get update -qq
apt-get upgrade -y -qq

# ── 2. Installer Chromium + utilitaires ──────────────────────
echo "[2/8] Installation de Chromium..."
apt-get install -y -qq chromium-browser unclutter xdotool xscreensaver

# ── 3. Créer l'utilisateur kiosk ─────────────────────────────
echo "[3/8] Création de l'utilisateur kiosk..."
if ! id "$KIOSK_USER" &>/dev/null; then
    useradd -m -s /bin/bash "$KIOSK_USER"
    usermod -aG audio,video,input,plugdev "$KIOSK_USER"
fi

# ── 4. Configurer le démarrage automatique ────────────────────
echo "[4/8] Configuration du démarrage automatique..."
mkdir -p /home/$KIOSK_USER/.config/autostart

cat > /home/$KIOSK_USER/.config/autostart/horizonai-kiosk.desktop << EOF
[Desktop Entry]
Type=Application
Name=HorizonAI Kiosk
Exec=/opt/horizonai/start-kiosk.sh
Hidden=false
NoDisplay=false
X-GNOME-Autostart-enabled=true
EOF

# ── 5. Créer le script de démarrage kiosque ──────────────────
echo "[5/8] Création du script de démarrage..."
mkdir -p /opt/horizonai

cat > /opt/horizonai/start-kiosk.sh << KIOSK_SCRIPT
#!/bin/bash
# HorizonAI — Démarrage mode kiosque

export DISPLAY=:0
KIOSK_URL="${HORIZONAI_URL}/kiosk?token=${DEVICE_TOKEN}"

# Désactiver l'économiseur d'écran
xset s off
xset s noblank
xset -dpms

# Masquer le curseur après 3 secondes d'inactivité
unclutter -idle 3 -root &

# Attendre que le réseau soit disponible (max 30s)
for i in \$(seq 1 30); do
    if ping -c 1 8.8.8.8 &>/dev/null; then
        echo "Réseau disponible"
        break
    fi
    echo "Attente réseau... \$i/30"
    sleep 1
done

# Lancer Chromium en mode kiosque
chromium-browser \
    --kiosk \
    --no-first-run \
    --disable-infobars \
    --disable-session-crashed-bubble \
    --disable-restore-session-state \
    --noerrdialogs \
    --disable-translate \
    --start-maximized \
    --app="\${KIOSK_URL}" \
    --user-data-dir=/tmp/chromium-kiosk \
    --allow-running-insecure-content \
    --use-fake-ui-for-media-stream \
    2>/dev/null

# Si Chromium se ferme, redémarrer après 5 secondes
sleep 5
exec /opt/horizonai/start-kiosk.sh
KIOSK_SCRIPT

chmod +x /opt/horizonai/start-kiosk.sh
chown -R $KIOSK_USER:$KIOSK_USER /opt/horizonai

# ── 6. Service systemd de surveillance ───────────────────────
echo "[6/8] Création du service systemd..."
cat > /etc/systemd/system/horizonai-kiosk.service << EOF
[Unit]
Description=HorizonAI Kiosque OIM
After=network-online.target graphical-session.target
Wants=network-online.target

[Service]
Type=simple
User=$KIOSK_USER
Environment=DISPLAY=:0
ExecStart=/opt/horizonai/start-kiosk.sh
Restart=always
RestartSec=5
StandardOutput=journal
StandardError=journal

[Install]
WantedBy=graphical-session.target
EOF

systemctl daemon-reload
systemctl enable horizonai-kiosk.service

# ── 7. Script de watchdog (redémarrage si Chromium plante) ───
echo "[7/8] Configuration du watchdog..."
cat > /opt/horizonai/watchdog.sh << 'EOF'
#!/bin/bash
# Redémarre Chromium s'il est planté depuis plus de 5 minutes
while true; do
    sleep 300
    if ! pgrep -x "chromium-browse" > /dev/null; then
        systemctl restart horizonai-kiosk.service
    fi
done
EOF
chmod +x /opt/horizonai/watchdog.sh

cat > /etc/systemd/system/horizonai-watchdog.service << EOF
[Unit]
Description=HorizonAI Watchdog
After=horizonai-kiosk.service

[Service]
Type=simple
ExecStart=/opt/horizonai/watchdog.sh
Restart=always

[Install]
WantedBy=multi-user.target
EOF

systemctl daemon-reload
systemctl enable horizonai-watchdog.service

# ── 8. Configuration audio + micro ───────────────────────────
echo "[8/8] Configuration audio..."
apt-get install -y -qq pulseaudio alsa-utils
usermod -aG audio $KIOSK_USER

# Désactiver la veille écran et le screensaver système
cat >> /etc/xdg/lxsession/LXDE-pi/autostart << EOF
@xset s off
@xset -dpms
@xset s noblank
EOF

echo ""
echo "============================================================"
echo "  Installation terminée !"
echo "============================================================"
echo ""
echo "  La borne démarrera automatiquement sur :"
echo "  $HORIZONAI_URL/kiosk?token=${DEVICE_TOKEN:0:8}..."
echo ""
echo "  Commandes utiles :"
echo "  sudo systemctl status horizonai-kiosk"
echo "  sudo systemctl restart horizonai-kiosk"
echo "  sudo journalctl -u horizonai-kiosk -f"
echo ""
echo "  Redémarrez le Raspberry Pi pour activer la borne :"
echo "  sudo reboot"
echo ""
