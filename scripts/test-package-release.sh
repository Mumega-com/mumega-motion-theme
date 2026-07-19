#!/usr/bin/env bash

# Regression tests for the deterministic theme package and the release-workflow
# safety contract. These run locally; they do not contact GitHub or publish.
set -euo pipefail

readonly ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
readonly VERSION='0.2.0'
readonly ARCHIVE="mumega-motion-theme-${VERSION}.zip"
readonly DIST_DIR="${ROOT_DIR}/dist"
readonly WORKFLOW="${ROOT_DIR}/.github/workflows/edge-release.yml"
readonly PUBLISHED_AT='2026-07-19T01:02:03Z'

export MUMEGA_MOTION_MANIFEST_PUBLISHED_AT="${PUBLISHED_AT}"

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
  requires_wordpress: '6.5',
  requires_php: '7.4',
  published_at: '2026-07-19T01:02:03Z',
};
if (JSON.stringify(manifest) !== JSON.stringify(expected)) {
  throw new Error(`Unexpected manifest: ${JSON.stringify(manifest)}`);
}
NODE

unzip -Z1 "${DIST_DIR}/${ARCHIVE}" > "${DIST_DIR}/contents.txt"
required_runtime_files=(
	style.css
	theme.json
	functions.php
	index.php
	header.php
	footer.php
	page.php
	single.php
	home.php
	archive.php
	search.php
	404.php
	build/index.js
	build/index.asset.php
	assets/css/editorial.css
	assets/css/print.css
	page-templates/editorial-page.php
	page-templates/editorial-home.php
	template-parts/article-meta.php
	template-parts/content-card-compact.php
	template-parts/content-card.php
	template-parts/empty-state.php
	template-parts/lead-story.php
	template-parts/newsletter.php
	template-parts/section-heading.php
	inc/editorial-helpers.php
	inc/editorial-islands.php
	inc/editorial-patterns.php
	inc/editorial-queries.php
	inc/editorial-setup.php
)
for required in "${required_runtime_files[@]}"; do
	grep -Fxq "mumega-motion-theme/${required}" "${DIST_DIR}/contents.txt" || fail "Missing runtime file: ${required}"
done

expected_top_level_paths=$'404.php\narchive.php\nassets\nbuild\nfooter.php\nfunctions.php\nheader.php\nhome.php\ninc\nindex.php\npage-templates\npage.php\nsearch.php\nsingle.php\nstyle.css\ntemplate-parts\ntheme.json'
actual_top_level_paths="$({
	sed -n 's#^mumega-motion-theme/\([^/][^/]*\)/.*#\1#p' "${DIST_DIR}/contents.txt"
	sed -n 's#^mumega-motion-theme/\([^/][^/]*\)$#\1#p' "${DIST_DIR}/contents.txt"
} | LC_ALL=C sort -u)"
test "${actual_top_level_paths}" = "${expected_top_level_paths}" || fail 'Package top-level runtime allowlist is not exact.'
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

# The producer and consumer share canonical SemVer. Reject values that could
# create an immutable release the installed client cannot discover, plus input
# shapes that must never be interpreted as shell source.
readonly PAYLOAD_MARKER="${TMPDIR:-/tmp}/mumega-motion-release-payload"
rm -f "${PAYLOAD_MARKER}"
invalid_versions=(
	''
	'v0.2.0'
	'0.2'
	'0.2.0-beta'
	'01.2.0'
	' 0.2.0'
	'0.2.0 '
	$'0.2.0\n'
	"0.2.0; touch ${PAYLOAD_MARKER}"
	'$(touch /tmp/mumega-motion-release-payload)'
)
for invalid_version in "${invalid_versions[@]}"; do
	set +e
	./scripts/package-theme.sh "${invalid_version}" >/dev/null 2>&1
	status=$?
	set -e
	test "${status}" -eq 64 || fail "Packager accepted noncanonical version: $(printf '%q' "${invalid_version}")"
done
test ! -e "${PAYLOAD_MARKER}" || fail 'A shell-payload-shaped version was executed.'

# Release workflow invariants: immutable, verified tag and release bindings.
assert_verification_trigger_targets_master() {
	local event_name="$1"

	awk -v event_name="${event_name}" '
	$0 == "  " event_name ":" {
		in_trigger = 1
		next
	}
	in_trigger && /^  [[:alnum:]_-]+:$/ {
		exit
	}
	in_trigger && $0 == "    branches:" {
		branches = 1
	}
	in_trigger && $0 == "      - master" {
		master = 1
	}
	END {
		exit branches && master ? 0 : 1
	}
	' "${WORKFLOW}" || fail "${event_name} events to master must run the verification job."
}

assert_verification_trigger_targets_master 'pull_request'
assert_verification_trigger_targets_master 'push'

