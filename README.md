Enhanced Media Library - compatibility fork by Tgg Labs (unofficial)

This repository is an UNOFFICIAL compatibility fork of "Enhanced Media Library" by
webbistro (https://profiles.wordpress.org/webbistro/), original plugin page
https://wordpress.org/plugins/enhanced-media-library/, original site
https://wpuxsolutions.com.

The original plugin's licence and its copyright notice are kept unchanged; see
LICENSE. We are not the original author and we do not sell this plugin. The
commercial "Enhanced Media Library PRO" and its support remain the property of its
author, and we would not touch the commercial part.

What we changed: compatibility only (PHP 8.3/8.5, WordPress 6.8/7.1). No feature, no
option and no user-visible text was changed. The list of changes is in
CHANGELOG-COMPAT.md, with the tests we ran in tests/.

Why: the last public release is 2.9.4 (July 2024) and the plugin is marked as tested
up to WordPress 6.5.14, while the current WordPress release is 7.1.4, and threads here
report layout problems on WordPress 7. If the original author prefers to maintain the
plugin himself, this repository and these patches are his to use.

---

## Who does what

| | |
|---|---|
| Original plugin, all features, all wording, copyright | **wpUXsolutions / webbistro** - https://profiles.wordpress.org/webbistro/ - https://wpuxsolutions.com |
| Commercial "Enhanced Media Library PRO" and its support | **the original author** - https://wpuxsolutions.com - untouched by this fork |
| This repository: PHP 8 / WordPress 6.8-7.1 compatibility patches, test harness, packaging | **Tgg Labs** (https://github.com/tgg49099-dotcom) - unofficial, free, no paid tier |
| WordPress.org plugin directory, its slug `enhanced-media-library`, its page, its reviews, its SVN | **unchanged, still the original author's** - this repository is not published there |

Nothing here is submitted to the WordPress.org plugin directory. The plugin slug
`enhanced-media-library` on wordpress.org is untouched by us.

## Repository layout

```
enhanced-media-library/   the plugin, patched for compatibility (Enhanced Media Library 2.9.4)
tests/                    the test harness we used, and the raw output of the runs
CHANGELOG-COMPAT.md       every change we made, file by file, with the reason
NOTICE                    authorship, attribution and licensing notice
LICENSE                   the GNU General Public License v2 (the plugin's own licence)
```

The `enhanced-media-library/` directory is the plugin itself and can be zipped and
installed as a normal WordPress plugin (the main folder name must stay
`enhanced-media-library`).

## What we tested

The plugin was exercised on **real WordPress installations** (not a mock), with the
plugin's own workflows: activation, the public front with a `[gallery]` and a
`[playlist]` shortcode, the media library, the media edit screen, saving a media
through the real edit form, saving a media through the plugin's own AJAX endpoint
(`wp_ajax_save-attachment-compat`), the five settings screens, saving the settings
through the real forms, and the export/import utilities.

| | WordPress 6.8.12 | WordPress 7.1.4 |
|---|---|---|
| PHP 8.3.29 | tested | tested |
| PHP 8.5.8 | tested | tested |

The harness lives in `tests/` and the raw output of every run is in
`tests/results/`. `tests/run-matrix.sh` runs the whole matrix; the cases are run
**sequentially** on purpose (WordPress test suites of this kind are not
parallelisable in the same sandbox).

The same suite is run twice: once against the untouched official 2.9.4 code
(`original`) and once against the patched code (`fixed`). On the official code the
suite reports PHP 8 warnings; on the patched code it reports none.

## Known issues we did NOT change

`CHANGELOG-COMPAT.md` lists them in full. In short: one thing would need a
behaviour change to be fixed, so we left it alone and documented it instead. We
prefer a fork that behaves exactly like the original over a fork that "improves"
it.

## Licence

This fork is distributed under the same licence as the original plugin: the GNU
General Public License, version 2 or (at your option) any later version
(`License: GPL version 2 or later` in the plugin header, `License: GPLv2 or later`
in `readme.txt`). The full text is in `LICENSE`.
