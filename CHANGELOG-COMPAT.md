# CHANGELOG-COMPAT.md

Compatibility-only changes applied to **Enhanced Media Library 2.9.4** (original
release: 2024-07-15, `https://downloads.wordpress.org/plugin/enhanced-media-library.2.9.4.zip`,
md5 `4b322340610d4d79d599232efa4b956d`).

Rules that were applied to every line below:

* **no feature added, no feature removed**;
* **no option added, renamed or removed**;
* **no user-visible text changed** (not one string literal, not one label);
* every corrected expression evaluates to **exactly the value PHP 7.x produced**,
  so the behaviour of the plugin is unchanged;
* the byte content of every untouched file is preserved exactly.

Only **two** files were modified, out of the **32** files of the official ZIP:

| file | change | what |
|---|---|---|
| `enhanced-media-library/core/taxonomies.php` | +12 lines before line 982 | initialisation of `$term_ids` and `$tcount` |
| `enhanced-media-library/core/options-pages.php` | 1 line changed (line 1286) | guarded read of `$_FILES['import_file']` |

Every other file of the official ZIP (`enhanced-media-library.php`, `readme.txt`,
the five other `core/*.php` files, the twelve `js/*.js`, the four `css/*.css`, the
four `fonts/*`, the two `images/*.gif`, `index.php`, `languages/…pot`) is
byte-for-byte identical to the original.

Verification of that claim:

```
$ diff -rq original/enhanced-media-library fixed/enhanced-media-library
Files original/enhanced-media-library/core/options-pages.php and fixed/enhanced-media-library/core/options-pages.php differ
Files original/enhanced-media-library/core/taxonomies.php and fixed/enhanced-media-library/core/taxonomies.php differ
```

---

## How the breakage was measured

Before changing anything, the official 2.9.4 code was installed in **real**
WordPress installations (6.8.12 and 7.1.4, each with its own SQLite database)
and driven with PHP 8.3.29 and PHP 8.5.8, through the plugin's own workflows:
activation, the public front, the media library, the media edit screen, saving a
media through the real edit form, saving a media through the plugin's own AJAX
endpoint, the five settings screens, saving the settings, and the
export/import utilities. Every PHP diagnostic written to `wp-content/debug.log`
by the plugin was collected.

Two complementary static passes were run on the untouched source:

* **PHPStan 2.3.5** with `szepeviktor/phpstan-wordpress` and the WordPress
  stubs, level 6, `phpVersion: 80300`. It reported five "variable might not be
  defined" candidates; three of them are false positives (documented in
  *False positives* below) and two are the real break #1.
* a **manual audit of every superglobal read** (`$_POST`, `$_GET`, `$_REQUEST`,
  `$_FILES`) that is not preceded by an `isset()`/`empty()` guard. Only one such
  read was unguarded: the real break #2.

Everything the audit looked for and did **not** find is listed in
*Checked and found clean* below, because "we looked and it was fine" is the
useful part of a compatibility report.

---

## 1. `enhanced-media-library/core/taxonomies.php` — break #1

**Measured, on the untouched original, in `wp-content/debug.log`:**

```
[11-Nov-2026 12:05:48 UTC] PHP Warning:  Undefined variable $term_ids in .../enhanced-media-library/core/taxonomies.php on line 999
[11-Nov-2026 12:05:48 UTC] PHP Warning:  Undefined variable $tcount   in .../enhanced-media-library/core/taxonomies.php on line 1013
```

**Where it happens.** In `wpuxss_eml_save_attachment_compat()`, the AJAX handler
the plugin registers on `wp_ajax_save-attachment-compat` (the "Save changes"
button of the media modal):

```php
foreach ( $media_taxonomy_names as $taxonomy ) {

    if ( isset( $attachment_data[ $taxonomy ] ) ) {
        $term_ids = array_map( 'trim', preg_split( '/,+/', $attachment_data[ $taxonomy ] ) );
    }
    elseif ( isset( $_REQUEST['tax_input'] ) ) {
        ...
    }

    wp_set_object_terms( $id, $term_ids, $taxonomy, false );     // line 999
    ...
}

if ( (bool) $wpuxss_eml_lib_options['show_count'] )
    $attachment['tcount'] = $tcount;                              // line 1013
```

* **line 999** — `$term_ids` is read when neither `$attachment_data[$taxonomy]`
  nor `$_REQUEST['tax_input']` was supplied. This is the **normal** case of the
  media modal: the modal sends the attachment fields, not the taxonomy field.
