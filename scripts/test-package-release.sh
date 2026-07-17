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
	rm -f "${ROOT_DIR}/inc/DEVELOPMENT.MD"
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
if grep -Ei '/(src|tests|docs|scripts|node_modules|vendor|\.git|\.github)(/|$)|\.(map|md)$' "${DIST_DIR}/contents.txt"; then
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

# Case variants of development extensions must be rejected too.
printf 'package test fixture\n' > "${ROOT_DIR}/inc/DEVELOPMENT.MD"
if ./scripts/package-theme.sh "${VERSION}"; then
	fail 'Packager accepted an uppercase development file extension beneath an allowed runtime path.'
fi
rm -f "${ROOT_DIR}/inc/DEVELOPMENT.MD"

# Release workflow invariants: immutable, verified tag and release bindings.
while IFS= read -r action; do
	[[ "${action}" =~ @[0-9a-f]{40}(\ #.*)?$ ]] || fail "Action is not pinned to a full commit SHA: ${action}"
done < <(grep -E '^ +uses: ' "${WORKFLOW}")

checkout_count="$(grep -c 'uses: actions/checkout@' "${WORKFLOW}")"
credentials_disabled_count="$(grep -c 'persist-credentials: false' "${WORKFLOW}")"
test "${checkout_count}" -eq "${credentials_disabled_count}" || fail 'Each checkout must disable persisted credentials.'
assert_contains 'git tag -a "${TAG}" "${GITHUB_SHA}"' "${WORKFLOW}"
assert_contains 'git config --local user.name "github-actions[bot]"' "${WORKFLOW}"
assert_contains 'git config --local user.email "41898282+github-actions[bot]@users.noreply.github.com"' "${WORKFLOW}"
assert_contains 'push --porcelain origin "refs/tags/${TAG}:refs/tags/${TAG}"' "${WORKFLOW}"
assert_contains 'echo "VERSION=0.1.${GITHUB_RUN_NUMBER}" >> "$GITHUB_ENV"' "${WORKFLOW}"
assert_contains 'echo "TAG=edge-v0.1.${GITHUB_RUN_NUMBER}" >> "$GITHUB_ENV"' "${WORKFLOW}"
assert_contains 'git rev-parse "refs/tags/${TAG}^{}"' "${WORKFLOW}"
assert_contains 'credential.helper=!f() {' "${WORKFLOW}"
assert_contains 'GITHUB_TOKEN: ${{ github.token }}' "${WORKFLOW}"
assert_contains 'awk -v peeled_ref="refs/tags/${TAG}^{}"' "${WORKFLOW}"
assert_contains 'test -n "$remote_sha"' "${WORKFLOW}"

assert_step_contains() {
	local step_name="$1"
	local needle="$2"

	awk -v step_name="${step_name}" -v needle="${needle}" '
		$0 == "      - name: " step_name {
			in_step = 1
			next
		}
		in_step && /^      - name: / {
			exit
		}
		in_step && index($0, needle) {
			found = 1
		}
		END {
			exit found ? 0 : 1
		}
	' "${WORKFLOW}" || fail "Workflow step ${step_name} is missing: ${needle}"
}

# Each authenticated remote peeled-tag check must use the same ephemeral
# credential helper as the tag push; checkout never persists credentials.
for verification_step in \
	'Verify the new annotated tag resolves to the verified commit' \
	'Verify the published release is immutable and correctly bound'; do
	assert_step_contains "${verification_step}" 'GITHUB_TOKEN: ${{ github.token }}'
	assert_step_contains "${verification_step}" 'credential.helper=!f() {'
	assert_step_contains "${verification_step}" 'ls-remote --exit-code origin "refs/tags/${TAG}^{}"'
done

# The peeled-ref pipelines must run under Bash so their pipefail contract is
# explicit, both before publishing and after the release has been created.
peeled_ref_bash_count="$(grep -c '^        shell: bash$' "${WORKFLOW}" || true)"
test "${peeled_ref_bash_count}" -eq 2 || fail 'Both peeled-ref verification steps must explicitly use Bash.'

assert_contains 'gh release create "$TAG"' "${WORKFLOW}"
assert_contains '--verify-tag' "${WORKFLOW}"
assert_not_contains '--target "${GITHUB_SHA}"' "${WORKFLOW}"
assert_contains 'cd dist' "${WORKFLOW}"
assert_contains 'sha256sum -c "mumega-motion-theme-${VERSION}.zip.sha256"' "${WORKFLOW}"
assert_contains "repository administrators must enable GitHub's Immutable Releases" "${WORKFLOW}"
assert_contains 'release.immutable!==true' "${WORKFLOW}"
assert_contains 'release.assets.map' "${WORKFLOW}"
assert_contains 'grep -Ei' "${WORKFLOW}"
assert_contains "-iname '*.map'" "${ROOT_DIR}/scripts/package-theme.sh"
assert_contains "-iname '*.md'" "${ROOT_DIR}/scripts/package-theme.sh"

# Exercise the exact peeled-ref selection used by the workflow: a matching
# tag object SHA must not be mistaken for the dereferenced commit SHA.
test_tag='edge-v0.1.987'
expected_sha='0123456789abcdef0123456789abcdef01234567'
remote_sha="$({
	printf '%s %s\n' 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' "refs/tags/${test_tag}"
	printf '%s %s\n' "${expected_sha}" "refs/tags/${test_tag}^{}"
} | awk -v peeled_ref="refs/tags/${test_tag}^{}" '$2 == peeled_ref { print $1; exit }')"
test -n "${remote_sha}" || fail 'Peeled remote tag SHA must be nonempty.'
test "${remote_sha}" = "${expected_sha}" || fail 'Peeled remote tag SHA did not select the triggering commit.'

printf 'Package and release workflow checks passed.\n'
