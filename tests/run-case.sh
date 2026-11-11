#!/usr/bin/env bash
# =============================================================================
# run-case.sh - UN cas de test : (variante) x (WordPress) x (PHP)
#
#   usage : run-case.sh <original|fixed> <wp-7.1.4|wp-6.8.12> <8.3.29|8.5.8>
#
# Tests (le workflow propre au plugin Enhanced Media Library) :
#   T1  activation du plugin
#   T2  front : page publiee avec [gallery] et [playlist]
#   T3  bibliotheque de medias (grille + mode liste)
#   T4  ecran de modification d'un media
#   T5  enregistrement d'un media (formulaire reel de l'ecran de modification)
#   T6  AJAX du plugin save-attachment-compat (installation neuve : aucune
#       categorie media n'existe encore)  <-- CONTRE-EPREUVE ROUGE
#   T6b AJAX du plugin save-attachment-compat avec tax_input (cas nominal)
#   T7  pages de reglages du plugin (5 pages)
#   T8  enregistrement des reglages (3 formulaires reels)
#   T9  utilitaires du plugin (export + import avec fichier + import sans
#       partie fichier)
#
# Sortie : une ligne VERT/ROUGE par test + un verdict final.
# Code de sortie 0 = tout vert, 1 = au moins un test rouge.
# =============================================================================
set -uo pipefail
BASE=${EML_BASE:-/work/wp/reprise/eml}
source "$BASE/tests/lib.sh"

VARIANT="$1"; ENVN="$2"; PHPV="$3"
ENVDIR="$BASE/env/$ENVN"
PORT=$(env_port "$ENVN")
LOGDIR="$BASE/logs/$VARIANT-$ENVN-$PHPV"
mkdir -p "$LOGDIR"

PASS=0; FAIL=0
declare -a RESULTS=()

say()  { printf '%s\n' "$*"; }
res()  { # res <etat> <nom> <detail>
  if [ "$1" = "VERT" ]; then PASS=$((PASS+1)); else FAIL=$((FAIL+1)); fi
  RESULTS+=("$1 | $2 | $3")
  printf '%-6s %-46s %s\n' "$1" "$2" "$3"
}
diag_now() { count_diagnostics "$ENVDIR"; }
det() { plugin_diagnostics "$ENVDIR" | sed "s|.*plugins/$SLUG/||" | head -4 | tr '\n' ';'; }

say "=============================================================="
say "CAS : $VARIANT / WordPress $ENVN / PHP $PHPV"
say "=============================================================="

# --- preparation ------------------------------------------------------------
reset_env "$ENVDIR" "$PHPV"
if [ ! -f "$ENVDIR/wordpress/wp-content/database/wpenv.sqlite" ]; then
  res ROUGE "T0 preparation" "la base n'a pas ete reinstallee"
  say "VERDICT : ROUGE"; exit 1
fi
rm -rf "$ENVDIR/wordpress/wp-content/plugins/$SLUG"
cp -a "$BASE/$VARIANT/$SLUG" "$ENVDIR/wordpress/wp-content/plugins/$SLUG"

# --- T1 : activation --------------------------------------------------------
BEFORE=$(diag_now)
OUT=$(wp "$ENVDIR" "$PHPV" plugin activate $SLUG 2>&1); RC=$?
AFTER=$(diag_now)
if [ "$RC" -ne 0 ]; then
  res ROUGE "T1 activation" "wp-cli a rendu $RC : $(echo "$OUT" | head -2 | tr '\n' ' ')"
elif echo "$OUT" | grep -qiE 'fatal error|parse error|uncaught'; then
  res ROUGE "T1 activation" "erreur fatale : $(echo "$OUT" | grep -iE 'fatal|uncaught' | head -1)"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T1 activation" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T1 activation" "aucune erreur fatale, 0 diagnostic"
fi
echo "$OUT" > "$LOGDIR/activation.txt"

