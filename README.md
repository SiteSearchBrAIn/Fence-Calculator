# NTF Fence Estimator

Custom fence pricing calculator plugin for **newtampafence.com**.

- **Version:** 3.1.1
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

## JobNimbus (3.1.0)

Every estimator lead is also pushed to JobNimbus, using the `job-nimbus-client`
plugin and its API token (the same ones the Gravity Forms hook uses).

- Gate step (name, email, phone): find or create the contact, create a job
  named `Fence Estimator Lead - <name>`.
- Completed quote: updates that job (`PUT jobs/<jnid>`) with the estimate detail.
  The jnid is held in a 14-day transient keyed on the email. If the update
  fails or the transient is gone, a new job `Fence Estimate Quote - <name>` is created.
- The push runs from WP-Cron (`ntf_fence_jobnimbus_push`), so the visitor and the
  notification email never wait on, or fail because of, the CRM.
- Results (last 50) are kept in the `ntf_fence_jn_log` option.
- Test without touching the CRM: `add_filter('ntf_fence_jobnimbus_dry_run', '__return_true');`
  logs the contact and job payloads instead of sending them.
- Fence type goes in the job description; it does not fill the JobNimbus Fence Type dropdown.

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
site, so changes go to `staging12` first. This repository is the source of
truth: deploy from a checkout of `main`, never from a hand-edited server copy.
