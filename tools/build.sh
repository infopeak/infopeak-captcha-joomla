#!/usr/bin/env bash
# Builds the installable package from the committed plugin folder and writes
# updates.xml for that exact zip (version, download URL, sha512).
#
#   tools/build.sh            refuses to run on an uncommitted plugin folder
#   tools/build.sh --dirty    builds the working tree as it is (local testing only)
#
# Publishes nothing. See RELEASING.md for the release steps.
set -euo pipefail
cd "$(dirname "$0")/.."

SRC=plg_captcha_infopeak
REPO=https://github.com/infopeak/infopeak-captcha-joomla

version=$(sed -n 's:.*<version>\(.*\)</version>.*:\1:p' "$SRC/infopeak.xml" | head -1)
code_version=$(sed -n "s:.*VERSION = '\(.*\)';.*:\1:p" "$SRC/src/Service/InfoPeak.php")

if [ "$version" != "$code_version" ]; then
  echo "Version mismatch: infopeak.xml says $version, InfoPeak::VERSION says $code_version" >&2
  exit 1
fi

if ! grep -q "<version>$version</version>" changelog.xml; then
  echo "changelog.xml has no entry for $version" >&2
  exit 1
fi

if ! cmp -s LICENSE "$SRC/LICENSE.txt"; then
  echo "$SRC/LICENSE.txt differs from LICENSE" >&2
  exit 1
fi

mkdir -p build
zip="build/plg_captcha_infopeak-$version.zip"
rm -f "$zip"

if [ "${1:-}" = "--dirty" ]; then
  (cd "$SRC" && python -m zipfile -c "../$zip" $(ls -A))
else
  if [ -n "$(git status --porcelain -- "$SRC" changelog.xml)" ]; then
    echo "Uncommitted changes in $SRC or changelog.xml. Commit first, or use --dirty for local testing." >&2
    exit 1
  fi
  git archive --format=zip -o "$zip" "HEAD:$SRC"
fi

sha=$(sha512sum "$zip" | cut -d' ' -f1)

cat > updates.xml <<XML
<?xml version="1.0" encoding="UTF-8"?>
<updates>
	<update>
		<name>InfoPeak Captcha</name>
		<description>InfoPeak Captcha for Joomla</description>
		<element>infopeak</element>
		<type>plugin</type>
		<folder>captcha</folder>
		<client>site</client>
		<version>$version</version>
		<infourl title="InfoPeak Captcha $version">$REPO/releases/tag/v$version</infourl>
		<downloads>
			<downloadurl type="full" format="zip">$REPO/releases/download/v$version/plg_captcha_infopeak-$version.zip</downloadurl>
		</downloads>
		<sha512>$sha</sha512>
		<targetplatform name="joomla" version="(5|6)\.[0-9]+" />
		<php_minimum>8.1</php_minimum>
		<maintainer>InfoPeak</maintainer>
		<maintainerurl>https://infopeak.io</maintainerurl>
	</update>
</updates>
XML

echo "Built $zip"
echo "sha512 $sha"
