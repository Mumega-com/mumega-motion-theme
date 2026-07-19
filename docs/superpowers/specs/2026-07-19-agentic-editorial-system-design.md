# Mumega Motion Agentic Editorial System

**Date:** 2026-07-19  
**Status:** Approved design; written contract pending owner review  
**Repository:** `Mumega-com/mumega-motion-theme`  
**First site profile:** `https://mcpwp.net`  
**Initial editorial contract:** `1.0.0`  
**Target theme release:** Mumega Motion `0.2.0`  
**Target MCPWP profile:** `1.0.0`

## Objective

Create a reusable editorial operating system that allows smaller, bounded agents to research, draft, verify, classify, update, and hand off high-quality WordPress content without improvising the publication's strategy on every task.

The system must make the correct process easier than an unstructured AI draft. It must define:

- what content belongs on the site;
- which template fits each content problem;
- which evidence is required;
- what each agent may and may not do;
- how a draft moves between agents and human reviewers;
- which checks block progression;
- how content remains accurate after publication;
- how the visible WordPress templates reflect that rigor;
- how the contract evolves without silently breaking agents or old content.

MCPWP.net is the first production profile and proving ground. Mumega Motion remains reusable for other AI-first knowledge publications.

## Relationship to existing designs

This specification extends, but does not replace:

- `2026-07-18-mcpwp-editorial-system-design.md`, which defines the WordPress-native publication architecture;
- `2026-07-19-mcpwp-homepage-v2-design.md`, which defines the audience-first homepage and ASTER presentation.

When the documents overlap, this specification controls agent workflow, editorial schemas, format templates, quality gates, and versioning. The earlier specifications continue to control front-end composition, theme boundaries, and the safe homepage rollout.

## Problem statement

Small agents are effective when their task is bounded and their output is mechanically reviewable. They are unreliable when asked to "write a good article" without an explicit audience, canonical intent, evidence packet, format, constraints, or handoff state.

Prompt-only guidance is insufficient because prompts drift. Gutenberg patterns alone are insufficient because they control appearance but not research or claims. The chosen design combines:

1. a machine-readable editorial contract;
2. human-readable agent instructions;
3. structured brief and research artifacts;
4. WordPress-native visible patterns;
5. deterministic validation gates;
6. human publication authority.

## Design principles

1. **One task, one agent responsibility.** An agent does not research, write, verify, and approve the same article.
2. **Draft-first.** Agents create or revise drafts; publication remains an explicit human action.
3. **Evidence before prose.** Material factual claims are sourced or observed before drafting.
4. **One canonical intent.** The system strengthens durable topic pages instead of multiplying near-duplicate URLs.
5. **WordPress is the content source of truth.** Posts, revisions, taxonomies, authors, media, and public status remain native WordPress data.
6. **The repository is the contract source of truth.** Schemas, agent rules, templates, and validators are version-controlled.
7. **MCPWP is an optional operating interface.** It enables agents to work with WordPress but is not required to render the site.
8. **Visible rigor.** Methods, sources, limitations, updates, and disclosures are useful to readers, not hidden machine-only metadata.
9. **Fail closed.** Missing evidence, unclear ownership, unsupported access, or an incompatible contract stops the workflow.
10. **No speculative automation.** New fields and workflow features are proven manually before becoming plugin or database requirements.

## System boundaries

### Mumega Motion owns

- Gutenberg patterns for the six public article formats.
- Semantic template parts for summaries, evidence, methodology, limitations, corrections, disclosures, and related knowledge.
- Responsive, accessible and printable presentation.
- Optional homepage modules described in the Homepage V2 specification.
- Stable CSS class and pattern-slug contracts.
- Theme-level tests for public rendering.

### The editorial contract owns

- Topic, audience, format, evidence and freshness vocabularies.
- Content brief and research packet schemas.
- Agent role instructions and allowed transitions.
- Validation rules and stop conditions.
- Version and compatibility policy.
- GitHub issue templates and labels for editorial work.

### WordPress owns

- Public and draft content.
- Revisions and publication status.
- Categories, tags, media, menus and author accounts.
- Slugs, canonical URLs and normal publishing permissions.