* **line 1013** — `$tcount` is only assigned inside the loop, and only when the
  "show count" option is on (it is on by default) **and** the taxonomy actually
  has terms. On a fresh installation, where no media category exists yet, the
  loop body never reaches the assignment, and the variable is read undefined.

In PHP 7 both were `Notice`s. In PHP 8 a read of an undefined variable is a
**Warning**, which is what the log shows.

**The fix.**

```diff
+    // PHP 8 : une variable lue non definie devient un Warning (au lieu d'un
+    // Notice en PHP 7). On initialise les deux variables avec la valeur
+    // equivalente a celle de PHP 7 (variable non definie => NULL) :
+    //   - $term_ids : NULL et array() sont identiques pour wp_set_object_terms()
+    //     (core fait `if ( empty( $terms ) ) { $terms = array(); }`).
+    //   - $tcount : NULL exactement comme en PHP 7 quand la boucle ne l'a pas
+    //     remplit ; l'auto-vivification depuis NULL est silencieuse en PHP 8.
+    // Le comportement observable (JSON renvoye, termes enregistres) est
+    // identique a PHP 7.
+    $term_ids = array();
+    $tcount = null;
+
     foreach ( $media_taxonomy_names as $taxonomy ) {
```

**Why this is behaviour-preserving, in detail.**

* `$term_ids` — in PHP 7 the variable was *undefined* on the first iteration
  that had no source, so it was `NULL` when handed to `wp_set_object_terms()`.
  WordPress core (both 6.8.12 and 7.1.4) starts that function with
  `if ( empty( $terms ) ) { $terms = array(); }`, so `NULL` and `array()` are
  **the same thing** there: the taxonomy is cleared. Initialising with
  `array()` gives exactly that.
  The initialisation is placed **before** the loop and not inside it, on
  purpose: in PHP 7, if one iteration had set `$term_ids` and a later
  iteration had no source, the later iteration silently reused the *previous*
  value. Initialising once, before the loop, reproduces that stale-value
  behaviour exactly; initialising inside the loop would have quietly changed it.
* `$tcount` — in PHP 7 it was `NULL` whenever the loop never assigned it, and
  the JSON sent back to the browser contained `"tcount":null`. Initialising it
  with `NULL` keeps that byte-for-byte. Writing into a `NULL` variable
  (`$tcount[$term_id] = …`) is silent in PHP 8 (only `false` is deprecated for
  auto-vivification, since 8.1), so the populated case is unchanged too.

**After the fix**, the same request produces no diagnostic at all and the same
JSON payload.

---

## 2. `enhanced-media-library/core/options-pages.php` — break #2

**Measured, on the untouched original, in `wp-content/debug.log`:**

```
[11-Nov-2026 12:05:48 UTC] PHP Warning:  Undefined array key "import_file" in .../enhanced-media-library/core/options-pages.php on line 1286
```

**Where it happens.** In `wpuxss_eml_settings_import()`, the handler of the
plugin's "Import Plugin Settings" button:

```php
$import_file = $_FILES['import_file'];

if ( empty( $import_file['tmp_name'] ) ) {
    add_settings_error( 'eml-settings', 'eml_settings_file_absent',
        __('Settings cannot be imported. Please upload a file to import settings.', 'enhanced-media-library'),
        'error' );
    return;
}
```

The form is `enctype="multipart/form-data"` with `<input type="file"
name="import_file"/>`. **When the browser submits it, `$_FILES['import_file']`
always exists** — PHP creates the entry even when the user picked no file
(`tmp_name` empty, `error` = `UPLOAD_ERR_NO_FILE`). We verified this on PHP
8.3.29 and 8.5.8 with a multipart part carrying an empty `filename`.

The warning appears when the request carries **no file part at all** for
`import_file` — for instance a POST that is not `multipart/form-data`, or a
request whose file part was dropped. The key is then simply absent from
`$_FILES`, and reading it is a **Warning** in PHP 8 (a `Notice` in PHP 7).

**The fix.**

```diff
-    $import_file = $_FILES['import_file'];
+    // PHP 8 : la lecture d'une cle absente d'un tableau est un Warning
+    // ("Undefined array key") au lieu d'un Notice en PHP 7. Le comportement
+    // visible est identique : le message d'erreur existant s'affiche.
+    $import_file = isset( $_FILES['import_file'] ) ? $_FILES['import_file'] : array();
```