# --- contenu de test --------------------------------------------------------
PNG="$LOGDIR/eml-test.png"
printf '\x89PNG\r\n\x1a\n\x00\x00\x00\rIHDR\x00\x00\x00\x01\x00\x00\x00\x01\x08\x06\x00\x00\x00\x1f\x15\xc4\x89\x00\x00\x00\nIDATx\x9cc\x00\x01\x00\x00\x05\x00\x01\r\n-\xb4\x00\x00\x00\x00IEND\xaeB`\x82' > "$PNG"
ATT=$(wp "$ENVDIR" "$PHPV" media import "$PNG" --title="Media de test EML" --porcelain 2>/dev/null | lastid)
PAGE_ID=$(wp "$ENVDIR" "$PHPV" post create --post_title="Page galerie" --post_status=publish \
  --post_content="[gallery ids=\"$ATT\"] [playlist ids=\"$ATT\"]" --porcelain 2>/dev/null | lastid)
PERMALINK=$(wp "$ENVDIR" "$PHPV" post get "$PAGE_ID" --field=url 2>/dev/null | tr -d '\r\n')
[ -n "$PERMALINK" ] || PERMALINK="http://127.0.0.1:$PORT/?page_id=$PAGE_ID"
say "ATT=$ATT PAGE_ID=$PAGE_ID"
if [ -z "$ATT" ] || [ -z "$PAGE_ID" ]; then
  res ROUGE "T0 preparation" "wp-cli n'a pas renvoye d'identifiant (ATT=[$ATT] PAGE_ID=[$PAGE_ID])"
  say "VERDICT : ROUGE"; exit 1
fi

# --- serveur web ------------------------------------------------------------
if ! start_server "$ENVDIR" "$PHPV"; then
  res ROUGE "T2 serveur HTTP" "le serveur ne repond pas sur le port $PORT"
  say "VERDICT : ROUGE"; exit 1
fi
COOKIE=$(login_cookie "$ENVDIR" "$PHPV")

# --- T2 : front (galerie + playlist) ---------------------------------------
BEFORE=$(diag_now)
CODE=$(curl -s -o "$LOGDIR/front.html" -w '%{http_code}' "$PERMALINK" --max-time 60)
AFTER=$(diag_now)
if [ "$CODE" != "200" ]; then
  res ROUGE "T2 front (galerie + playlist)" "HTTP $CODE"
elif grep -qiE 'fatal error|uncaught (exception|error)' "$LOGDIR/front.html"; then
  res ROUGE "T2 front (galerie + playlist)" "erreur fatale dans la page"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T2 front (galerie + playlist)" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
elif ! grep -q 'eml-test' "$LOGDIR/front.html"; then
  res ROUGE "T2 front (galerie + playlist)" "la galerie ne contient pas le media"
else
  res VERT "T2 front (galerie + playlist)" "HTTP 200, media affiche, 0 diagnostic"
fi

# --- T3 : bibliotheque de medias (grille + liste) ---------------------------
BEFORE=$(diag_now)
BAD=""
for U in "upload.php" "upload.php?mode=list"; do
  N=$(echo "$U" | tr '?=&' '___')
  CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/media-$N.html" -w '%{http_code}' \
         "http://127.0.0.1:$PORT/wp-admin/$U" --max-time 90)
  [ "$CODE" = "200" ] || BAD="$BAD $U:$CODE"
  grep -qiE 'fatal error|uncaught (exception|error)' "$LOGDIR/media-$N.html" && BAD="$BAD $U:fatal"
done
AFTER=$(diag_now)
if [ -n "$BAD" ]; then
  res ROUGE "T3 bibliotheque de medias" "HTTP non 200 :$BAD"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T3 bibliotheque de medias" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T3 bibliotheque de medias" "grille + liste HTTP 200, 0 diagnostic"
fi

# --- recuperation du nonce de l'ecran de modification ----------------------
curl -s -b "$COOKIE" -o "$LOGDIR/media-edit.html" \
     "http://127.0.0.1:$PORT/wp-admin/post.php?post=$ATT&action=edit" --max-time 90 >/dev/null