### MCPWP owns

- Authenticated, scoped agent access to supported WordPress operations.
- Actual capability enforcement.
- Site context containing a condensed copy of the active editorial contract.
- Draft creation and updates only when the active tool and role permissions allow them.

Mumega Motion does not grant MCPWP permissions, and the editorial contract does not claim that an unsupported tool exists.

### Human editors own

- Final editorial judgment.
- Public authorship and reviewer identity.
- Publication, canonical changes, redirects and deletions.
- Commercial disclosures and sensitive claims.
- Exceptions to the contract, recorded with a reason.

## Repository structure

Implementation creates this source layout:

```text
editorial/
  manifest.json
  schemas/
    content-brief.schema.json
    research-packet.schema.json
    validation-report.schema.json
  templates/
    explainer.md
    practical-guide.md
    test-report.md
    comparison-review.md
    news-briefing.md
    analysis-opinion.md
  agents/
    scout.md
    brief-creator.md
    researcher.md
    writer.md
    technical-verifier.md
    discovery-reviewer.md
    editor-handoff.md
  rules/
    sources.md
    seo-geo.md
    authorship-disclosure.md
    freshness-corrections.md
    wordpress-handoff.md
  examples/
    valid/
    invalid/
```

The full contract remains in the private repository. A generated, condensed site-context document contains only the instructions needed by authorized MCPWP agents. The build process must detect when the condensed context is out of sync with the contract version.

## Knowledge and classification model

Every proposed article has exactly one value in each required dimension.

### Primary topic

- `understand`: concepts, definitions, ecosystem change and implications;
- `build`: implementation, integration and operational workflows;
- `govern`: permissions, security, oversight, recovery and policy;
- `grow`: content, search, AI visibility, analytics and audience development;
- `test`: original experiments, evaluations and reproducible observations.

MCPWP may display different public category names, but its site profile maps them to these stable contract values.

### Primary audience

- `site-owner`;
- `agency`;
- `builder`;
- `content-team`.

An article may help secondary audiences, but one primary audience controls examples, assumptions, terminology and calls to action.

### Content format

- `explainer`;
- `practical-guide`;
- `test-report`;
- `comparison-review`;
- `news-briefing`;
- `analysis-opinion`.

### Freshness class

- `evergreen`: reviewed at least every twelve months or when a named dependency changes;
- `version-sensitive`: reviewed when a named product, API, model, plugin or platform version changes;
- `news`: interpreted as of a stated publication date and either connected to a durable page or retired from promotion when no longer current.

### Public entities

Native WordPress tags represent controlled public entities such as tools, protocols, models, plugins, organizations and named concepts. Operational workflow labels never become public tags.

### Canonical intent

Every brief records one `canonical_slug`. This is the join key between the GitHub editorial artifact and the WordPress draft during the first release. A new brief cannot proceed if another active issue or published page already owns the same intent without an explicit consolidation decision.

## Content brief contract

The machine-readable brief requires:

```yaml
contract_version: 1.0.0
working_title: string
canonical_slug: string
content_format: explainer | practical-guide | test-report | comparison-review | news-briefing | analysis-opinion
primary_topic: understand | build | govern | grow | test
primary_audience: site-owner | agency | builder | content-team
user_question: string
direct_answer: string
search_intent: informational | procedural | comparative | evaluative | navigational
unique_value_type: original-test | primary-source-synthesis | implementation-procedure | evidence-led-analysis
unique_value: string
public_entities: [string]
canonical_page_supported: string | null
required_evidence: [string]
primary_sources: [url]
test_required: boolean
versions_and_date: string | null
required_internal_links: [string]
human_reviewer_role: string
ai_disclosure: string
freshness_class: evergreen | version-sensitive | news
update_trigger: string
commercial_relationship: none | affiliate | vendor-access | sponsored
```

Schema validation confirms structure, not truth. A valid brief can still be rejected by the editor for weak intent, insufficient value or inappropriate scope.

### Brief admission rules

A brief stops before research when:

