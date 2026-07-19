# MCPWP Profile and Vertical-Slice Validation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Configure MCPWP Editorial Profile 1.0.0 on the existing non-front-page preview, install Mumega Motion 0.2.0 safely, and prove five bounded agent workflows produce evidence-backed WordPress drafts without autonomous publication.

**Architecture:** Use GitHub issues as first-release editorial state/brief artifacts and native WordPress drafts as the public-content source of truth. Configure only published, unprotected convention pages needed by the preview; keep the legacy Elementor page assigned as the public homepage. Merge generated Editorial Contract 1.0.0 instructions into MCPWP site context and verify readback before any agent draft operation.

**Tech Stack:** WordPress dashboard, MCPWP authenticated HTTP endpoint or logged-in admin fallback, Mumega Motion direct private update channel, GitHub issues, Lighthouse 13.4+, browser accessibility inspection.

## Global Constraints

- Current public homepage remains WordPress page 541.
- Current Editorial Home preview remains page 1130 until a replacement is explicitly approved.
- Do not delete or rewrite the legacy Elementor homepage.
- Do not change Reading Settings during this plan.
- Do not expose API keys, WordPress cookies, Site Kit credentials or other secrets in commands, issues, logs, drafts or site context.
- Use only MCPWP tools actually returned by initialization/tool discovery.
- Small agents create or update drafts only; only the human owner may publish.
- New policy/profile pages remain noindex until the launch gate, while still being publicly renderable for the theme convention.
- Consent, analytics and Privacy Policy changes require separate exact action-time approval.
- Preserve WordPress revisions and record every changed object ID.

---

## Evidence ledger

Create one private GitHub epic comment containing a table with these fields for every operational step:

```text
timestamp | actor | contract | object | before | action | after | evidence | rollback
```

Never place credentials or full response headers containing secrets in the ledger.

### Task 1: Capture authoritative production baseline and access

**Files:**
- Create: `docs/operations/mcpwp-profile-1.0.0-baseline.md`

**Produces:** a non-secret restore map before any WordPress or release mutation.

- [ ] **Step 1: Confirm repository state**

Run:

```bash
git status --short --branch
git log -3 --oneline --decorate
git tag --points-at origin/master
```

Expected: implementation branch is based on the reviewed design commits; no unrelated user files are staged.

- [ ] **Step 2: Confirm public and preview routes logged out**

Record HTTP status, final URL, title, H1, theme body classes and cache headers for:

```text
https://mcpwp.net/
https://mcpwp.net/mcpwp-editorial-home-2/
https://mcpwp.net/affiliate-disclosure/
```

Expected: root remains the legacy homepage; preview returns 200; disclosure returns 200.

- [ ] **Step 3: Confirm WordPress configuration read-only**

Through an authenticated MCPWP read tool or logged-in WordPress dashboard, record:

```text
show_on_front = page
page_on_front = 541
page_for_posts = 0
active theme = Mumega Motion
theme version = 0.1.19
preview page ID = 1130
preview template = page-templates/editorial-home.php
primary menu ID = 137
footer menu ID = 138
```

If any value differs, stop configuration and amend the plan with the observed authoritative state.

- [ ] **Step 4: Verify MCPWP operations before use**

Initialize the MCP endpoint using a locally stored secret, list tools, and record only tool names and non-secret capability descriptions. Verify the exact read, draft-create/update, media, menu, option and theme update/rollback operations needed later. If a capability is absent, assign that step to the logged-in dashboard; do not infer an endpoint.

- [ ] **Step 5: Write and commit the baseline**

The baseline contains no credential, cookie, bearer value or private response body.

```bash
git add docs/operations/mcpwp-profile-1.0.0-baseline.md
git commit -m "docs: record MCPWP profile baseline"
```

### Task 2: Create the GitHub epic, phase issues and editorial workflow labels

**External state:** private repository `Mumega-com/mumega-motion-theme`.

**Produces:** one epic and independently closable implementation/proof issues.

- [ ] **Step 1: Create labels if absent**

Create:

```text
program:agentic-editorial
component:contract
component:theme
component:site-profile
component:validation
state:brief-ready
state:brief-accepted
state:research-ready
state:research-accepted
state:drafting
state:technical-verification
state:discovery-review
state:human-review
state:update-due
```