NONCE=$(grep -oP 'name="_wpnonce"[^>]*value="\K[a-f0-9]+' "$LOGDIR/media-edit.html" | head -1)

# --- T6 : AJAX du plugin, installation neuve (aucune categorie media) ------
# C'est le workflow "enregistrer les modifications" de la modale medias du
# plugin (wp_ajax_save-attachment-compat). Sur une installation neuve aucune
# categorie media n'existe : c'est le cas le plus frequent.
BEFORE=$(diag_now)
CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/ajax-save.json" -w '%{http_code}' \
       --data-urlencode "action=save-attachment-compat" \
       --data-urlencode "id=$ATT" \
       --data-urlencode "nonce=$NONCE" \
       --data-urlencode "attachments[$ATT][post_title]=Titre ajax" \
       --data-urlencode "attachments[$ATT][post_content]=Contenu ajax" \
       "http://127.0.0.1:$PORT/wp-admin/admin-ajax.php" --max-time 60)
AFTER=$(diag_now)
if [ "$CODE" != "200" ]; then
  res ROUGE "T6 AJAX save-attachment-compat" "HTTP $CODE"
elif ! grep -q '"success":true' "$LOGDIR/ajax-save.json"; then
  res ROUGE "T6 AJAX save-attachment-compat" "reponse sans succes : $(head -c 120 "$LOGDIR/ajax-save.json")"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T6 AJAX save-attachment-compat" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T6 AJAX save-attachment-compat" "enregistrement JSON OK, 0 diagnostic"
fi

# --- creation d'une categorie media (a partir de maintenant) ---------------
TERM=$(wp "$ENVDIR" "$PHPV" term create media_category "Categorie de test" --porcelain 2>/dev/null | lastid)
wp "$ENVDIR" "$PHPV" post term add "$ATT" media_category "$TERM" >/dev/null 2>&1
say "TERM=$TERM"

# --- T6b : AJAX du plugin avec tax_input (cas nominal) ---------------------
BEFORE=$(diag_now)
CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/ajax-save-taxinput.json" -w '%{http_code}' \
       --data-urlencode "action=save-attachment-compat" \
       --data-urlencode "id=$ATT" \
       --data-urlencode "nonce=$NONCE" \
       --data-urlencode "attachments[$ATT][post_title]=Titre ajax" \
       --data-urlencode "tax_input[media_category][$TERM]=1" \
       "http://127.0.0.1:$PORT/wp-admin/admin-ajax.php" --max-time 60)
AFTER=$(diag_now)
if [ "$CODE" != "200" ]; then
  res ROUGE "T6b AJAX save-attachment-compat (tax_input)" "HTTP $CODE"
elif ! grep -q '"success":true' "$LOGDIR/ajax-save-taxinput.json"; then
  res ROUGE "T6b AJAX save-attachment-compat (tax_input)" "reponse sans succes : $(head -c 120 "$LOGDIR/ajax-save-taxinput.json")"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T6b AJAX save-attachment-compat (tax_input)" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T6b AJAX save-attachment-compat (tax_input)" "enregistrement JSON OK, 0 diagnostic"
fi

# --- T4 : ecran de modification d'un media ----------------------------------
BEFORE=$(diag_now)
CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/media-edit.html" -w '%{http_code}' \
       "http://127.0.0.1:$PORT/wp-admin/post.php?post=$ATT&action=edit" --max-time 90)
AFTER=$(diag_now)
if [ "$CODE" != "200" ]; then
  res ROUGE "T4 ecran de modification d'un media" "HTTP $CODE"
elif grep -qiE 'fatal error|uncaught (exception|error)|critical error' "$LOGDIR/media-edit.html"; then
  res ROUGE "T4 ecran de modification d'un media" "erreur fatale dans la page"
elif ! grep -q 'tax_input\[media_category\]' "$LOGDIR/media-edit.html"; then
  res ROUGE "T4 ecran de modification d'un media" "le champ de taxonomie EML est absent"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T4 ecran de modification d'un media" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T4 ecran de modification d'un media" "HTTP 200, taxonomie EML presente, 0 diagnostic"