- `canonical_slug` conflicts with an active or published canonical intent;
- the proposed article has no unique value beyond summarizing existing secondary sources;
- the question is outside the active site profile's declared scope; for MCPWP this is the WordPress × AI intersection;
- the required evidence cannot legally or practically be obtained;
- a commercial relationship is unknown;
- the responsible human reviewer role is absent;
- the requested workflow would require an unsupported or unauthorized MCPWP capability.

## Research packet contract

The researcher converts an accepted brief into a claim-level packet before prose is drafted.

Each material claim records:

- claim text or bounded proposition;
- claim type: observed, sourced fact, calculation, interpretation or opinion;
- source URL or test evidence reference;
- publisher or owner;
- publication and access dates;
- relevant version or environment;
- confidence and unresolved uncertainty;
- whether the claim may become stale;
- the exact article section that will use it.

The packet also records:

- counterevidence and credible disagreement;
- definitions that require consistent wording;
- terminology and entity-name normalization;
- copyright-sensitive quotations;
- screenshots, logs or test artifacts;
- facts that must not be claimed.

Direct quotations from a single non-lyrical source remain short and are used only when the original wording is necessary. Agents primarily paraphrase and cite.

## Public article templates

All formats include a concise summary, visible authorship, AI-assistance disclosure when applicable, relevant sources, limitations, related knowledge and an update/correction area. Each format adds its own required structure.

### Explainer

Use for a durable concept or ecosystem question.

Required sections:

1. Direct answer.
2. Why it matters to the primary audience.
3. How it works.
4. Concrete WordPress example.
5. Boundaries and common misconceptions.
6. What to do next.
7. Sources and updates.

### Practical guide

Use when the reader wants a repeatable outcome.

Required sections:

1. Outcome and suitability.
2. Prerequisites and permissions.
3. Tested versions and date.
4. Ordered procedure.
5. Verification steps.
6. Failure modes.
7. Rollback or recovery.
8. Security and data considerations.
9. Sources and updates.

### Test report

Use for original evidence.

Required sections:

1. Question and hypothesis.
2. Environment and versions.
3. Procedure.
4. Observations and artifacts.
5. Results.
6. Failures and anomalies.
7. Limitations and reproducibility.
8. Conclusion bounded to the evidence.
9. Sources, corrections and retest trigger.

### Comparison or review

Use for an actual reader decision, not affiliate inventory.

Required sections:

1. Decision and audience.
2. Inclusion and exclusion criteria.
3. Tested versions, access and commercial relationships.
4. Comparable evidence table.
5. Findings by decision criterion.
6. Best fit and poor fit for each option.
7. Limitations.
8. Independent conclusion.
9. Affiliate disclosure when applicable.
10. Sources and update trigger.

Official documentation, pricing, terms and changelogs are required for each compared product. A product may not receive a tested label without direct testing evidence.

### News briefing

Use for a time-sensitive development that needs interpretation.

Required sections:

1. What changed.
2. Primary announcement or artifact.
3. Who is affected.
4. Why it matters now.
5. What readers should do.
6. What remains unknown.
7. Connection to a durable topic page.
8. Sources and dated update note.

### Analysis or opinion

Use for a clearly labelled interpretation.

Required sections:

1. Thesis.
2. Evidence.
3. Strongest counterargument.
4. Analysis separating facts from inference.
5. Implications for the primary audience.
6. Conditions that would change the conclusion.
7. Sources and author disclosure.

## Agent roles and permissions

### Scout

Inputs: published corpus, active briefs, primary topic map and current platform changes.  
Output: gap proposal or update proposal.  
May: identify overlap, stale content and emerging primary sources.  
May not: create or update a WordPress post.

### Brief creator

Inputs: accepted gap proposal.  
Output: schema-valid content brief.  
May: define intent, audience, format and evidence requirements.  
May not: mark its own brief accepted.

### Researcher

Inputs: accepted brief.  
Output: research packet with claim-level evidence.  
May: browse current primary sources and record uncertainty.  
May not: draft the publishable article or silently broaden scope.

### Writer

