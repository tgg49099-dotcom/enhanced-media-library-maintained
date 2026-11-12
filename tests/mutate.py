#!/usr/bin/env python3
# =============================================================================
# mutate.py - mutation exacte, reversible et verifiable (contre-epreuve rouge)
#
#   usage : MUT_PATTERN=wpe|eml python3 -I mutate.py <break|restore> <fichier>
#
# Principe : on ne touche qu'AU CODE DU CORRECTIF DE COMPATIBILITE, et on
# verifie que le motif de chaque substitution est present EXACTEMENT UNE fois.
# Si le motif n'est pas trouve ou est trouve plusieurs fois, le script REFUSE
# d'ecrire : une mutation qui ne trouve pas sa cible ne prouve rien (le
# test resterait vert pour la mauvaise raison).
#
# "restore" remet le texte exact : apres restauration, le fichier doit être
# identique octet pour octet au fichier "fixed" de référence (contrôle fait
# par md5 dans le script de conduite).
#
# WP EDIT  : on retire la garde " ?? null" ajoutée par le correctif sur la
#            lecture d'option de la ligne 11 de includes/functions.php. Sans
#            la garde, PHP 8 signale "Trying to access array offset on false"
#            à l'activation : c'est exactement le warning que le correctif
#            supprime.
#
# EML      : on neutralise l'initialisation que le correctif ajoute avant la
#            boucle des taxonomies (core/taxonomies.php) : $term_ids et
#            $tcount redeviennent non definis, exactement comme dans le code
#            officiel d'origine. PHP 8 signale alors "Undefined variable
#            $term_ids" et "Undefined variable $tcount" quand le point
#            d'entree AJAX du plugin enregistre un media.
# =============================================================================
import hashlib
import os
import sys

# Chaque entree : (ligne_corrigee, ligne_cassee). Les deux sont uniques.
PATTERNS = {
    "wpe": [
        (
            "if(($plugin_options_global['disable_admin_links'] ?? null) != 1) {",
            "if($plugin_options_global['disable_admin_links'] != 1) {",
        ),
    ],
    "eml": [
        (
            "    $term_ids = array();",
            "    $term_ids = array(); unset($term_ids); /*MUT*/",
        ),
        (
            "    $tcount = null;",
            "    $tcount = null; unset($tcount); /*MUT*/",
        ),
    ],
}


def md5(path):
    with open(path, "rb") as fh:
        return hashlib.md5(fh.read()).hexdigest()


def main():
    if len(sys.argv) != 3:
        sys.stderr.write("usage: mutate.py <break|restore> <fichier>\n")
        return 2
    mode, path = sys.argv[1], sys.argv[2]
    key = os.environ.get("MUT_PATTERN", "")
    if not key:
        # On deduit le motif du chemin : le patch est dans wp-edit/... pour
        # WP Edit, dans enhanced-media-library/... pour EML.
        if "wp-edit" in path:
            key = "wpe"
        elif "enhanced-media-library" in path:
            key = "eml"
    if mode not in ("break", "restore"):
        sys.stderr.write("mode inconnu: %s\n" % mode)
        return 2
    if key not in PATTERNS:
        sys.stderr.write(
            "motif inconnu : MUT_PATTERN doit valoir 'wpe' ou 'eml'\n")
        return 2

    with open(path, "r", encoding="utf-8", newline="") as fh:
        text = fh.read()

    for patched, broken in PATTERNS[key]:
        needle, replacement = (patched, broken) if mode == "break" else (broken, patched)
        count = text.count(needle)
        if count != 1:
            sys.stderr.write(
                "REFUS : motif %r trouve %d fois (1 attendu) dans %s\n"
                % (needle[:48], count, path))
            return 1
        text = text.replace(needle, replacement, 1)

    with open(path, "w", encoding="utf-8", newline="") as fh:
        fh.write(text)

    print("mode=%-7s motif=%-4s fichier=%s md5_apres=%s"
          % (mode, key, path, md5(path)))
    return 0


if __name__ == "__main__":
    sys.exit(main())
