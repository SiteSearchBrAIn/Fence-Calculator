# NTF Fence Estimator

Custom fence pricing calculator plugin for **newtampafence.com**.

- **Version:** 3.2.1
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

## Updates (3.2.0)

The plugin updates itself from this repository's GitHub releases. There is no store and no license.

- WordPress checks the latest release (cached 6 hours) and offers it under Dashboard > Updates and
  on the Plugins screen, like any plugin. Install with one click, `wp plugin update ntf-fence-estimator`,
  or switch on the per-plugin "Enable auto-updates" link.
- Dashboard > Updates > **Check again** (and WP-CLI) forces a fresh look.
- The installer renames GitHub's download folder to `ntf-fence-estimator/` and refuses a package that
  does not contain the plugin file, so an update can never leave a second copy or deactivate the plugin.
- Private repo instead of public: define `NTF_FENCE_GH_TOKEN` in `wp-config.php` with a read-only token
  (Contents: Read on this repo). It is only sent to api.github.com.

### Releasing

1. Change the code on a branch, test on `staging12`, merge the PR to `main`.
2. Make sure `Version:` in `ntf-fence-estimator.php` and `NTF_FENCE_VERSION` both equal the new version.
3. `gh release create vX.Y.Z --target main --title "X.Y.Z - summary" --notes "what changed"`.
   The release notes become the changelog shown in WordPress. No zip needs attaching.
4. Sites see it within 6 hours, or immediately after "Check again".

Version 3.2.0 is the first release with the updater, so a site on 3.1.x has to be updated by hand once
(copy the folder). Every release after that installs itself.

## Deploying

The plugin has no update mechanism; it is installed by copying the folder
to `wp-content/plugins/ntf-fence-estimator`. Production is a live client
site, so changes go to `staging12` first. This repository is the source of
truth: deploy from a checkout of `main`, never from a hand-edited server copy.