Inputs: accepted brief, accepted research packet and selected template.  
Output: WordPress draft and validation artifact.  
May: synthesize, organize and explain supported material.  
May not: introduce unsupported material claims, publish, change canonical URLs or create redirects.

### Technical verifier

Inputs: draft, brief, research packet and test artifacts.  
Output: pass/fail report with corrections.  
May: reproduce supported procedures, inspect permissions, check versions and validate rollback claims.  
May not: rewrite conclusions to hide failed verification.

### Discovery reviewer

Inputs: technically verified draft and corpus map.  
Output: SEO/GEO and knowledge-structure report.  
May: flag unclear intent, duplicate coverage, poor headings, missing internal links, crawl barriers, mismatched metadata and inconsistent entities.  
May not: add keyword variants or unsupported FAQ content solely for ranking.

### Editor handoff

Inputs: all artifacts and a draft that passed machine gates.  
Output: concise human decision packet.  
May: summarize remaining risks and required choices.  
May not: publish or impersonate the human reviewer.

### Human editor

Inputs: complete handoff.  
Output: approve, return, reject, publish or schedule.  
Only the human editor may approve exceptions, public authorship, publication, redirects, deletions and commercial conclusions.

## Workflow state machine

The canonical workflow is:

```text
idea
  -> brief_ready
  -> brief_accepted
  -> research_ready
  -> research_accepted
  -> drafting
  -> technical_verification
  -> discovery_review
  -> human_review
  -> approved
  -> published
  -> update_due | corrected | retired
```

GitHub issue labels represent editorial workflow state in the first release. WordPress uses only its normal draft, pending, scheduled, published and private statuses. The system does not add custom WordPress statuses in version 1.

Every transition records:

- actor or agent role;
- contract version;
- timestamp;
- input artifact versions;
- validation result;
- unresolved risks;
- next allowed role.

An agent may perform only the transition assigned to its role. Failed validation returns the artifact to the role that owns the defect; it does not advance with a warning.

## WordPress handoff

The first release uses `canonical_slug` as the deterministic link between a GitHub editorial issue and its WordPress draft. It does not require private custom post metadata or an editorial-workflow plugin.

Before writing, the writer must:

1. confirm the active contract version from MCPWP site context;
2. read the accepted brief and research packet;
3. inspect the current canonical URL and related posts;
4. confirm that the required WordPress operations are actually exposed and authorized;
5. create or update a draft only;
6. preserve WordPress revisions;
7. return the draft ID, preview URL, slug and validation report.

When MCPWP lacks a required capability, the writer stops and reports the missing operation. The system does not infer tool names or use a broader operation as a substitute.

## Gutenberg and visible components

The six templates become registered core-block Gutenberg patterns. Patterns provide semantic starting structures and editable instructional placeholders; they do not lock content or require JavaScript.

Shared visible components include:

- article summary;
- key takeaways when useful;
- tested environment and versions;
- methodology;
- evidence/source list;
- limitations;
- failure and rollback guidance;
- commercial and affiliate disclosure;
- AI-assistance and human-review disclosure;
- correction and update history;
- related topic and public entity links.

Templates render complete, readable HTML without Motion. Motion may progressively reveal or connect nonessential visual elements while respecting reduced-motion preferences.

## SEO and generative-discovery contract

The discovery rules follow current primary guidance rather than a separate collection of GEO tricks.

### Required

- One page owns one primary intent.
- Important content is server-rendered and available as text.
- Headings describe the actual information hierarchy.
- Internal links use descriptive anchors and connect current stories to durable topic pages.
- Metadata and structured data match visible content.
- Public pages are crawlable through WordPress, robots rules, hosting and CDN layers.
- Canonicals, redirects and sitemap entries identify one preferred URL.
- Claims use primary sources, original tests or clearly labelled inference.
- Dates, versions, entities and commercial relationships are consistent across text, metadata and media.
- Important images have useful alternative text; decorative imagery does not duplicate adjacent content.
- Updated and removed URLs are reflected promptly through sitemaps and, when configured, IndexNow.
- Search Console, Bing Webmaster Tools and available AI-referral/citation data inform audits after publication.

