#!/usr/bin/env python3
"""
form-post.py - transforme le HTML d'un formulaire WordPress en corps POST
(x-www-form-urlencoded), comme le ferait un navigateur.

Usage:
  form-post.py <fichier.html> [--form <index|name>] [--set nom=valeur]... [--unset nom]...

Sortie sur stdout : le corps urlencode.
Chaque --set remplace (ou ajoute) un champ ; chaque --unset retire un champ.
"""
import sys
import re
from urllib.parse import quote

TAG_RE = re.compile(r"<(input|select|textarea|button)\b([^>]*)>", re.I)
ATTR_RE = re.compile(r"""([a-zA-Z_:][-a-zA-Z0-9_:.]*)\s*=\s*("([^"]*)"|'([^']*)'|([^\s"'>]+))""")
FORM_RE = re.compile(r"<form\b([^>]*)>", re.I)


ATTR_BOOL_RE = re.compile(r"(?:^|\s)([a-zA-Z_:][-a-zA-Z0-9_:.]*)(?=\s|/?>)")


def attrs(s):
    d = {}
    for m in ATTR_RE.finditer(s):
        name = m.group(1).lower()
        val = m.group(3)
        if val is None:
            val = m.group(4)
        if val is None:
            val = m.group(5)
        if val is None:
            val = ""
        d[name] = val
    # attributs booleens sans valeur (checked, selected, disabled, multiple...)
    for m in ATTR_BOOL_RE.finditer(s):
        k = m.group(1).lower()
        if k not in d and k not in ("input", "option", "textarea", "select", "form", "label"):
            d[k] = k
    return d


def unescape(s):
    s = s.replace("&amp;", "&").replace("&lt;", "<").replace("&gt;", ">")
    s = s.replace("&quot;", '"').replace("&#039;", "'").replace("&#39;", "'")
    s = s.replace("&nbsp;", " ")
    return s


def main():
    path = sys.argv[1]
    html = open(path, encoding="utf-8", errors="ignore").read()

    form_index = 0
    want_form = None
    sets = []
    unsets = []
    args = sys.argv[2:]
    i = 0
    while i < len(args):
        a = args[i]
        if a == "--form":
            want_form = args[i + 1]
            i += 2
        elif a == "--set":
            sets.append(args[i + 1])
            i += 2
        elif a == "--unset":
            unsets.append(args[i + 1])
            i += 2
        else:
            i += 1

    # decoupe par <form ...> ... </form>
    FORM_BLOCK_RE = re.compile(r"<form\b[^>]*>(.*?)</form\s*>", re.I | re.S)
    blocks = []
    for m in FORM_BLOCK_RE.finditer(html):
        a = attrs(m.group(0)[:m.group(0).find(">") + 1])
        blocks.append((a, m.group(1)))
    if not blocks:
        blocks = [({}, html)]

    if want_form is not None and not want_form.isdigit():
        target_idx = 0
        for i, (a, _c) in enumerate(blocks):
            if a.get("id") == want_form or a.get("name") == want_form:
                target_idx = i
                break
    else:
        target_idx = int(want_form) if want_form else 0
    if target_idx >= len(blocks):
        target_idx = 0

    body = blocks[target_idx][1]

    fields = []          # liste de (name, value)
    seen_names = set()

    for m in TAG_RE.finditer(body):
        tag = m.group(1).lower()
        a = attrs(m.group(2))
        name = a.get("name")
        if not name:
            continue
        t = a.get("type", "").lower()
        if tag == "input":
            if t in ("submit", "button", "image", "reset", "file"):
                continue
            if t in ("checkbox", "radio"):
                if "checked" not in a:
                    continue
            fields.append((name, unescape(a.get("value", ""))))
        elif tag == "textarea":
            fields.append((name, ""))
        elif tag == "select":
            # on prend l'option selectionnee, sinon la premiere
            pass

    # <select> : option selectionne
    for m in re.finditer(r"<select\b[^>]*name=\"([^\"]+)\"[^>]*>(.*?)</select>", body, re.I | re.S):
        name = m.group(1)
        opts = re.findall(r"<option\b([^>]*)>", m.group(2), re.I)
        chosen = None
        first = None
        for o in opts:
            oa = attrs(o)
            val = unescape(oa.get("value", ""))
            if first is None:
                first = val
            if "selected" in oa:
                chosen = val
                break
        fields = [f for f in fields if f[0] != name]
        if chosen is not None or first is not None:
            fields.append((name, chosen if chosen is not None else first))

    # textareas : contenu
    for m in re.finditer(r"<textarea\b([^>]*)>(.*?)</textarea>", body, re.I | re.S):
        a = attrs(m.group(1))
        name = a.get("name")
        if not name:
            continue
        fields = [f for f in fields if f[0] != name]
        fields.append((name, unescape(m.group(2).strip())))

    # --unset / --set
    for u in unsets:
        fields = [f for f in fields if f[0] != u]
    for s in sets:
        k, _, v = s.partition("=")
        fields = [f for f in fields if f[0] != k]
        fields.append((k, v))

    out = "&".join("%s=%s" % (quote(n, safe="[]"), quote(v, safe="")) for n, v in fields)
    sys.stdout.write(out)


if __name__ == "__main__":
    main()
