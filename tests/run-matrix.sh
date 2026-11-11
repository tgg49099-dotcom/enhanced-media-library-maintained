#!/usr/bin/env bash
# =============================================================================
# run-matrix.sh - matrice de compatibilite d'Enhanced Media Library 2.9.4
#
#   8 cas sequentiels : {original,fixed} x {wp-6.8.12,wp-7.1.4} x {8.3.29,8.5.8}
#
#   usage : bash tools/run-matrix.sh [original|fixed|all]
#
# Les cas sont SEQUENTIELS (jamais en parallele : les suites WordPress de ce
# bac a sable ne sont pas parallelisables).
#
# Sortie :
#   logs/matrice.txt          - synthese complete
#   logs/<variante>-<wp>-<php>/  - journaux detailles de chaque cas
# =============================================================================
set -uo pipefail
BASE=${EML_BASE:-/work/wp/reprise/eml}
source "$BASE/tests/lib.sh"

WHICH="${1:-all}"
LOGDIR="$BASE/logs"
mkdir -p "$LOGDIR"
SUMMARY="$LOGDIR/matrice.txt"

WPS=(wp-7.1.4 wp-6.8.12)
PHPS=(8.3.29 8.5.8)
VARIANTS=()
case "$WHICH" in
  original) VARIANTS=(original) ;;
  fixed)    VARIANTS=(fixed) ;;
  *)        VARIANTS=(original fixed) ;;
esac

{
  echo "==============================================================="
  echo "MATRICE DE COMPATIBILITE - Enhanced Media Library 2.9.4"
  echo "==============================================================="
  echo "WordPress : ${WPS[*]}"
  echo "PHP       : ${PHPS[*]}"
  echo "Variantes : ${VARIANTS[*]}"
  echo "Date      : $(date -u '+%Y-%m-%d %H:%M:%S UTC')"
  echo
} | tee "$SUMMARY"

TOTAL=0; VERTS=0; ROUGES=0
declare -a LIGNES=()

for VARIANT in "${VARIANTS[@]}"; do
  for ENVN in "${WPS[@]}"; do
    for PHPV in "${PHPS[@]}"; do
      TOTAL=$((TOTAL+1))
      CASE="$LOGDIR/$VARIANT-$ENVN-$PHPV"
      mkdir -p "$CASE"
      echo "---------------------------------------------------------------"
      echo "CAS $TOTAL/8 : $VARIANT / $ENVN / $PHPV"
      echo "---------------------------------------------------------------"
      bash "$BASE/tests/run-case.sh" "$VARIANT" "$ENVN" "$PHPV" 2>&1 | tee "$CASE/console.txt"
      RC=${PIPESTATUS[0]}
      if [ "$RC" -eq 0 ]; then
        V=1
      else
        V=0
      fi
      VERTS=$((VERTS+V))
      # compte les tests rouges du cas (grep -c affiche 0 et sort 1 : on ne
      # surtout pas ajouter de "|| echo 0", cela ajouterait une seconde ligne)
      NR=$(grep -c '^ROUGE' "$CASE/resultats.txt" 2>/dev/null)
      NR=${NR:-0}
      ROUGES=$((ROUGES+NR))
      LINE=$(printf '%-9s %-10s %-8s verdict=%-6s tests_rouges=%s' "$VARIANT" "$ENVN" "$PHPV" \
             "$([ "$RC" -eq 0 ] && echo VERT || echo ROUGE)" "$NR")
      LIGNES+=("$LINE")
      echo "$LINE" | tee -a "$SUMMARY"
      echo
    done
  done
done

{
  echo "==============================================================="
  echo "SYNTHESE : $VERTS/$TOTAL cas verts"
  echo "==============================================================="
  printf '%s\n' "${LIGNES[@]}"
} | tee -a "$SUMMARY"

echo
echo "Matrice ecrite dans $SUMMARY"
[ "$VERTS" -eq "$TOTAL" ]