Do not overload existing labels with a different meaning.

- [ ] **Step 2: Create the program epic**

Title: `Program: Mumega Motion 0.2.0 agentic editorial system`
Body: objective, links to the two specs and three plans, completion checklist, non-front-page constraint, release trains, rollback owner and five-format proof table.

- [ ] **Step 3: Create child issues**

Create one issue for each independently reviewable deliverable:

1. Editorial Contract schemas and manifest.
2. Format/role/rule pack.
3. Validator, fixtures and generated site context.
4. GitHub intake and CI enforcement.
5. WordPress conventions and audience menu.
6. Homepage deterministic query/data layer.
7. Homepage V2 template parts and semantics.
8. Six Gutenberg format patterns.
9. Visual/responsive/print implementation.
10. Explicit 0.2.0 packaging and release workflow.
11. MCPWP profile configuration.
12. Five-format bounded-agent validation.
13. Preview accessibility/performance/rollback audit.

Every issue links its controlling plan task, acceptance evidence and rollback boundary. Add child links to the epic.

- [ ] **Step 4: Verify external state**

Read the epic and each issue back from GitHub. Confirm private-repository URLs, labels, open state and complete checklist; record issue numbers in the baseline document.

### Task 3: Merge verified implementation and publish immutable 0.2.0

**External state:** GitHub pull request, Actions, release and direct update channel.

- [ ] **Step 1: Open implementation PRs from isolated branches**

PR order:

1. contract/validator;
2. theme/PHP/pattern/CSS;
3. release workflow/package verification.

Each PR links its child issues and includes exact local commands/results. Do not mix site content mutations into code PRs.

- [ ] **Step 2: Require green checks and review**

Confirm PHP 7.4 and 8.3 matrices, Node contract tests, JS tests, PHP syntax, PHPUnit and package-policy tests pass on the exact PR commit. Address failures before merge; never bypass required checks.

- [ ] **Step 3: Merge in dependency order**

After each merge, verify `origin/master` contains the expected commit and the next PR is rebased/updated without unrelated changes.

- [ ] **Step 4: Dispatch exact version 0.2.0**

Run the updated release workflow on `master` with input `0.2.0`. Verify:

```text
tag = edge-v0.2.0
release prerelease = true
release immutable = true
assets = mumega-motion-theme-0.2.0.zip, checksum, manifest.json
tag peeled SHA = verified master SHA
manifest version = 0.2.0
```

- [ ] **Step 5: Verify direct update discovery read-only**

From a test or the live dashboard update check, confirm the installed 0.1.19 theme sees 0.2.0 with the expected package URL and requirements. Do not install until rollback evidence is recorded.

### Task 4: Install 0.2.0 on MCPWP with rollback proof

**External state:** MCPWP.net theme installation; public root must remain page 541.

- [ ] **Step 1: Capture the pre-update theme backup/rollback identifier**

Use the actual MCPWP theme update mechanism or dashboard. Record installed version, target version, backup identifier/path abstraction and rollback operation name without exposing secrets or filesystem internals unnecessarily.

- [ ] **Step 2: Install the verified package**

Use the explicit update operation against `edge-v0.2.0` or install the exact release ZIP through WordPress. Verify the package SHA256 against the immutable release before installation.

- [ ] **Step 3: Verify installation**

Read back:

```text
active theme = Mumega Motion
installed version = 0.2.0
root homepage ID = 541
preview page ID = 1130
preview template = Editorial Home
```

Fetch both routes logged out. Root must remain visually/functionally unchanged; preview may show clean fallback modules before profile content exists.

- [ ] **Step 4: Prove rollback, then restore 0.2.0**

Use the supported rollback operation to restore 0.1.19, verify the installed version and both routes, then reinstall 0.2.0 and reverify. If rollback changes Reading Settings or content, stop and restore before continuing.

### Task 5: Configure MCPWP Editorial Profile 1.0.0

**External state:** WordPress pages, media, menus, taxonomies and preview page.

- [ ] **Step 1: Upload approved ASTER media**

