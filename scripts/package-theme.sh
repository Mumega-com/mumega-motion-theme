#!/usr/bin/env bash

# Build a deterministic, installable WordPress theme package. The working tree
# is deliberately never edited: the edge version exists only in the archive.
set -euo pipefail

readonly SLUG='mumega-motion-theme'
readonly REQUIRES_WP='6.5'
readonly REQUIRES_PHP='7.4'
readonly NORMALIZED_TIMESTAMP='198001010000'

if [[ $# -ne 1 || ! $1 =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
	printf 'Usage: %s VERSION (VERSION must use MAJOR.MINOR.PATCH)\n' "$0" >&2
	exit 64
fi

readonly VERSION="$1"
readonly ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly DIST_DIR="${ROOT_DIR}/dist"
readonly ARCHIVE_NAME="${SLUG}-${VERSION}.zip"
readonly ARCHIVE_PATH="${DIST_DIR}/${ARCHIVE_NAME}"
readonly SHA_PATH="${ARCHIVE_PATH}.sha256"
readonly MANIFEST_PATH="${DIST_DIR}/manifest.json"
readonly STAGING_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${SLUG}.XXXXXX")"
readonly STAGED_THEME="${STAGING_DIR}/${SLUG}"

cleanup() {
	rm -rf "${STAGING_DIR}"
}
trap cleanup EXIT

cd "${ROOT_DIR}"

for runtime_path in style.css functions.php index.php stream-demo.php build inc; do
	if [[ ! -e ${runtime_path} ]]; then
		printf 'Required runtime path is missing: %s\n' "${runtime_path}" >&2
		exit 1
	fi
done

mkdir -p "${STAGED_THEME}"

# This explicit allowlist is the package policy. It excludes source, tests,
# documentation, Composer/Node tooling, Git data, workflows, and local output.
for runtime_path in style.css functions.php index.php stream-demo.php build inc; do
	cp -R "${runtime_path}" "${STAGED_THEME}/${runtime_path}"
done

# Source maps are development-only even when a build tool leaves one in build/.
find "${STAGED_THEME}" -type f -iname '*.map' -delete

# The allowlist above is the primary boundary. These checks make the policy
# explicit and prevent a future runtime directory from silently carrying
# development material into the installable ZIP.
if find "${STAGED_THEME}" -type d \( \
	-name src -o -name tests -o -name test -o -name docs -o -name scripts -o \
	-name node_modules -o -name vendor -o -name .git -o -name .github \
\) -print -quit | grep -q .; then
	printf 'Runtime package contains a disallowed development directory.\n' >&2
	exit 1
fi

if find "${STAGED_THEME}" -type f \( \
	-iname '*.map' -o -iname '*.md' -o -iname 'readme*' -o -iname composer.json -o \
	-iname composer.lock -o -iname package.json -o -iname package-lock.json \
\) -print -quit | grep -q .; then
	printf 'Runtime package contains a disallowed development file.\n' >&2
	exit 1
fi

if [[ -n $(find "${STAGED_THEME}" -type l -print -quit) ]]; then
	printf 'Runtime package must not contain symbolic links.\n' >&2
	exit 1
fi

# Patch the copy only. This intentionally does not modify the checkout's
# style.css, whose stable version remains the source version.
perl -0pi -e "s/^Version:[^\r\n]*/Version: ${VERSION}/m" "${STAGED_THEME}/style.css"
if ! grep -Fxq "Version: ${VERSION}" "${STAGED_THEME}/style.css"; then
	printf 'Unable to set the staged theme Version header.\n' >&2
	exit 1
fi

# ZIP format cannot represent dates before 1980. Fixed mtimes, permissions,
# ordering, and stripped extra fields make repeated packages byte-identical.
find "${STAGED_THEME}" -type d -exec chmod 755 {} +
find "${STAGED_THEME}" -type f -exec chmod 644 {} +
find "${STAGED_THEME}" -exec touch -h -t "${NORMALIZED_TIMESTAMP}" {} +

mkdir -p "${DIST_DIR}"
rm -f "${ARCHIVE_PATH}" "${SHA_PATH}"
(
	cd "${STAGING_DIR}"
	LC_ALL=C find "${SLUG}" -print | LC_ALL=C sort | zip -X -q "${ARCHIVE_PATH}" -@
)

readonly SHA256="$(shasum -a 256 "${ARCHIVE_PATH}" | awk '{print $1}')"
if [[ ! ${SHA256} =~ ^[0-9a-f]{64}$ ]]; then
	printf 'Unable to calculate a lowercase SHA-256 checksum.\n' >&2
	exit 1
fi

printf '%s  %s\n' "${SHA256}" "${ARCHIVE_NAME}" > "${SHA_PATH}"
cat > "${MANIFEST_PATH}" <<EOF
{
  "slug": "${SLUG}",
  "version": "${VERSION}",
  "package_url": "https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v${VERSION}/${ARCHIVE_NAME}",
  "sha256": "${SHA256}",
  "requires_wp": "${REQUIRES_WP}",
  "requires_php": "${REQUIRES_PHP}"
}
EOF

printf 'Created %s\nCreated %s\nCreated %s\n' "${ARCHIVE_PATH}" "${SHA_PATH}" "${MANIFEST_PATH}"