### Prohibited

- Publishing separate pages for minor keyword or query-fan-out variations.
- Mass-generating pages without original value.
- Adding unsupported FAQ questions for search coverage.
- Hiding machine-targeted summaries or keywords from readers.
- Claiming `llms.txt`, Markdown mirrors or special AI markup guarantee citation.
- Fabricating expertise, tests, quotations, statistics, author identities or update dates.
- Treating crawler access as proof of indexing, ranking or citation.

The contract recognizes that generative visibility is stochastic. It measures crawlability, citations, referred visits and content reuse where platforms expose data; it never promises a ranking or citation position.

## Authorship, ASTER and disclosure

ASTER is MCPWP's single public synthetic editorial guide. ASTER may introduce briefings, explain research context and provide a consistent visual/voice layer. ASTER is not a human author, technical verifier or publication authority.

Internal agents are operational roles and do not become public bylines. Public pages identify:

- the intentional public author or editorial desk;
- the human reviewer when review is material;
- how AI assisted research, drafting or media creation;
- the responsible methodology and correction path;
- commercial relationships.

Service-account names and API usernames are not polished public author identities.

## Validation gates

### Schema gate

- All required fields exist and match controlled values.
- URLs and slugs are syntactically valid.
- Contract versions are compatible.

### Scope and duplication gate

- One primary audience and intent are explicit.
- The canonical slug is unique or has an approved consolidation plan.
- The article supplies one allowed unique-value type.

### Evidence gate

- Material factual claims map to a source or observation.
- Primary sources are current enough for the claim.
- Test claims include environment, versions, date and artifacts.
- Contradictory evidence is recorded rather than omitted.

### Template gate

- Required sections for the chosen format exist.
- The direct answer, limitations, disclosures and update mechanism are visible.
- Facts, interpretations and opinions are distinguishable.

### WordPress gate

- Draft status is preserved.
- Heading order is valid and exactly one public H1 is owned by the template.
- Links, images and block markup are valid.
- The agent changed only the authorized post and fields.

### Discovery gate

- Title, excerpt, visible copy and schema describe the same page.
- Internal links support topic continuity without stuffing.
- No duplicate intent or thin query-variant page is introduced.
- Crawl and canonical prerequisites are testable.

### Human gate

- Reviewer identity and unresolved risks are visible.
- Commercial and sensitive claims receive explicit approval.
- Only a human may approve publication or an exception.

## Failure behavior

- Missing or incompatible contract: stop before content mutation.
- Invalid brief: return field-level errors to the brief creator.
- Weak or inaccessible sources: return to research; do not fill gaps with model memory.
- Conflicting sources: preserve disagreement and request editorial judgment.
- Failed reproduction: record the failure and correct or narrow the article.
- Duplicate intent: propose consolidation, update or redirect; do not create another draft.
- Unauthorized WordPress operation: stop and report the actual missing capability.
- Broken Gutenberg markup: keep the last valid revision and return to the writer.
- Failed SEO/GEO check: correct the content or metadata; do not create doorway variants.
- Missing human reviewer: remain in human review and never publish.

## Versioning model

Four independent release trains prevent accidental coupling.

| Component | Initial/next version | Scope |
|---|---:|---|
| Mumega Motion | `0.1.19` -> `0.2.0` | Theme rendering, patterns, styles and public template behavior |
| Editorial Contract | `1.0.0` | Schemas, templates, roles, workflow and validation rules |
| MCPWP Editorial Profile | `1.0.0` | MCPWP-specific taxonomy, menus, ASTER, copy and configured pages |
| MCPWP plugin | Independent `3.x` train | Authenticated WordPress/MCP operations and product distribution |

The theme must render without MCPWP. The compatibility manifest records the MCPWP version tested with agent operations, not a runtime dependency.

### Semantic-version rules

Theme:

- patch: compatible bug, style, accessibility or packaging correction;
- minor while below 1.0: substantial capability such as the Agentic Editorial System or a later knowledge layer;
- 1.0: stable reusable contracts, proven migration and supported rollback.

Editorial contract:

- patch: clarification or validator defect that does not materially change a compliant artifact;
- minor: compatible optional field, template or rule;
- major: changed required fields, controlled values, workflow transitions or role responsibilities.

Site profile:

- patch: copy, link, asset or mapping correction;
- minor: compatible new topic, pathway or optional module;
- major: information-architecture change requiring migration.

### Compatibility manifest

`editorial/manifest.json` uses this initial contract:

```json
{
  "editorial_contract": "1.0.0",
  "mcpwp_profile": "1.0.0",
  "requires_theme": ">=0.2.0 <0.3.0",
  "mcpwp_plugin_required": false,
  "tested_mcpwp_plugin": "3.6.1"
}
```

Existing published articles are not made invalid merely because the contract gains a compatible minor version. A material update migrates its brief and validation artifact to the latest compatible contract. A new contract major version requires an explicit migration plan before agents use it.

Theme tags continue the existing private direct-update convention. The first published implementation tag is `edge-v0.2.0`; immutable tags and retained packages support rollback. Development builds use branch commits or manually installed preview ZIPs rather than unverified prerelease semantics in the updater.

## Delivery roadmap

### Phase 1: contract foundation

- Add schemas, format templates, agent instructions, rules, examples and manifest.
- Add GitHub issue templates and workflow labels.
- Build deterministic schema and cross-reference validation.
- Generate condensed MCPWP site context from the contract.

Exit: valid and invalid fixture packs pass their expected checks; smaller agents can identify their inputs, output and stop conditions without reading implementation code.

### Phase 2: Mumega Motion 0.2.0

- Add and test the six Gutenberg patterns.
- Add shared visible editorial components.
- Implement Homepage V2, ASTER/editorial-guide convention, Audiences menu and optional modules.
- Preserve server rendering, no-JavaScript behavior, Elementor isolation and rollback.

Exit: theme and package tests pass; the existing preview page renders the new system without changing the public homepage.

### Phase 3: agent pack 1.0.0

- Publish bounded instruction files for each role.
- Connect issue state to allowed agent transitions.
- Install the condensed contract into MCPWP site context.
- Verify draft-only WordPress behavior against actually exposed MCPWP capabilities.

Exit: no small agent can progress without the previous accepted artifact, and no small agent can publish.

### Phase 4: MCPWP profile 1.0.0

- Configure topic and audience mappings.
- Add ASTER profile, methodology, AI disclosure, knowledge-map promotion, newsletter and policy links.
- Map existing content to canonical intents without changing URLs unnecessarily.

Exit: all homepage modules use real, intentional WordPress content or omit themselves cleanly.

### Phase 5: vertical-slice validation

- Run one explainer, practical guide, test report, comparison/review and news briefing through the complete workflow.
- Record every exception, ambiguity and failed handoff.
- Fix the contract before adding automation.

Exit: each format produces a reviewable WordPress draft and complete evidence trail without role leakage.

### Phase 6: preview and launch gates

- Install the private theme release on MCPWP.net.
- Configure the non-front-page Editorial Home preview.
- Complete responsive, accessibility, performance, link, authorship, consent, disclosure, newsletter and rollback audits.
- Obtain explicit owner approval before changing Reading Settings.

Exit: the owner can switch or roll back the homepage without code deployment.

### Phase 7: later automation

- Content-gap and freshness agents.
- Scheduled citation and search monitoring.
- Mupot orchestration.
- Dynamic graph, backlinks and edge rendering.

These are independent follow-up designs and do not block contract 1.0.0.

## Testing strategy

### Contract tests

- Valid fixtures pass each JSON Schema.
- Invalid fixtures fail with exact field errors.
- Every controlled value has one documented meaning.
- Every agent role has one input, output, allowed transition and stop condition.
- Every format maps to a Gutenberg pattern.
- Site context generation includes and reports the contract version.
- Compatibility rules reject unsupported major versions.

### Theme tests

- Patterns contain required semantic sections and no page-level duplicate H1.
- Public components escape output and preserve block rendering.
- Optional conventions require public, unprotected WordPress content.
- Homepage queries preserve duplicate exclusion.
- No `front-page.php` enters the package.
- No essential content requires JavaScript.
- Existing update, rollback and package tests remain green.