fi

# --- T5 : enregistrement d'un media (formulaire reel) -----------------------
BEFORE=$(diag_now)
NEWTITLE="Media de test EML - enregistre"
BODY=$(python3 "$BASE/tests/form-post.py" "$LOGDIR/media-edit.html" --form 1 \
        --set "post_title=$NEWTITLE" --set "content=Description modifiee par le harnais.")
CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/media-save.html" -w '%{http_code}' \
       -H "Content-Type: application/x-www-form-urlencoded" --data "$BODY" \
       "http://127.0.0.1:$PORT/wp-admin/post.php" --max-time 90)
SAVED=$(wp "$ENVDIR" "$PHPV" post get "$ATT" --field=post_title 2>/dev/null | tr -d '\r\n')
SAVEDC=$(wp "$ENVDIR" "$PHPV" post get "$ATT" --field=post_content 2>/dev/null | tr -d '\r\n')
AFTER=$(diag_now)
if [ "$CODE" != "200" ] && [ "$CODE" != "302" ]; then
  res ROUGE "T5 enregistrement d'un media" "HTTP $CODE"
elif [ "$SAVED" != "$NEWTITLE" ] || ! echo "$SAVEDC" | grep -q "modifiee par le harnais"; then
  res ROUGE "T5 enregistrement d'un media" "le media n'a pas ete enregistre (HTTP $CODE, titre=[$SAVED])"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T5 enregistrement d'un media" "enregistre mais $((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T5 enregistrement d'un media" "titre + description enregistres, 0 diagnostic"
fi

# --- T7 : pages de reglages du plugin ---------------------------------------
BEFORE=$(diag_now)
BAD=""
for PAGE in media-library media-taxonomies mime-types eml-settings; do
  CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/opt-$PAGE.html" -w '%{http_code}' \
         "http://127.0.0.1:$PORT/wp-admin/options-general.php?page=$PAGE" --max-time 90)
  [ "$CODE" = "200" ] || BAD="$BAD $PAGE:$CODE"
  grep -qiE 'fatal error|uncaught (exception|error)' "$LOGDIR/opt-$PAGE.html" && BAD="$BAD $PAGE:fatal"
done
CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/opt-media-settings.html" -w '%{http_code}' \
       "http://127.0.0.1:$PORT/wp-admin/upload.php?page=media" --max-time 90)
[ "$CODE" = "200" ] || BAD="$BAD media-settings:$CODE"
AFTER=$(diag_now)
if [ -n "$BAD" ]; then
  res ROUGE "T7 pages de reglages" "HTTP non 200 :$BAD"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T7 pages de reglages" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T7 pages de reglages" "5 pages HTTP 200, 0 diagnostic"
fi

# --- T8 : enregistrement des reglages (formulaires reels) --------------------
BEFORE=$(diag_now)
BAD=""; OKSAVES=0
for PAGE in media-library media-taxonomies mime-types; do
  F="$LOGDIR/opt-$PAGE.html"
  [ -f "$F" ] || { BAD="$BAD $PAGE:page-absente"; continue; }
  BODY=$(python3 "$BASE/tests/form-post.py" "$F")
  if [ -z "$BODY" ]; then BAD="$BAD $PAGE:formulaire-vide"; continue; fi
  CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/save-$PAGE.html" -w '%{http_code}' \
         -H "Content-Type: application/x-www-form-urlencoded" --data "$BODY" \
         "http://127.0.0.1:$PORT/wp-admin/options-general.php?page=$PAGE" --max-time 90)
  if [ "$CODE" = "200" ]; then OKSAVES=$((OKSAVES+1)); else BAD="$BAD $PAGE:$CODE"; fi
done
AFTER=$(diag_now)
if [ -n "$BAD" ]; then
  res ROUGE "T8 enregistrement des reglages" "$OKSAVES/3 enregistres :$BAD"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T8 enregistrement des reglages" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T8 enregistrement des reglages" "3 formulaires enregistres, 0 diagnostic"