Use the approved identity artwork from the design assets. Create an optimized web derivative appropriate for the hero before upload. Set descriptive alt text on the profile media and empty alt where the same identity is repeated next to text. Record attachment ID and dimensions.

- [ ] **Step 2: Create/update profile pages as drafts**

Prepare these exact slugs:

```text
editorial-guide
editorial-methodology
knowledge-map
ai-disclosure
newsletter
```

ASTER page title is `ASTER`; excerpt states `AI Research Editor · AI-assisted research, human-reviewed`; featured image is the approved portrait. Methodology describes Install/Connect/Verify/Recover without implying every article was tested. Knowledge Map states that the first release uses categories, entity tags and contextual links, not computed graph relationships. AI Disclosure identifies model assistance and human responsibility. Newsletter embeds the existing WPForms form only after consent copy and destination are verified.

- [ ] **Step 3: Review and publish convention pages noindex**

After human content review, publish the pages required by the theme convention and set explicit Yoast noindex until the launch gate. Verify each public URL returns 200 logged out and is absent from navigation unless intentionally linked.

- [ ] **Step 4: Configure the Audience Pathways menu**

Create/assign up to three items in order:

```text
I run a WordPress site | Practical AI improvements without a rebuild.
I manage client websites | Governed workflows your agency can repeat.
I build WordPress systems | Architecture, MCP and implementation guides.
```

Point each to a real published topic/audience destination; do not use `#` links.

- [ ] **Step 5: Configure topic categories and descriptions**

Map public categories to Understand, Build, Govern, Grow and Test without mass-changing existing slugs or URLs. Add useful public descriptions, preserve secondary categories, stop assigning General, and keep Releases excluded from automatic promotion.

- [ ] **Step 6: Configure preview page 1130**

Set:

```text
title = Your WordPress site can have an AI future.
excerpt = Tested tools, practical workflows and independent guidance for using AI and MCP—without rebuilding everything.
template = page-templates/editorial-home.php
status = publish
```

Author core-button content for the primary guide and tested-workflows links plus the audience trust line. Do not assign the page as front page.

- [ ] **Step 7: Configure menus/footer and featured content**

Assign the intended Primary, Footer and Audiences menus. Make one reviewed current briefing sticky. Verify enough distinct posts exist for coverage, guides and tools; underfilled modules must omit rather than use weak content.

- [ ] **Step 8: Read back every object**

Record IDs, slugs, statuses, templates, noindex state, menu assignments, sticky ID and preview output. Confirm page 541 remains `page_on_front`.

### Task 6: Install Editorial Contract 1.0.0 into MCPWP site context

**External state:** MCPWP site context.

- [ ] **Step 1: Generate and verify local context**

Run:

```bash
npm run generate:editorial-context
npm run validate:editorial-contract
git diff --exit-code -- editorial/generated/mcpwp-site-context.md
```

Expected: deterministic committed output with version/hash and no secrets.

- [ ] **Step 2: Read current site context**

Preserve all still-current operational context. Remove only superseded editorial rules after comparing meaning; never overwrite unrelated plugin, rollback or security information.

- [ ] **Step 3: Merge the generated section**

Add one delimited section:

```text
BEGIN MUMEGA EDITORIAL CONTRACT 1.0.0
<generated content>
END MUMEGA EDITORIAL CONTRACT 1.0.0
```

Ensure exactly one active editorial-contract section exists.

- [ ] **Step 4: Verify readback**

Read site context again and compare the contract version and SHA-256 with the repository output. Verify prior non-editorial context remains. Record only length, version, hash and timestamp—not the full context—in GitHub evidence.

### Task 7: Prove five bounded content workflows

**External state:** five GitHub editorial issues and five WordPress drafts.

**Representative vertical slices:**

| Format | Candidate |
|---|---|
| Explainer | Existing article 711: what a WordPress MCP server is |
| Practical guide | Existing article 715: secure WordPress MCP setup |
| Test report | Existing article 1008: governed AI operator test |
| Comparison/review | Official WordPress MCP Adapter vs MCPWP: roles, overlap and fit |
| News briefing | Current WordPress/WordPress.com MCP change from a primary announcement |

Candidates may be replaced only when authoritative current state makes one invalid; record the reason and retain one item per format.

