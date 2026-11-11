#!/usr/bin/env bash
# =============================================================================
# setup-env.sh - cree un environnement WordPress isole (SQLite) pour les tests
#
# Usage : setup-env.sh <version-wp> <dossier-cible> <url> [email-admin]
# Exemple:
#   bash setup-env.sh 6.8.12 "$EML_BASE/env/wp-6.8.12" http://127.0.0.1:8092
#
# Variables d'environnement (toutes optionnelles) :
#   WPE_WPCLI       chemin de wp-cli.phar
#   WPE_PHP         binaire PHP utilise pour l'installation (defaut 8.5.8 si dispo)
#   WPE_PHP_85      binaire PHP 8.5
#   WPE_SQLITE_SRC  dossier du plugin sqlite-database-integration
# =============================================================================
set -euo pipefail

VER="$1"
TARGET="$2"
URL="$3"
EMAIL="${4:-admin@example.test}"

WPCLI="${WPE_WPCLI:-/work/wp/wpenv/wp-cli.phar}"
SQLITE_SRC="${WPE_SQLITE_SRC:-/work/wp/wpenv/wordpress/wp-content/plugins/sqlite-database-integration}"
PHP="${WPE_PHP:-}"
if [ -z "$PHP" ]; then
  if [ -x "${WPE_PHP_85:-}" ]; then PHP="$WPE_PHP_85"; else PHP="/work/tools/php/x-8.5.8/php"; fi
fi

if [ -f "$TARGET/wordpress/wp-includes/version.php" ]; then
  echo "[setup] $TARGET existe deja -> rien a faire"
else
  mkdir -p "$TARGET"
  cd "$TARGET"
  echo "[setup] telechargement WordPress $VER ..."
  curl -sS -L -o "wordpress-$VER.zip" "https://downloads.wordpress.org/release/wordpress-$VER.zip"
  unzip -q -o "wordpress-$VER.zip"
  rm -f "wordpress-$VER.zip"
  WPDIR="$TARGET/wordpress"

  # Plugin SQLite + drop-in db.php
  mkdir -p "$WPDIR/wp-content/plugins"
  rm -rf "$WPDIR/wp-content/plugins/sqlite-database-integration"
  cp -a "$SQLITE_SRC" "$WPDIR/wp-content/plugins/sqlite-database-integration"
  cat > "$WPDIR/wp-content/db.php" <<'DROPIN'
<?php
/**
 * Plugin Name: SQLite integration (Drop-in)
 * Version: 1.8.0
 * Author: WordPress Performance Team
 * Author URI: https://make.wordpress.org/performance/
 */
define( 'SQLITE_DB_DROPIN_VERSION', '1.8.0' );
$sqlite_plugin_implementation_folder_path = __DIR__ . '/plugins/sqlite-database-integration';
if ( ! defined( 'DATABASE_TYPE' ) ) { define( 'DATABASE_TYPE', 'sqlite' ); }
if ( ! defined( 'DB_ENGINE' ) ) { define( 'DB_ENGINE', 'sqlite' ); }
require_once $sqlite_plugin_implementation_folder_path . '/wp-includes/sqlite/db.php';
DROPIN

  cat > "$WPDIR/wp-config.php" <<CONFIG
<?php
define( 'DB_NAME', 'wordpress' );
define( 'DB_USER', 'wordpress' );
define( 'DB_PASSWORD', 'wordpress' );
define( 'DB_HOST', 'localhost' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
define( 'DB_DIR', __DIR__ . '/wp-content/database/' );
define( 'DB_FILE', 'wpenv.sqlite' );
define( 'AUTH_KEY',         'wpenv-only-not-a-secret-0000000000000001' );
define( 'SECURE_AUTH_KEY',  'wpenv-only-not-a-secret-0000000000000002' );
define( 'LOGGED_IN_KEY',    'wpenv-only-not-a-secret-0000000000000003' );
define( 'NONCE_KEY',        'wpenv-only-not-a-secret-0000000000000004' );
define( 'AUTH_SALT',        'wpenv-only-not-a-secret-0000000000000005' );
define( 'SECURE_AUTH_SALT', 'wpenv-only-not-a-secret-0000000000000006' );
define( 'LOGGED_IN_SALT',   'wpenv-only-not-a-secret-0000000000000007' );
define( 'NONCE_SALT',       'wpenv-only-not-a-secret-0000000000000008' );
\$table_prefix = 'wp_';
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
define( 'SCRIPT_DEBUG', true );
define( 'WP_ENVIRONMENT_TYPE', 'local' );
define( 'WP_HOME', '$URL' );
define( 'WP_SITEURL', '$URL' );
define( 'DISALLOW_FILE_EDIT', true );
define( 'AUTOMATIC_UPDATER_DISABLED', true );
define( 'WP_AUTO_UPDATE_CORE', false );
if ( ! defined( 'ABSPATH' ) ) { define( 'ABSPATH', __DIR__ . '/' ); }
require_once ABSPATH . 'wp-settings.php';
CONFIG

  mkdir -p "$WPDIR/wp-content/database"
  echo "[setup] installation de WordPress $VER ..."
  "$PHP" -d phar.require_hash=0 "$WPCLI" --allow-root --path="$WPDIR" core install \
      --url="$URL" --title="EML Test $VER" --admin_user=admin \
      --admin_password=admin-pass-123 --admin_email="$EMAIL" --skip-email 2>&1 | tail -3
fi

grep -m1 "wp_version =" "$TARGET/wordpress/wp-includes/version.php" || true