**Why this is behaviour-preserving.** In PHP 7, a missing key made
`$import_file` `NULL`, and `empty( $import_file['tmp_name'] )` was then `true`,
so the plugin displayed *"Settings cannot be imported. Please upload a file to
import settings."* and returned. With the guard, `$import_file` is `array()`,
`empty( $import_file['tmp_name'] )` is still `true`, and the **same message** is
displayed. No string literal, no option and no control flow was changed — only
the diagnostic disappeared.

---

## Checked and found clean (measured, not assumed)

| what was looked for | result |
|---|---|
| `create_function()` (removed in PHP 8) | **not present** in any of the 32 files |
| `each()` (removed in PHP 8) | **not present** in any of the 32 files |
| dynamic properties (deprecated in PHP 8.2) | **not present**: the three `Walker_*` classes declare `var $tree_type` / `var $db_fields`, and the parent `Walker` class carries `#[AllowDynamicProperties]` |
| `add_action()` / `add_filter()` with a wrong accepted-args count | every call is consistent with the callback's signature; the extra arguments PHP passes to a userland callback with fewer parameters are ignored, no diagnostic |
| `get_taxonomies()`, `get_post_types()`, `get_object_taxonomies()` with the legacy `'object'` output | still supported by WordPress 6.8.12 and 7.1.4, no deprecation emitted |
| `get_terms()` with the legacy two-argument form | still supported by WordPress 6.8.12 and 7.1.4, no deprecation emitted |
| `wp_terms_checklist()` and the `selected_cats` / `popular_cats` arguments | WordPress core always fills `selected_cats` and `popular_cats` with an array, so the plugin's Walker never reads an undefined variable |
| `wp_set_object_terms()` with an empty term list | core maps `NULL` and `array()` to the same empty list |
| `$_POST` / `$_GET` / `$_REQUEST` reads without a guard | all of them are guarded by an `isset()`/`empty()`/`wp_verify_nonce()` test in the same function, except the one fixed in break #2 |
| WordPress core deprecations on PHP 8.5 | **WordPress 6.8.12 itself** emits `Deprecated: Function imagedestroy() is deprecated since 8.5` in `wp-includes/class-wp-image-editor-gd.php`. That is core code, it is not the plugin's, and it is out of the scope of this fork (WordPress 7.1.4 does not have it). It is not counted against the plugin by our test harness. |

### False positives

PHPStan reported three more "variable might not be defined" candidates that we
checked by hand and left alone, because there is nothing to fix:

* `core/taxonomies.php:857` and `:858` — `$popular_cats` and `$selected_cats`
  come from `extract($args)` inside `Walker_Media_Taxonomy_Checklist::start_el()`.
  WordPress core's `wp_terms_checklist()` always puts `selected_cats` and
  `popular_cats` into `$args`, so they are always defined.
* `enhanced-media-library.php:885` — `$wpuxss_eml_mimes[$ext] = …` where
  `$wpuxss_eml_mimes` was not initialised. Writing to an offset of an undefined
  variable auto-creates the array and is **silent** in PHP 8 (verified on
  8.3.29 and 8.5.8); only *reading* an undefined variable warns.

---

## Known issues we did NOT change (they would change behaviour)

* **`tax_input[taxonomy][TERMID]` carries `0`/`1` instead of the term ID.**
  The plugin's `Walker_Media_Taxonomy_Checklist` renders
  `<input value="1" type="checkbox" name="tax_input[media_category][123]">`,
  while WordPress core's `edit_post()` expects `tax_input[taxonomy]` to hold
  **term IDs**. The plugin works around this with its own AJAX handler, but a
  plain "Update" on the media edit screen therefore does not apply the
  checkbox selection the way core expects. This is **not** a PHP 8 regression:
  it behaves exactly the same on PHP 7. Fixing it would change which terms are
  attached to a media — a behaviour change — so we did not touch it.
* **The layout of the media library toolbar on WordPress 7** (the grid layout
  reported in the support forum) is a CSS problem, not a PHP compatibility
  problem, and fixing it would change what the plugin displays.
* The plugin's own `readme.txt` still declares `Requires PHP: 5.6` and
  `Tested up to: 6.5.5`. We did not touch it: the readme is the author's, and
  the WordPress.org listing is not ours to change.

## How the fixes were tested

See `tests/`. The same suite is run against the untouched original and against
the patched code, on the matrix WordPress 6.8.12 / 7.1.4 × PHP 8.3.29 /
8.5.8. The suite fails on the original (PHP 8 warnings in `debug.log`) and
passes on the patched code (no diagnostic at all). The raw output of every run
is in `tests/results/`.