- [ ] **Step 1: Create accepted briefs**

Use the issue form and machine-readable schema. A human accepts each brief before research. Run schema validation and duplicate-intent check. Existing published articles are updated as drafts/revisions rather than cloned to new URLs.

- [ ] **Step 2: Dispatch bounded research roles**

Each researcher receives only its accepted brief and contract. Require current primary sources, counterevidence, version/date, forbidden claims and artifacts. Validate the research packet before writing.

- [ ] **Step 3: Dispatch bounded writers**

Each writer receives only one accepted brief, one accepted research packet, the matching format template and actual discovered MCPWP draft tools. The writer returns draft ID, preview URL, slug and validation report. Assert status is draft or pending, never published.

- [ ] **Step 4: Dispatch technical verification**

For procedural/test/comparison items, reproduce claimed operations in an authorized safe environment and record failures, permissions, versions and rollback. Explainer/news items receive source/version verification without invented execution.

- [ ] **Step 5: Dispatch discovery review**

Check canonical intent, title/excerpt/body agreement, headings, text availability, entity consistency, source links, descriptive internal links, media alt text and disclosure. Reject keyword variants, unsupported FAQ and ranking claims.

- [ ] **Step 6: Produce human handoffs**

Each issue reaches `state:human-review` with complete artifact hashes, unresolved risks and preview. No agent applies `published` status. Record role leakage, ambiguity or exception as a contract defect and fix the contract before declaring proof.

- [ ] **Step 7: Verify five-format evidence**

Require five schema-valid briefs, five research packets, five WordPress drafts, five technical/discovery reports and five human-review handoffs. Confirm zero agent publications and no duplicate canonical URLs.

### Task 8: Complete preview QA and rollback audit

**External state:** logged-out preview; no public homepage switch.

- [ ] **Step 1: Functional and link audit**

Verify every visible internal link on the preview returns the intended status without unexpected redirect chains. Test search, mobile menu, audience paths, guide/category links, policy links and WPForms validation. Confirm optional omissions are intentional.

- [ ] **Step 2: Responsive and accessibility audit**

At 320, 375, 768, 1024 and 1440 CSS pixels verify no horizontal overflow, exact source order, visible focus, 44px controls, one H1, valid heading hierarchy and usable keyboard navigation. Test screen reader landmarks, 200% zoom, no JavaScript, reduced motion and print.

- [ ] **Step 3: Performance audit**

Run Lighthouse 13.4+ mobile and desktop against the logged-out preview. Required targets:

```text
mobile performance >= 90
accessibility = 100
CLS < 0.1
LCP < 2.5s
```

Record request count, transfer size and comparison with the 0.1.19 audit.

- [ ] **Step 4: Search/discovery audit**

Verify canonical, meta description, robots, sitemap eligibility, visible structured data agreement, OAI-SearchBot/CDN access policy and Bing/IndexNow configuration without claiming citation guarantees. Remove generated WordPress-logo artwork from final assets.

- [ ] **Step 5: Launch-gate audit**

Report explicit state for authorship, AI disclosure, affiliate disclosure, consent/privacy, newsletter, site icon, metadata, content curation, Search Console/Bing verification and rollback. Consent remains a blocker until separately authorized and verified.

- [ ] **Step 6: Final rollback proof**

Verify theme rollback to 0.1.19 and restoration to 0.2.0, then verify Reading Settings still point to page 541. Confirm page 1130 remains independently previewable after both transitions.

- [ ] **Step 7: Publish evidence report**

Add a concise report to the program epic with test commands, release/tag/SHA, WordPress object map, five-format proof, Lighthouse reports, screenshots, known launch gates and rollback evidence. Do not switch the public homepage.

## Completion evidence

- Immutable 0.2.0 release and verified package exist.
- MCPWP.net runs 0.2.0 while root remains page 541.
- Page 1130 renders the complete audience-first preview.
- Editorial Contract 1.0.0 version/hash match repository and site context.
- Five representative formats reach human review as WordPress drafts with full artifacts.
- No small agent publishes or changes canonical URLs.
- Accessibility, performance, links, no-JavaScript and rollback checks pass.
- Remaining public-launch gates are explicit; no front-page switch occurs.
