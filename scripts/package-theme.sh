#!/usr/bin/env bash

# Build a deterministic, installable WordPress theme package. The working tree
# is deliberately never edited: the edge version exists only in the archive.
set -euo pipefail

readonly SLUG='mumega-motion-theme'
readonly REQUIRES_WP='6.5'
readonly REQUIRES_PHP='7.4'
readonly NORMALIZED_TIMESTAMP='198001010000'

is_valid_manifest_timestamp() {
	local timestamp="$1"
	local year
	local month
	local day
	local hour
	local minute
	local second
	local offset_hour
	local offset_minute
	local days_in_month

	if [[ ! ${timestamp} =~ ^([0-9]{4})-([0-9]{2})-([0-9]{2})T([0-9]{2}):([0-9]{2}):([0-9]{2})(Z|[+-]([0-9]{2}):([0-9]{2}))$ ]]; then
		return 1
	fi

	year=$(( 10#${BASH_REMATCH[1]} ))
	month=$(( 10#${BASH_REMATCH[2]} ))
	day=$(( 10#${BASH_REMATCH[3]} ))
	hour=$(( 10#${BASH_REMATCH[4]} ))
	minute=$(( 10#${BASH_REMATCH[5]} ))
	second=$(( 10#${BASH_REMATCH[6]} ))
	offset_hour=$(( 10#${BASH_REMATCH[8]:-0} ))
	offset_minute=$(( 10#${BASH_REMATCH[9]:-0} ))

	if (( month < 1 || month > 12 || hour > 23 || minute > 59 || second > 59 || offset_hour > 23 || offset_minute > 59 )); then
		return 1
	fi

	case "${month}" in
		2)
			days_in_month=28
			if (( year % 4 == 0 && ( year % 100 != 0 || year % 400 == 0 ) )); then
				days_in_month=29
			fi
			;;
		4|6|9|11)
			days_in_month=30
			;;
		*)
			days_in_month=31
			;;
	esac

	(( day >= 1 && day <= days_in_month ))
}

if [[ $# -ne 1 || ! $1 =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then
	printf 'Usage: %s VERSION (VERSION must use canonical MAJOR.MINOR.PATCH)\n' "$0" >&2
	exit 64
fi

readonly VERSION="$1"
readonly ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly DIST_DIR="${ROOT_DIR}/dist"
readonly ARCHIVE_NAME="${SLUG}-${VERSION}.zip"
readonly ARCHIVE_PATH="${DIST_DIR}/${ARCHIVE_NAME}"
readonly SHA_PATH="${ARCHIVE_PATH}.sha256"
readonly MANIFEST_PATH="${DIST_DIR}/manifest.json"

if [[ -n ${MUMEGA_MOTION_MANIFEST_PUBLISHED_AT+x} ]]; then
	manifest_published_at="${MUMEGA_MOTION_MANIFEST_PUBLISHED_AT}"
else
	manifest_published_at="$(git -C "${ROOT_DIR}" show -s --format=%cI HEAD)"
fi

if ! is_valid_manifest_timestamp "${manifest_published_at}"; then
	printf 'MUMEGA_MOTION_MANIFEST_PUBLISHED_AT must be a real ISO-8601/RFC3339 timestamp.\n' >&2
	exit 64
fi

readonly MANIFEST_PUBLISHED_AT="${manifest_published_at}"
unset manifest_published_at

readonly STAGING_DIR="$(mktemp -d "${TMPDIR:-/tmp}/${SLUG}.XXXXXX")"
readonly STAGED_THEME="${STAGING_DIR}/${SLUG}"

cleanup() {
	rm -rf "${STAGING_DIR}"
}
trap cleanup EXIT

cd "${ROOT_DIR}"

for runtime_path in \
	style.css \
	theme.json \
	functions.php \
	index.php \
	header.php \
	footer.php \
	page.php \
	single.php \
	home.php \
	archive.php \
	search.php \
	404.php \
	build \
	inc \
	assets \
	page-templates \
	template-parts; do
	if [[ ! -e ${runtime_path} ]]; then
		printf 'Required runtime path is missing: %s\n' "${runtime_path}" >&2
		exit 1
	fi
done

mkdir -p "${STAGED_THEME}"

# This explicit allowlist is the package policy. It excludes source, tests,
# documentation, Composer/Node tooling, Git data, workflows, and local output.
for runtime_path in \
	style.css \
	theme.json \
	functions.php \
	index.php \
	header.php \
	footer.php \
	page.php \
	single.php \
	home.php \
	archive.php \
	search.php \
	404.php \
	build \
	inc \
	assets \
	page-templates \
	template-parts; do
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
  "requires_wordpress": "${REQUIRES_WP}",
  "requires_php": "${REQUIRES_PHP}",
  "published_at": "${MANIFEST_PUBLISHED_AT}"
}
EOF

printf 'Created %s\nCreated %s\nCreated %s\n' "${ARCHIVE_PATH}" "${SHA_PATH}" "${MANIFEST_PATH}"
