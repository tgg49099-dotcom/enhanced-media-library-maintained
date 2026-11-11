#!/usr/bin/env bash
# =============================================================================
# lib.sh - helpers du harnais de test EML (Enhanced Media Library)
# =============================================================================
BASE=${EML_BASE:-/work/wp/reprise/eml}
WPCLI=${EML_WPCLI:-/work/wp/wpenv/wp-cli.phar}
SLUG=enhanced-media-library

phpbin() {
  case "$1" in
    8.3.29) echo "${EML_PHP_83:-/work/tools/php/x-8.3.29/php}" ;;
    8.5.8)  echo "${EML_PHP_85:-/work/tools/php/x-8.5.8/php}"  ;;
    *) echo "version PHP inconnue: $1" >&2; return 2 ;;
  esac
}

# wp <envdir> <phpver> <args...>
# Les lignes de diagnostic PHP (notamment les "Deprecated" du COEUR de
# WordPress sur PHP 8.5, ex. imagedestroy()) sont retirees de la sortie : elles
# ne font pas partie de la sortie de la commande et pollueraient la lecture
# des identifiants renvoyes par wp-cli.
wp() {
  local envdir="$1"; shift
  local phpv="$1"; shift
  "$(phpbin "$phpv")" -d phar.require_hash=0 -d display_errors=1 -d error_reporting=E_ALL \
    "$WPCLI" --allow-root --path="$envdir/wordpress" "$@" 2>&1 \
    | grep -vE '(Deprecated|Warning|Notice|Fatal error|Parse error)[[:space:]]*:' \
    | grep -v 'phar:///work/wp/wpenv/wp-cli.phar'
  return "${PIPESTATUS[0]}"
}

# Ne garde que la derniere ligne purement numerique (identifiant renvoye par
# wp-cli --porcelain).
lastid() { grep -oP '^\d+$' | tail -1; }

env_port() {
  case "$1" in
    wp-7.1.4)  echo 8091 ;;
    wp-6.8.12) echo 8092 ;;
    *) echo "env inconnu: $1" >&2; return 2 ;;
  esac
}

# Reset complet d'un environnement : base SQLite vide + log efface.
reset_env() {
  local envdir="$1" phpv="$2"
  rm -f "$envdir/wordpress/wp-content/database/wpenv.sqlite"
  rm -f "$envdir/wordpress/wp-content/debug.log"
  rm -rf "$envdir/wordpress/wp-content/plugins/$SLUG"
  local port; port=$(env_port "$(basename "$envdir")")
  wp "$envdir" "$phpv" core install \
      --url="http://127.0.0.1:$port" --title="EML Test" --admin_user=admin \
      --admin_password=admin-pass-123 --admin_email=admin@example.test --skip-email \
      >/dev/null 2>&1
}

# Diag diagnostiques du PLUGIN (on ignore wp-cli et le coeur de WordPress).
plugin_diagnostics() {
  local envdir="$1"
  local log="$envdir/wordpress/wp-content/debug.log"
  [ -f "$log" ] || return 0
  grep -E "wp-content/plugins/$SLUG/" "$log" \
    | grep -vE 'phar:///work/wp/wpenv/wp-cli.phar' || true
}

# Compteur de lignes de diagnostic (= erreurs + warnings + notices + deprecated).
count_diagnostics() {
  plugin_diagnostics "$1" | grep -c . || true
}

# Cookies d'authentification pour le compte admin (id 1).
login_cookie() {
  local envdir="$1" phpv="$2"
  local hash
  hash=$(wp "$envdir" "$phpv" eval 'echo COOKIEHASH;' 2>/dev/null | grep -oP '[a-f0-9]{32}' | head -1)
  local auth logged
  auth=$(wp "$envdir" "$phpv" eval 'echo wp_generate_auth_cookie(1, time()+7200, "auth");' 2>/dev/null | grep -oP 'admin\|[0-9]+\|[A-Za-z0-9]+\|[a-f0-9]+' | head -1)
  logged=$(wp "$envdir" "$phpv" eval 'echo wp_generate_auth_cookie(1, time()+7200, "logged_in");' 2>/dev/null | grep -oP 'admin\|[0-9]+\|[A-Za-z0-9]+\|[a-f0-9]+' | head -1)
  echo "wordpress_${hash}=${auth}; wordpress_logged_in_${hash}=${logged}"
}

start_server() {
  local envdir="$1" phpv="$2"
  local port; port=$(env_port "$(basename "$envdir")")
  stop_server "$envdir"
  setsid nohup "$(phpbin "$phpv")" -S "127.0.0.1:${port}" \
      -t "$envdir/wordpress" > "$envdir/server.log" 2>&1 &
  echo $! > "$envdir/.server.pid"
  local i
  for i in $(seq 1 40); do
    if [ "$(curl -s -o /dev/null -w '%{http_code}' "http://127.0.0.1:${port}/" --max-time 5)" = "200" ]; then
      return 0
    fi
    sleep 0.25
  done
  return 1
}

stop_server() {
  local envdir="$1"
  if [ -f "$envdir/.server.pid" ]; then
    local pid; pid=$(cat "$envdir/.server.pid")
    kill "$pid" 2>/dev/null || true
    sleep 0.3
    kill -9 "$pid" 2>/dev/null || true
    rm -f "$envdir/.server.pid"
  fi
}