awk '
	$0 == "  workflow_dispatch:" {
		in_dispatch = 1
		next
	}
	in_dispatch && /^  [[:alnum:]_-]+:$/ {
		exit
	}
	in_dispatch && $0 == "    inputs:" {
		inputs = 1
	}
	in_dispatch && $0 == "      version:" {
		version = 1
	}
	in_dispatch && version && $0 == "        required: true" {
		required = 1
	}
	in_dispatch && version && $0 == "        type: string" {
		string_type = 1
	}
	END {
		exit inputs && version && required && string_type ? 0 : 1
	}
' "${WORKFLOW}" || fail 'Manual dispatch must require a string version input.'

awk '
	$0 == "  release:" {
		in_release = 1
		next
	}
	in_release && /^  [[:alnum:]_-]+:$/ {
		exit
	}
	in_release && $0 == "    if: github.event_name == '\''workflow_dispatch'\'' && github.ref == '\''refs/heads/master'\''" {
		guarded = 1
	}
	END {
		exit guarded ? 0 : 1
	}
	' "${WORKFLOW}" || fail 'The release job must publish only for a manual dispatch from master.'
assert_not_contains "github.event_name == 'push'" "${WORKFLOW}"
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
assert_contains 'INPUT_VERSION: ${{ inputs.version }}' "${WORKFLOW}"
inputs_version_reference_count="$(grep -Fc '${{ inputs.version }}' "${WORKFLOW}" || true)"
test "${inputs_version_reference_count}" -eq 1 || fail 'The manual input must appear only in the step environment mapping, never in shell source.'
assert_contains 'if [[ ! "$INPUT_VERSION" =~ ^(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)\.(0|[1-9][0-9]*)$ ]]; then' "${WORKFLOW}"
assert_contains 'VERSION="${INPUT_VERSION}"' "${WORKFLOW}"
assert_contains 'TAG="edge-v${VERSION}"' "${WORKFLOW}"
assert_contains 'echo "VERSION=${VERSION}"' "${WORKFLOW}"
assert_contains 'echo "TAG=${TAG}"' "${WORKFLOW}"
assert_not_contains 'GITHUB_RUN_NUMBER' "${WORKFLOW}"
assert_contains '} >> "$GITHUB_ENV"' "${WORKFLOW}"
manifest_timestamp_derivation_count="$(grep -Fc 'git show -s --format=%cI "${GITHUB_SHA}"' "${WORKFLOW}" || true)"
test "${manifest_timestamp_derivation_count}" -eq 2 || fail 'Verify and release jobs must derive the manifest timestamp from the triggering commit.'
manifest_timestamp_export_count="$(grep -Fc 'echo "MUMEGA_MOTION_MANIFEST_PUBLISHED_AT=${published_at}"' "${WORKFLOW}" || true)"
test "${manifest_timestamp_export_count}" -eq 2 || fail 'Verify and release jobs must pass the manifest timestamp through the environment.'
assert_contains 'git rev-parse "refs/tags/${TAG}^{}"' "${WORKFLOW}"
assert_contains 'git ls-remote --exit-code --tags origin "refs/tags/${TAG}"' "${WORKFLOW}"
assert_contains 'grep -Eq '\''^\*.*\[new tag\]$'\''' "${WORKFLOW}"
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
assert_contains 'find functions.php index.php header.php footer.php page.php single.php home.php archive.php search.php 404.php page-templates template-parts inc -type f -name' "${WORKFLOW}"
assert_step_contains 'Run JavaScript behavior tests' 'npm run test:js'
assert_step_contains 'Run PHPUnit and lint shipped PHP' 'vendor/bin/phpunit -c phpunit.xml.dist'
assert_step_contains 'Verify package layout and manifest' 'expected_top_level_paths='
assert_step_contains 'Verify package layout and manifest' 'actual_top_level_paths='
assert_step_contains 'Verify package layout and manifest' 'requires_wordpress:"6.5"'
assert_step_contains 'Verify package layout and manifest' 'published_at:process.env.MUMEGA_MOTION_MANIFEST_PUBLISHED_AT'
for required in "${required_runtime_files[@]}"; do
	assert_step_contains 'Verify package layout and manifest' "${required}"
done
assert_not_contains 'stream-demo.php' "${WORKFLOW}"

# Exercise the exact peeled-ref selection used by the workflow: a matching
# tag object SHA must not be mistaken for the dereferenced commit SHA.
test_tag='edge-v0.2.0'
expected_sha='0123456789abcdef0123456789abcdef01234567'
remote_sha="$({
	printf '%s %s\n' 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' "refs/tags/${test_tag}"
	printf '%s %s\n' "${expected_sha}" "refs/tags/${test_tag}^{}"
} | awk -v peeled_ref="refs/tags/${test_tag}^{}" '$2 == peeled_ref { print $1; exit }')"
test -n "${remote_sha}" || fail 'Peeled remote tag SHA must be nonempty.'
test "${remote_sha}" = "${expected_sha}" || fail 'Peeled remote tag SHA did not select the triggering commit.'

printf 'Package and release workflow checks passed.\n'
