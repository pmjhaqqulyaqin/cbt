#!/bin/bash
#=====================================================
# DEPLOY CLEAN - CBT MAN 2 LOMBOK TIMUR
# Jalankan di terminal hosting (SSH root@76.13.21.14)
#=====================================================

set -e

SITE_DIR="/www/wwwroot/cbt.mandualotim.sch.id"
BACKUP_DIR="/www/backup/cbt-backup-$(date +%Y%m%d-%H%M%S)"
GITHUB_REPO="https://github.com/pmjhaqqulyaqin/cbt.git"
WP_VERSION="6.7.1"  # WordPress terbaru yang stabil

echo "============================================="
echo "  DEPLOY CLEAN CBT MAN 2 LOMBOK TIMUR"
echo "============================================="
echo ""

# ---- STEP 1: Backup yang lama ----
echo "[1/7] Backup file & database lama..."
mkdir -p "$BACKUP_DIR"

# Backup wp-config.php (penting! berisi credentials DB)
if [ -f "$SITE_DIR/wp-config.php" ]; then
    cp "$SITE_DIR/wp-config.php" "$BACKUP_DIR/wp-config.php.bak"
    echo "  ✓ wp-config.php di-backup"
fi

# Backup database
DB_NAME=$(grep "DB_NAME" "$SITE_DIR/wp-config.php" 2>/dev/null | cut -d"'" -f4 || echo "")
DB_USER=$(grep "DB_USER" "$SITE_DIR/wp-config.php" 2>/dev/null | cut -d"'" -f4 || echo "")
DB_PASS=$(grep "DB_PASSWORD" "$SITE_DIR/wp-config.php" 2>/dev/null | cut -d"'" -f4 || echo "")
DB_HOST=$(grep "DB_HOST" "$SITE_DIR/wp-config.php" 2>/dev/null | cut -d"'" -f4 || echo "localhost")

if [ -n "$DB_NAME" ] && [ -n "$DB_USER" ]; then
    mysqldump -u"$DB_USER" -p"$DB_PASS" -h"$DB_HOST" "$DB_NAME" > "$BACKUP_DIR/database.sql" 2>/dev/null && \
    echo "  ✓ Database '$DB_NAME' di-backup" || \
    echo "  ⚠ Gagal backup database (lanjut saja)"
fi

# Backup uploads jika ada
if [ -d "$SITE_DIR/wp-content/uploads" ]; then
    cp -r "$SITE_DIR/wp-content/uploads" "$BACKUP_DIR/uploads" 2>/dev/null && \
    echo "  ✓ uploads/ di-backup" || \
    echo "  ⚠ Gagal backup uploads"
fi

echo "  Backup tersimpan di: $BACKUP_DIR"
echo ""

# ---- STEP 2: Bersihkan folder site ----
echo "[2/7] Membersihkan folder site..."
# Simpan .user.ini dan .htaccess jika ada (konfigurasi aaPanel)
[ -f "$SITE_DIR/.user.ini" ] && cp "$SITE_DIR/.user.ini" "$BACKUP_DIR/.user.ini.bak"
[ -f "$SITE_DIR/.htaccess" ] && cp "$SITE_DIR/.htaccess" "$BACKUP_DIR/.htaccess.bak"

