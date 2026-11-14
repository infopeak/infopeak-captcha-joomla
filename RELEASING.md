# Releasing

Joomla sites find updates through `updates.xml` on the `main` branch of
https://github.com/infopeak/infopeak-captcha-joomla. Each entry points to a zip attached to a GitHub
release. Nothing is published until the release exists and `updates.xml` is pushed.

## Steps

1. Raise the version in two places, which must match:
   - `plg_captcha_infopeak/infopeak.xml` → `<version>`
   - `plg_captcha_infopeak/src/Service/InfoPeak.php` → `VERSION`
2. Add a `<changelog>` entry for the version to `changelog.xml`.
3. Commit.
4. Build:

   ```bash
   bash tools/build.sh
   ```

   It refuses to run on uncommitted changes, builds `build/plg_captcha_infopeak-<version>.zip` from the
   committed plugin folder, and rewrites `updates.xml` with that zip's download URL and sha512.
5. Test the zip: install it over the previous version on Joomla 5 and Joomla 6, and run
   `tests/unit.php` inside both.
6. Create the GitHub release `v<version>` and attach the zip under exactly that file name.
7. Commit and push `updates.xml` **after** the release exists. Joomla checks the sha512, so the zip on the
   release must be the one the build produced.
8. Upload the same zip to the JED listing.

## Rules

- The git identity in this repository is `InfoPeak <support@infopeak.io>`.
- Never move or rewrite a published tag or release asset: sites that already read `updates.xml` would
  fail the sha512 check.