fi

# --- T9 : utilitaires du plugin (export / import) ---------------------------
BEFORE=$(diag_now)
F="$LOGDIR/opt-eml-settings.html"
URLOPT="http://127.0.0.1:$PORT/wp-admin/options-general.php?page=eml-settings"
NONCEEXP=$(grep -oP 'id="eml-settings-export-nonce"[^>]*value="\K[a-f0-9]+' "$F" | head -1)
CODE=$(curl -s -b "$COOKIE" -o "$LOGDIR/util-export.json" -w '%{http_code}' \
       --data-urlencode "eml-settings-export=1" \
       --data-urlencode "eml-settings-export-nonce=$NONCEEXP" \
       "$URLOPT" --max-time 60)
EXPORT_OK=0
grep -q '"taxonomies"' "$LOGDIR/util-export.json" 2>/dev/null && EXPORT_OK=1
NONCEIMP=$(grep -oP 'id="eml-settings-import-nonce"[^>]*value="\K[a-f0-9]+' "$F" | head -1)

# T9a : import avec un VRAI fichier (multipart) - cas nominal du navigateur
C1=$(curl -s -b "$COOKIE" -o "$LOGDIR/util-import-fichier.html" -w '%{http_code}' \
     -F "eml-settings-import=1" -F "eml-settings-import-nonce=$NONCEIMP" \
     -F "import_file=@$LOGDIR/util-export.json;type=application/json" \
     "$URLOPT" --max-time 60)
IMPORTED=0
grep -qi 'Plugin settings imported' "$LOGDIR/util-import-fichier.html" && IMPORTED=1

# T9b : import SANS partie fichier (le fichier n'a pas ete joint a la requete)
C2=$(curl -s -b "$COOKIE" -o "$LOGDIR/util-import-sans-fichier.html" -w '%{http_code}' \
     --data-urlencode "eml-settings-import=1" \
     --data-urlencode "eml-settings-import-nonce=$NONCEIMP" \
     "$URLOPT" --max-time 60)
AFTER=$(diag_now)
if [ "$CODE" != "200" ]; then
  res ROUGE "T9 utilitaires (export/import)" "export HTTP $CODE"
elif [ "$EXPORT_OK" -ne 1 ]; then
  res ROUGE "T9 utilitaires (export/import)" "l'export n'a pas renvoye le JSON des reglages"
elif [ "$C1" != "200" ] || [ "$IMPORTED" -ne 1 ]; then
  res ROUGE "T9 utilitaires (export/import)" "l'import avec fichier n'a pas abouti (HTTP $C1)"
elif [ "$C2" != "200" ]; then
  res ROUGE "T9 utilitaires (export/import)" "import sans fichier : HTTP $C2"
elif [ "$AFTER" -gt "$BEFORE" ]; then
  res ROUGE "T9 utilitaires (export/import)" "$((AFTER-BEFORE)) diagnostic(s) : $(det)"
else
  res VERT "T9 utilitaires (export/import)" "export + import avec fichier + import sans fichier, 0 diagnostic"
fi

stop_server "$ENVDIR"

# --- conservation des preuves ------------------------------------------------
cp -f "$ENVDIR/server.log" "$LOGDIR/server.log" 2>/dev/null || true
cp -f "$ENVDIR/wordpress/wp-content/debug.log" "$LOGDIR/debug.log" 2>/dev/null || true
plugin_diagnostics "$ENVDIR" > "$LOGDIR/debug.log.plugin-only.txt" 2>/dev/null || true

# --- verdict ----------------------------------------------------------------
say ""
say "RESULTATS : $PASS vert(s), $FAIL rouge(s)"
printf '%s\n' "${RESULTS[@]}" > "$LOGDIR/resultats.txt"
if [ "$FAIL" -eq 0 ]; then
  say "VERDICT : VERT"
  exit 0
else
  say "VERDICT : ROUGE"
  exit 1
fi