# Hapus semua isi folder site
rm -rf "$SITE_DIR"/*
rm -rf "$SITE_DIR"/.git 2>/dev/null
rm -rf "$SITE_DIR"/.gitignore 2>/dev/null
echo "  ✓ Folder site dibersihkan"
echo ""

# ---- STEP 3: Download WordPress core ----
echo "[3/7] Download WordPress $WP_VERSION..."
cd /tmp
rm -f wordpress-${WP_VERSION}.tar.gz 2>/dev/null
wget -q "https://wordpress.org/wordpress-${WP_VERSION}.tar.gz" -O wordpress-${WP_VERSION}.tar.gz
echo "  ✓ Download selesai"

# Extract ke site directory
echo "  Extracting..."
tar -xzf wordpress-${WP_VERSION}.tar.gz
cp -r wordpress/* "$SITE_DIR/"
rm -rf wordpress wordpress-${WP_VERSION}.tar.gz
echo "  ✓ WordPress core terinstall"
echo ""

# ---- STEP 4: Clone repo GitHub (tema + plugin) ----
echo "[4/7] Clone repo dari GitHub..."
cd /tmp
rm -rf cbt-repo 2>/dev/null
git clone "$GITHUB_REPO" cbt-repo
echo "  ✓ Repo di-clone"

# Copy tema unbk (replace default themes kecuali yang dibutuhkan)
echo "  Copying tema unbk..."
cp -r /tmp/cbt-repo/wp-content/themes/unbk "$SITE_DIR/wp-content/themes/unbk"
echo "  ✓ Tema unbk terinstall"

# Copy plugin cbt-settings-fix
echo "  Copying plugin cbt-settings-fix..."
cp -r /tmp/cbt-repo/wp-content/plugins/cbt-settings-fix "$SITE_DIR/wp-content/plugins/cbt-settings-fix"
echo "  ✓ Plugin cbt-settings-fix terinstall"

# Cleanup
rm -rf /tmp/cbt-repo
echo ""

# ---- STEP 5: Restore wp-config.php ----
echo "[5/7] Restore wp-config.php..."
if [ -f "$BACKUP_DIR/wp-config.php.bak" ]; then
    cp "$BACKUP_DIR/wp-config.php.bak" "$SITE_DIR/wp-config.php"
    echo "  ✓ wp-config.php di-restore dari backup"
else
    echo "  ⚠ wp-config.php tidak ditemukan di backup"
    echo "  → Anda perlu konfigurasi manual via browser: https://cbt.mandualotim.sch.id/wp-admin/setup-config.php"
fi

# Restore uploads jika ada
if [ -d "$BACKUP_DIR/uploads" ]; then
    cp -r "$BACKUP_DIR/uploads" "$SITE_DIR/wp-content/uploads"
    echo "  ✓ uploads/ di-restore"
fi

# Restore .user.ini dan .htaccess
[ -f "$BACKUP_DIR/.user.ini.bak" ] && cp "$BACKUP_DIR/.user.ini.bak" "$SITE_DIR/.user.ini"
[ -f "$BACKUP_DIR/.htaccess.bak" ] && cp "$BACKUP_DIR/.htaccess.bak" "$SITE_DIR/.htaccess"
echo ""

# ---- STEP 6: Setup Git di folder site (untuk deploy.sh nanti) ----
echo "[6/7] Setup Git repo di folder site..."
cd "$SITE_DIR"
git init
git remote add origin "$GITHUB_REPO"
git config --global --add safe.directory "$SITE_DIR"
echo "  ✓ Git initialized"
echo ""

# ---- STEP 7: Set permissions ----
echo "[7/7] Set permissions..."
chown -R www:www "$SITE_DIR"
find "$SITE_DIR" -type d -exec chmod 755 {} \;
find "$SITE_DIR" -type f -exec chmod 644 {} \;
chmod 600 "$SITE_DIR/wp-config.php" 2>/dev/null
echo "  ✓ Permissions di-set (www:www)"
echo ""

# ---- DONE ----
echo "============================================="
echo "  ✅ DEPLOY CLEAN SELESAI!"
echo "============================================="
echo ""
echo "  Site URL : https://cbt.mandualotim.sch.id/"
echo "  Admin    : https://cbt.mandualotim.sch.id/wp-admin/"
echo "  Backup   : $BACKUP_DIR"
echo ""
echo "  NEXT STEPS:"
echo "  1. Buka https://cbt.mandualotim.sch.id/wp-admin/"
echo "  2. Appearance → Themes → Aktifkan tema 'unbk'"
echo "  3. Plugins → Aktifkan 'CBT Settings Fix'"
echo "  4. Buat deploy.sh untuk update selanjutnya"
echo ""

