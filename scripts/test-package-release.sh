#!/usr/bin/env bash

# Regression tests for the deterministic theme package and the release-workflow
# safety contract. These run locally; they do not contact GitHub or publish.
set -euo pipefail

readonly ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly VERSION='0.1.987'
readonly ARCHIVE="mumega-motion-theme-${VERSION}.zip"
readonly DIST_DIR="${ROOT_DIR}/dist"
readonly WORKFLOW="${ROOT_DIR}/.github/workflows/edge-release.yml"

fail() {

	printf 'FAIL: %s\n' "$*" >&2
	exit 1
}

assert_contains() {

	local needle="$1"
	local file="$2"
	grep -F --quiet -- "$needle" "$file" || fail "Expected ${file} to contain: ${needle}"
}

assert_not_contains() {

	local needle="$1"
	local file="$2"
	if grep -F --quiet -- "$needle" "$file"; then
		fail "Expected ${file} not to contain: ${needle}"
	fi
}

cleanup() {
	rm -f "${ROOT_DIR}/inc/README.package-release-test"
	rm -rf "${DIST_DIR}"
}
trap cleanup EXIT

cd "${ROOT_DIR}"
rm -rf "${DIST_DIR}"

./scripts/package-theme.sh "${VERSION}"
first_digest="$(shasum -a 256 "${DIST_DIR}/${ARCHIVE}" | awk '{print $1}')"

# The checksum file names the archive, so verify it from the directory that
# contains that archive. This catches a path-dependent sidecar check.
(
	cd "${DIST_DIR}"
	sha256sum -c "${ARCHIVE}.sha256"
)

node - "${DIST_DIR}/manifest.json" "${first_digest}" "${VERSION}" <<'NODE'
const fs = require('fs');
const [manifestPath, digest, version] = process.argv.slice(2);
const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
const archive = `mumega-motion-theme-${version}.zip`;
const expected = {
  slug: 'mumega-motion-theme',
  version,
  package_url: `https://github.com/Mumega-com/mumega-motion-theme/releases/download/edge-v${version}/${archive}`,
  sha256: digest,
  requires_wp: '6.5',
  requires_php: '7.4',
};
if (JSON.stringify(manifest) !== JSON.stringify(expected)) {
  throw new Error(`Unexpected manifest: ${JSON.stringify(manifest)}`);
}
NODE

unzip -Z1 "${DIST_DIR}/${ARCHIVE}" > "${DIST_DIR}/contents.txt"
for required in style.css functions.php index.php stream-demo.php build/index.js build/index.asset.php; do
	grep -Fxq "mumega-motion-theme/${required}" "${DIST_DIR}/contents.txt" || fail "Missing runtime file: ${required}"
done
if grep -E '/(src|tests|docs|scripts|node_modules|vendor|\.git|\.github)(/|$)|\.(map|md)$' "${DIST_DIR}/contents.txt"; then
	fail 'Package contains development-only content.'
fi

./scripts/package-theme.sh "${VERSION}"
second_digest="$(shasum -a 256 "${DIST_DIR}/${ARCHIVE}" | awk '{print $1}')"
test "${first_digest}" = "${second_digest}" || fail 'Packaging is not reproducible.'

# A disallowed documentation file beneath an otherwise allowed runtime path
# must reject the package instead of silently shipping future dev content.
printf 'package test fixture\n' > "${ROOT_DIR}/inc/README.package-release-test"
if ./scripts/package-theme.sh "${VERSION}"; then
	fail 'Packager accepted a development file beneath an allowed runtime path.'
fi
rm -f "${ROOT_DIR}/inc/README.package-release-test"

# Release workflow invariants: immutable, verified tag and release bindings.
while IFS= read -r action; do
	[[ "${action}" =~ @[0-9a-f]{40}(\ #.*)?$ ]] || fail "Action is not pinned to a full commit SHA: ${action}"
done < <(grep -E '^ +uses: ' "${WORKFLOW}")

checkout_count="$(grep -c 'uses: actions/checkout@' "${WORKFLOW}")"
credentials_disabled_count="$(grep -c 'persist-credentials: false' "${WORKFLOW}")"
test "${checkout_count}" -eq "${credentials_disabled_count}" || fail 'Each checkout must disable persisted credentials.'
assert_contains 'git tag -a "${TAG}" "${GITHUB_SHA}"' "${WORKFLOW}"
assert_contains 'git push --porcelain origin "refs/tags/${TAG}:refs/tags/${TAG}"' "${WORKFLOW}"
assert_contains 'git ls-remote --exit-code origin "refs/tags/${TAG}^{}"' "${WORKFLOW}"
assert_contains 'git rev-parse "refs/tags/${TAG}^{}"' "${WORKFLOW}"
assert_contains 'gh release create "$TAG"' "${WORKFLOW}"
assert_contains '--verify-tag' "${WORKFLOW}"
assert_not_contains '--target "${GITHUB_SHA}"' "${WORKFLOW}"
assert_contains 'cd dist' "${WORKFLOW}"
assert_contains 'sha256sum -c "mumega-motion-theme-${VERSION}.zip.sha256"' "${WORKFLOW}"
assert_contains "repository administrators must enable GitHub's Immutable Releases" "${WORKFLOW}"
assert_contains 'release.immutable!==true' "${WORKFLOW}"
assert_contains 'release.assets.map' "${WORKFLOW}"

printf 'Package and release workflow checks passed.\n'
