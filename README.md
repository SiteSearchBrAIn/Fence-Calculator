# NTF Fence Estimator

Custom fence pricing calculator plugin for **newtampafence.com**.

- **Version:** 3.0.1
- **WordPress plugin slug:** `ntf-fence-estimator`

## Provenance

This repository was seeded from the copy running on **production**
(`newtampafence.com`) on 2026-09-15. At the time of import the production
and `staging12` copies were byte-identical (verified by md5 across all
five files), so this tree reflects the live estimator including the
September price sweep.

Earlier local snapshots in the working folder are **not** authoritative:
the `prod/` capture from 2026-09-11 predates the 3.0.1 deploy and still
reads 2.2.0, and the `dev/` and `drive/` copies are 3.0.0.

## Layout

```
ntf-fence-estimator.php   plugin bootstrap, shortcode, pricing tables
template.php              calculator markup
assets/calculator.css     styles
assets/calculator.js      step logic and quote calculation
assets/logo.png
```

## Deploying

The plugin has no update mechanism; it is installed by copying the folder
to `wp-content/plugins/ntf-fence-estimator`. Production is a live client
site, so changes go to `staging12` first.
