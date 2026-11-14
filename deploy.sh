#!/bin/bash
#=====================================================
# DEPLOY UPDATE - CBT MAN 2 LOMBOK TIMUR
# Jalankan: ./deploy.sh
# Menarik update dari GitHub dan apply ke site
#=====================================================

set -e

SITE_DIR="/www/wwwroot/cbt.mandualotim.sch.id"
GITHUB_REPO="https://github.com/pmjhaqqulyaqin/cbt.git"

echo "============================================="
echo "  UPDATE CBT MAN 2 LOMBOK TIMUR"
echo "============================================="
echo ""

# Pull perubahan terbaru dari GitHub
echo "[1/3] Pull dari GitHub..."
cd /tmp
rm -rf cbt-update 2>/dev/null
git clone --depth 1 "$GITHUB_REPO" cbt-update
echo "  ✓ Repo terbaru di-download"
echo ""

# Update tema unbk
echo "[2/3] Update tema & plugin..."
# Hapus tema unbk lama, copy yang baru
rm -rf "$SITE_DIR/wp-content/themes/unbk"
cp -r /tmp/cbt-update/wp-content/themes/unbk "$SITE_DIR/wp-content/themes/unbk"
echo "  ✓ Tema unbk diperbarui"

# Hapus plugin lama, copy yang baru
rm -rf "$SITE_DIR/wp-content/plugins/cbt-settings-fix"
cp -r /tmp/cbt-update/wp-content/plugins/cbt-settings-fix "$SITE_DIR/wp-content/plugins/cbt-settings-fix"
echo "  ✓ Plugin cbt-settings-fix diperbarui"

# Cleanup
rm -rf /tmp/cbt-update
echo ""

# Set permissions & restart
echo "[3/4] Set permissions..."
chown -R www:www "$SITE_DIR/wp-content/themes/unbk"
chown -R www:www "$SITE_DIR/wp-content/plugins/cbt-settings-fix"
find "$SITE_DIR/wp-content/themes/unbk" -type d -exec chmod 755 {} \;
find "$SITE_DIR/wp-content/themes/unbk" -type f -exec chmod 644 {} \;
find "$SITE_DIR/wp-content/plugins/cbt-settings-fix" -type d -exec chmod 755 {} \;
find "$SITE_DIR/wp-content/plugins/cbt-settings-fix" -type f -exec chmod 644 {} \;
echo "  ✓ Permissions diperbarui"
echo ""

# Restart PHP-FPM (clear opcache)
echo "[4/4] Restart PHP-FPM..."
/etc/init.d/php-fpm-74 restart 2>/dev/null || service php-fpm-74 restart
echo "  ✓ PHP-FPM restarted (opcache cleared)"
echo ""

echo "============================================="
echo "  ✅ UPDATE SELESAI!"
echo "============================================="
echo "  Site: https://cbt.mandualotim.sch.id/"
echo ""

