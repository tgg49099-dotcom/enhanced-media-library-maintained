#!/usr/bin/env python3
"""
apply-compat-patch.py - construit la variante « fixed » d'Enhanced Media Library
a partir de la variante « original » (code officiel wordpress.org) en appliquant
uniquement les correctifs de compatibilite PHP 8 mesures sur WordPress reel.

Regle : aucune fonction ajoutee/supprimee, aucune option, aucun texte visible
change. Chaque correctif est une initialisation de variable ou un garde-fou
qui reproduit exactement le comportement de PHP 7.

usage : python3 tools/apply-compat-patch.py [--check]
"""
import os
import shutil
import sys
import filecmp

BASE = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SRC = os.path.join(BASE, "original", "enhanced-media-library")
DST = os.path.join(BASE, "fixed", "enhanced-media-library")

# ---------------------------------------------------------------------------
# Les correctifs. Chaque entree : (fichier relatif, texte d'origine, texte
# corrige, identifiant, raison mesuree)
# ---------------------------------------------------------------------------
PATCHES = [
    (
        "core/taxonomies.php",
        """    foreach ( $media_taxonomy_names as $taxonomy ) {

        if ( isset( $attachment_data[ $taxonomy ] ) ) {""",
        """    // PHP 8 : une variable lue non definie devient un Warning (au lieu d'un
    // Notice en PHP 7). On initialise les deux variables avec la valeur
    // equivalente a celle de PHP 7 (variable non definie => NULL) :
    //   - $term_ids : NULL et array() sont identiques pour wp_set_object_terms()
    //     (core fait `if ( empty( $terms ) ) { $terms = array(); }`).
    //   - $tcount : NULL exactement comme en PHP 7 quand la boucle ne l'a pas
    //     remplit ; l'auto-vivification depuis NULL est silencieuse en PHP 8.
    // Le comportement observable (JSON renvoye, termes enregistres) est
    // identique a PHP 7.
    $term_ids = array();
    $tcount = null;

    foreach ( $media_taxonomy_names as $taxonomy ) {

        if ( isset( $attachment_data[ $taxonomy ] ) ) {""",
        "compat-php8-undefined-term-ids",
        "Warning: Undefined variable $term_ids (taxonomies.php:999)",
    ),
    (
        "core/options-pages.php",
        """    $import_file = $_FILES['import_file'];""",
        """    // PHP 8 : la lecture d'une cle absente d'un tableau est un Warning
    // ("Undefined array key") au lieu d'un Notice en PHP 7. Le comportement
    // visible est identique : le message d'erreur existant s'affiche.
    $import_file = isset( $_FILES['import_file'] ) ? $_FILES['import_file'] : array();""",
        "compat-php8-missing-files-key",
        "Warning: Undefined array key \"import_file\" (options-pages.php:1286)",
    ),
]


def fail(msg):
    print("ERREUR : " + msg, file=sys.stderr)
    sys.exit(1)


def apply(check_only=False):
    if not os.path.isdir(SRC):
        fail("source introuvable : %s" % SRC)

    if check_only:
        # Verifie que le patch est deja present dans la destination ET que le
        # fichier corrigé, une fois le patch annule, est identique a l'original.
        ok = True
        for rel, old, new, pid, reason in PATCHES:
            path = os.path.join(DST, rel)
            src = os.path.join(SRC, rel)
            if not os.path.exists(path):
                print("  ABSENT  %s (%s)" % (path, pid))
                ok = False
                continue
            data = open(path, encoding="utf-8").read()
            if new not in data:
                print("  ABSENT  %s (%s) : le correctif n'est pas applique" % (rel, pid))
                ok = False
                continue
            reverted = data.replace(new, old, 1)
            original = open(src, encoding="utf-8").read()
            if reverted != original:
                print("  DIVERGENT %s (%s) : le fichier differe de l'original "
                      "au-dela du correctif" % (rel, pid))
                ok = False
                continue
            print("  OK      %-24s %s" % (pid, rel))
        return 0 if ok else 1

    # 1. recopie propre de l'original
    if os.path.exists(DST):
        shutil.rmtree(DST)
    os.makedirs(os.path.dirname(DST), exist_ok=True)
    shutil.copytree(SRC, DST)

    # 2. application des correctifs
    for rel, old, new, pid, reason in PATCHES:
        path = os.path.join(DST, rel)
        if not os.path.exists(path):
            fail("fichier absent dans la source : %s" % rel)
        data = open(path, encoding="utf-8").read()
        if data.count(old) != 1:
            fail("le texte a remplacer n'apparait pas exactement une fois dans %s "
                 "(%d occurrences) pour le correctif %s" % (rel, data.count(old), pid))
        data = data.replace(old, new, 1)
        open(path, "w", encoding="utf-8").write(data)
        print("  PATCH   %-24s %s" % (pid, reason))

    # 3. controle : aucun autre fichier ne doit differer de l'original
    diffs = []
    for root, dirs, files in os.walk(SRC):
        for f in files:
            s = os.path.join(root, f)
            rel = os.path.relpath(s, SRC)
            d = os.path.join(DST, rel)
            if not os.path.exists(d):
                diffs.append(rel + " (manquant)")
                continue
            if not filecmp.cmp(s, d, shallow=False):
                diffs.append(rel)
    print("  Fichiers modifies : %s" % ", ".join(sorted(diffs)))
    expected = sorted(p[0] for p in PATCHES)
    if sorted(diffs) != expected:
        fail("la variante corrigee differe de l'original sur des fichiers inattendus")
    return 0


if __name__ == "__main__":
    if "--check" in sys.argv:
        sys.exit(apply(check_only=True))
    print("Construction de la variante corrigee depuis %s" % SRC)
    sys.exit(apply())