### Workflow simulations

- Duplicate intent is stopped.
- Missing primary evidence is stopped.
- An unsupported tool request is stopped.
- Failed technical reproduction returns to the correct role.
- Discovery review cannot change publication status.
- A human-rejected draft cannot advance.
- A contract-version mismatch stops before WordPress mutation.
- A complete representative artifact reaches human review with an intact audit trail.

### Production preview

- Test 320, 375, 768, 1024 and 1440 CSS-pixel widths.
- Test keyboard, screen reader, 200% zoom, print, no JavaScript and reduced motion.
- Validate canonical, sitemap, robots, structured data and internal links.
- Measure Lighthouse mobile and desktop performance.
- Verify actual MCPWP tool discovery and permissions before agent use.
- Verify immediate rollback to Mumega Motion 0.1.19 and the legacy Elementor homepage.

## Security and privacy

- The contract grants no permissions; WordPress and MCPWP capabilities remain authoritative.
- Agents use the least-privileged role and API key compatible with their bounded task.
- Secrets never enter briefs, research packets, drafts, issues, logs or site context.
- Personally identifying, legal, medical, financial or confidential claims require a human reviewer appropriate to the risk.
- Consent-plugin installation, analytics changes and Privacy Policy rewriting require separate action-time authorization and verified behavior.
- Agent output is untrusted input until escaped, validated and reviewed.

## Explicitly out of scope

- Automatic publication.
- A second mascot or public personality for every agent role.
- A new editorial database or custom WordPress post status in version 1.
- Automatic redirects, deletions or canonical changes.
- Automatic legal, security or commercial approval.
- A graph database, vector store or semantic ranking engine.
- Cloudflare edge rendering.
- Product pricing, Freemius packaging or MCPWP plugin release changes.
- Replacing WordPress, Gutenberg or the existing private theme channel.
- Claiming SEO rank or AI citation guarantees.

## Acceptance criteria

The Agentic Editorial System design is achieved when:

- a smaller agent can determine its exact role, input, output and stop conditions;
- every article begins with a schema-valid brief and evidence packet;
- duplicate intent, missing evidence, incompatible versions and unsupported capabilities stop before publication;
- six reusable public formats exist as both agent templates and Gutenberg patterns;
- WordPress drafts remain native, revisioned and unpublished until human approval;
- the site context identifies the active editorial contract version;
- visible articles expose appropriate methods, sources, limitations, disclosure and updates;
- SEO and generative discovery use one people-first, crawlable, evidence-led contract;
- Mumega Motion, the editorial contract, the MCPWP site profile and MCPWP plugin release independently;
- five representative vertical slices reach human review without role leakage;
- the public homepage remains unchanged until all launch gates and explicit owner approval are complete.

## Primary guidance informing the contract

- [Google: optimizing for generative AI features](https://developers.google.com/search/docs/fundamentals/ai-optimization-guide)
- [Google: AI features and your website](https://developers.google.com/search/docs/appearance/ai-features)
- [Google: generative AI content guidance](https://developers.google.com/search/docs/fundamentals/using-gen-ai-content)
- [Bing: AI Performance in Webmaster Tools](https://blogs.bing.com/webmaster/February-2026/Introducing-AI-Performance-in-Bing-Webmaster-Tools-Public-Preview)
- [Bing: sitemaps in AI-powered search](https://blogs.bing.com/webmaster/July-2025/Keeping-Content-Discoverable-with-Sitemaps-in-AI-Powered-Search)
- [OpenAI: publisher and developer guidance](https://help.openai.com/en/articles/12627856-publishers-and-developers-faq)
- [WordPress: MCP Adapter](https://developer.wordpress.org/news/2026/02/from-abilities-to-ai-agents-introducing-the-wordpress-mcp-adapter/)
- [GEO: Generative Engine Optimization](https://arxiv.org/abs/2311.09735)
- [Critical survey of GEO research, 2023–2026](https://arxiv.org/abs/2607.14035)
