# CAP Alerts Real-Time Aggregation — Architecture Plan (2026-09-14, grilled)

## Implementation status (last updated 2026-09-16)

**Module boundary update:** the "CAP Feed Source" config entity and any other Drupal-side
aggregator-connector logic now live in a **separate, new module**, `cap_alerts_aggregator_connector`
(`~/repositories/parks_australia/cap_alerts_aggregator_connector`), not `cap_alerts_parks_australia`.
`cap_alerts_parks_australia` retains only the Parks-Australia-specific Alert content additions (park
attribution field, Location auto-relate). Everywhere below that previously said "lives in
`cap_alerts_parks_australia`" for feed-source/aggregator-connector concerns now means
`cap_alerts_aggregator_connector` instead.

| # | Item | Status | Notes |
|---|---|---|---|
| 1.A | "CAP Feed Source" config entity | ✅ Done | Lives in `cap_alerts_aggregator_connector`. Entity/form/list/routing/permissions, Key-module credential, repeatable `park_overrides` sub-form, and a locked-down `/api/cap-alerts/feed-sources` JSON endpoint (shared-secret header, validated against a Key entity) — all implemented and verified end-to-end in DDEV, including real HTTP requests with correct/wrong/missing secrets |
| 1.B | Alert park attribution (`field_site`, restricted to non-`_app`) | ✅ Done | In `cap_alerts_parks_australia`. Implemented + verified in DDEV (custom `EntityReferenceSelection` plugin) |
| 1.C | Location auto-relate presave logic (geometry match) | ✅ Done | In `cap_alerts_parks_australia`. `CapAlertLocationRelator` service + `hook_node_presave`, verified end-to-end in DDEV |
| 1.D | Park boundary GeoJSON data | ⬜ Outstanding | Waiting on user-provided per-park files; blocks aggregator geo-filtering, not the Drupal-side work |
| 2 | Aggregator service (new standalone repo, AWS) | 🟨 Partial | Node.js 22 project now scaffolded at `~/repositories/parks_australia/cap_alerts_aggregator` with AWS SAM, EventBridge schedule, DynamoDB state table, S3 state/output resources, SSM secret parameters, Drupal Feed Source client, RSS/Atom canonical-link ingestion, CAP XML normalization, source-health persistence, lifecycle reduction (expiry, Cancel references, Update supersession), Turf-based CAP polygon/circle geometry normalization, multi-park boundary assignment, and S3 publication to `alerts/<park>.json` with `Cache-Control: max-age=60`. Remaining: user-provided boundary files, Drupal park/location attribution, native GeoJSON/EDXL-DE adapters, CloudFront, monitoring, and deployment configuration |
| 3 | DataQuoll integration | ⬜ Outstanding | Depends on aggregator service (2) |
| 4 | Output schema | ⬜ Outstanding | Depends on aggregator service (2) |
| 5 | Gatsby frontend — live map, Place pages, Access Reports | ⬜ Outstanding | Depends on aggregator service (2) and output schema (4). Runtime Aggregator retrieval must cover Leaflet maps, Drupal Place pages, and Access Report maps/tables. |
| 6 | Gatsby build-time GraphQL — Alert↔Place relationship | 🟨 Partial | Resolver re-enabled in `gatsby-node.js`; `relatedCapAlerts { ...CapAlertData }` uncommented in `locationData.tsx` and `place.tsx`, and Drupal-authored Alert nodes now render in Gatsby builds. The Drupal relationship is **association metadata only**: Alert→Location establishes which Place/Location a Drupal-authored Alert relates to. It is not the source of current Alert content and must not be used to determine whether an Alert is active. When a Place page or Access Report loads, Gatsby must use the related Location identifier(s) to retrieve current, filtered Alert data from the CAP Aggregator. **Current limitation:** the existing build-time GraphQL results undergo no Alert lifecycle filtering. Expired and cancelled Alerts, including new and updated Alerts in the same incident chain, are all exposed and rendered. Consequently, one incident with five CAP Alert messages currently appears as five separate frontend Alerts instead of one current Alert. |

**Connector testing gap:** the API response shape has been exercised, but the following
`cap_alerts_aggregator_connector` fields have not yet been functionally tested with non-empty or
non-default values: `categoryAllowlist`, `msgtypeDenylist`, `agencyAllowlist`, `agencyDenylist`,
`requireGeometry`, and `parkOverrides`. Add coverage for saving each value through the Drupal form,
serializing it through `/api/cap-alerts/feed-sources`, and consuming it in the Aggregator filters.

Known-good, verified-in-DDEV building blocks so far: `field_site` restricted to website
`gatsby_endpoint`s only (no `_app`); `field_location_reference` re-enabled and auto-populated by
geometry match, scoped to matching `field_site`, additive-only; `cap_feed_source` config entity CRUD
working correctly from its new home in `cap_alerts_aggregator_connector`. See
`/memories/repo/cap-alerts-parks-australia-drupal-notes.md` for implementation gotchas encountered
(colon-free plugin IDs, config entity query operator limits, geophp/itamair class collision).

## Problem

The Gatsby interactive-map prototype has two shortcomings:

1. CAP alert feed data is only pulled in at Gatsby **build time**, so the map goes stale until the
   next build.
2. There is no filtering of incoming alerts (expired/cancelled alerts, geographically irrelevant
   alerts, etc.).

## Core idea: separate "authoring" from "serving"

Stop treating CAP alerts as buildable Gatsby content **for the live map**. Introduce a stateless
**aggregation layer** that both Drupal-authored alerts and external feeds pass through, which the
browser polls directly (client-side fetch, not Gatsby's GraphQL data layer) for the Leaflet map.

Separately, Drupal-authored alerts also continue to flow through Gatsby's normal build-time GraphQL
layer for a second, independent purpose: relating alerts to Place pages (see section 6). These two
paths serve different needs and are not in tension — the map needs real-time freshness; the Place-page
relationship is fine at ordinary build-time freshness, same as any other Drupal content edit on this
site.

Drupal's existing `cap_alerts` module already exposes `/api/alerts/rss.xml` (RSS index) +
`/api/alerts/{identifier}` (canonical CAP XML per alert) — exactly the RSS-links-to-canonical-XML
shape used by external CAP sources (CAP v1.2 §3, req 7). So Drupal itself is registered as **just
another feed source** in the aggregator, rather than building a separate ingestion path for it.
One filtering/cancellation/geo pipeline handles everything for the map.

```
┌───────────────────────┐   ┌──────────────────────────┐
│ Drupal cap_alert_     │   │ External feed sources     │
│ message nodes         │   │ (RSS/Atom/CAP-XML/        │
│ → /api/alerts/rss.xml │   │  GeoJSON/EDXL-DE)         │
│ (park pre-assigned by │   │ (always park-agnostic —  │
│  editor via field_site)│   │  geometry decides park)  │
└───────────┬───────────┘   └────────────┬─────────────┘
            │               (live map data path)
            └───────────────┬────────────┘
                             ▼
                 ┌─────────────────────────┐
                 │  CAP Aggregator (new,    │
                 │  standalone repo, AWS)   │
                 │  - polls every ~60s      │
                 │  - resolves RSS/Atom→XML │
                 │  - unwraps EDXL-DE       │
                 │  - cancellation chain    │
                 │  - expiry filtering      │
                 │  - field + agency filters│
                 │  - geo filter vs park    │
                 │    boundary GeoJSON      │
                 │  - normalises to GeoJSON │
                 └────────────┬─────────────┘
                              ▼
                 One static GeoJSON file per park on
                 S3 + CloudFront, 60s cache-control
                              ▼
                 Gatsby Leaflet map: client-side
                 fetch on mount + setInterval(60s),
                 togglable layers per source/agency

(separately, build-time path — see section 6)
Drupal cap_alert_message node saved
        → field_location_reference populated (geometry match, in Drupal)
        → Gatsby Endpoints module triggers a rebuild of that park's site
        → Gatsby GraphQL: Place → field_related_location → Location →
          relatedCapAlerts (via createResolvers) → Alert
```

## 1. Drupal: `cap_alerts_parks_australia` (Alert content) + `cap_alerts_aggregator_connector` (feed config)

Parks-Australia-specific Alert content additions (park attribution field, Location auto-relate) live
in `cap_alerts_parks_australia`. The "CAP Feed Source" config entity and any other Drupal-side
aggregator-connector logic live in a **separate module**, `cap_alerts_aggregator_connector` — split
out so the feed-source/aggregator-facing config isn't coupled to Parks-Australia-specific content
logic. Neither lives in `cap_alerts` itself, which stays reusable/upstreamable. Note: the actual
alert content type's machine name is `cap_alert_message` (from `cap_alerts`'s existing
`node.type.cap_alert_message`), referred to as "the Alert node type" below.

### A. New config entity: "CAP Feed Source" (in `cap_alerts_aggregator_connector`) — ✅ Done

Editable at `/admin/config/services/cap-alerts/feeds`. External sources are always **park-agnostic**
— there is no `park` field. Park attribution for external alerts is decided per-feature by geometry
matching in the aggregator, never configured per source.

| Field | Purpose |
|---|---|
| `label`, `feed_url` | identity + source |
| `feed_format` | `rss` \| `atom` \| `cap-xml` \| `dataquoll-geojson` \| `edxl-de` — explicit, no format auto-detection (a misidentified format silently drops/mangles alerts). There is no generic GeoJSON adapter; `dataquoll-geojson` is reserved for the documented DataQuoll incident schema. |
| `cap_xml_root_element` | only shown/required when `feed_format` is `cap-xml` (via `#states`). CAP v1.2 defines the `<alert>` element itself but no standard container for multiple alerts in one feed/document, so the wrapping root element name varies per feed and must be configured explicitly |
| `credential` | optional, stored via the **Key module** (encrypted, excluded from config export) — for feeds requiring a bearer token/API key |
| `min_severity` / `min_certainty` / `min_urgency` | CAP enum thresholds (base filters) |
| `category_allowlist`, `msgtype_denylist` | field-based filters |
| `agency_allowlist` / `agency_denylist` | filter by originating agency (e.g. exclude ambulance/medical-only incidents) — free-text/tags field |
| `require_geometry` | must have polygon/circle to be shown; **only applies to CAP-XML-style sources** — GeoJSON point-only sources (e.g. DataQuoll's default `incident` featureType) are filtered by point-in-park-boundary instead |
| `park_overrides` | repeatable sub-structure: `{ gatsby_endpoint, override severity/certainty/urgency/category/agency }`, letting individual parks tune thresholds against a shared multi-park source |
| `status` | enabled/disabled |
| `park_overrides` | implemented as an AJAX-repeatable sub-form (add row / rebuild); each row: `{gatsby_endpoint, min_severity, min_certainty, min_urgency, category_allowlist, agency_allowlist, agency_denylist}` |

Exposed via a **locked-down custom endpoint** at `GET /api/cap-alerts/feed-sources` (shared-secret
header `X-Cap-Aggregator-Secret`, secret stored as a Key entity, configured at
`/admin/config/services/cap-alerts/aggregator-connector`) — not the open JSON:API, since it reveals
filter thresholds, feed URLs, and resolved credentials. Returns each enabled source's config plus its
credential *resolved to the real secret value* (via `key.repository`), since the aggregator is the
one that actually calls the external feed and needs the real token.

**Implementation note (deviation from the original `_custom_access` plan):** the shared-secret check
is done **inside the controller**, not as a route-level `_custom_access` requirement. Drupal's
page-cache module does not reliably respect cache metadata coming from custom access checkers on the
resulting access-denied response — this caused a real bug where the first denied (or allowed)
response got cached and served to all subsequent requests regardless of the header. Doing the check
in the controller and returning an explicit `Cache-Control: no-store` `JsonResponse` in both the
allowed and denied cases avoids this entirely (verified in DDEV via alternating correct/wrong/missing
secret requests). This is now recorded as a general gotcha in repo memory.

### B. Alert node: park attribution (the one case with an explicit park) — ✅ Done

New entity-reference field targeting `gatsby_endpoint` — reusing the existing sitewide `field_site`
pattern (used on `location`, `page`, `place`, etc.) rather than inventing a parallel reference.
Restricted to non-`_app` (website) endpoints only, since CAP Alerts only apply to the map/website
context. A shortcode-normalization rule (strip `_app` suffix when matching) means a single website
selection also covers future mobile-app consumption — no need to double-mark both variants.

### C. Auto-relate Location nodes (req 4) — ✅ Done

On `hook_ENTITY_TYPE_presave` for Alert nodes, when polygon/circle fields change, run a
point-in-polygon/circle test scoped to **only Location nodes sharing the same `field_site` value** as
the alert (not every Location site-wide) and populate the existing `field_location_reference`
entity-reference field on the Alert. A small PHP geometry library (`mjaschen/phpgeo`,
`league/geotools`) or simple ray-casting is sufficient. This single piece of logic feeds **both** the
live-map real-time path (indirectly, via Drupal being polled as a feed source) and the build-time
Place-page relationship (section 6) — no duplicate geometry logic is needed anywhere else, including
the CAP Aggregator, which never needs to know about Location/Place content relationships.

### D. Park boundary data — ⬜ Outstanding (waiting on user-provided files)

Static, user-provided per-park GeoJSON boundary files. These are **versioned in the aggregator's own
repo**, not in Drupal or `cap_alerts_parks_australia` — keeps the standalone aggregator free of a
Drupal-fetch dependency on every run. (Drupal's own point-in-polygon test in section C uses the same
source boundary data, provided separately to Drupal as needed for that comparison.)

## 2. The Aggregator service (new, standalone project) — 🟨 Partial

A standalone Node.js 22 project now exists at
`~/repositories/parks_australia/cap_alerts_aggregator`, separate from `parksaustralia-cms` and
`frontend` (avoids coupling alert freshness to Drupal cron/uptime). It runs on AWS via SAM:

- **Compute:** scheduled Lambda(s), triggered by EventBridge every ~60s. Built with **AWS SAM**
  (simplest fit for this size stack; supports `sam local invoke` for testing filter/geometry logic
  locally).
- **State:** DynamoDB, one item per alert `identifier`, with a TTL attribute for auto-expiry — needed
  because Lambda invocations are stateless between runs and cancellation-chain/seen-set tracking
  must persist across cycles. Avoids read-modify-write races that a single S3 JSON blob would have.
- **Output:** one static GeoJSON file per park, written to S3, served through CloudFront with
  `Cache-Control: max-age=60`.
- **Secrets:** SSM Parameter Store (SecureString) for the shared secret used to call Drupal's locked
  Feed Source endpoint and for external feed credentials, mirrored by the Key module on the Drupal
  side.
- **Monitoring:** CloudWatch alarm on Lambda errors and on a feed's consecutive-failure count,
  notifying via SNS → email — a silently broken feed shouldn't degrade the map for weeks unnoticed.
- **Source healthcheck logging:** each poll cycle's per-source health result (status + error/response
  detail) is appended to a durable log (e.g. CloudWatch Logs, one structured JSON line per entry) with
  a UTC timestamp, distinct from the DynamoDB last-known-good state used for publication. To keep the
  log a signal of *change* rather than a duplicate of every poll, a log entry is only written when:
  1. a source is polled for the first time (its first-ever health result), or
  2. a source's status/error changes from its immediately preceding logged result (including changes
     in the error message/response detail while status stays e.g. `degraded`).
  Unchanged consecutive polls (same status *and* same error/response detail) are not re-logged. For
  example, polling every 30s with results `ok, ok, ok, degraded/errA, degraded/errB, degraded/errB,
  ok, degraded/errC, degraded/errD` logs only `ok (initial)`, `degraded/errA`, `degraded/errB`,
  `ok`, `degraded/errC`, `degraded/errD` — the repeated `ok` and repeated `degraded/errB` polls are
  skipped. This requires persisting each source's last-logged status/error (alongside, not instead
  of, the existing last-known-good alert cache) so it survives across stateless Lambda invocations.
- **Testing:** dedicated unit test suite for filter, cancellation-chain and geometry-matching logic —
  this is safety-relevant (a filtering bug could hide or wrongly surface a real hazard alert), not
  just a display nicety.

### Implemented first vertical slice

- `src/index.js` uses native Node.js `fetch` and `fast-xml-parser` to fetch the locked Drupal Feed
  Source endpoint with the `X-Cap-Aggregator-Secret` header. No separate Drupal API key is used;
  the connector's Key-backed shared secret is the access control.
- Local testing is discoverable and does not require SSM, DynamoDB, or deployed AWS resources:
  copy `~/repositories/parks_australia/cap_alerts_aggregator/.env.example` to `.env`, set
  `DRUPAL_FEED_SOURCES_URL` and `DRUPAL_AGGREGATOR_SECRET`, then run
  `npm run local:poll`. The runner writes normalized inspection output to
  `.local-output/aggregator.json`.
- The local runner loads one boundary file per park shortcode from `BOUNDARIES_DIR`. The supplied
  assets use the `assets/boundary_data/<shortcode>-boundary_10m.geojson` convention, with
  `uktnp-boundary.geojson` as the no-resolution-suffix example; the `-boundary` and optional
  resolution suffix are normalized automatically. It writes per-park FeatureCollections to
  `LOCAL_OUTPUT_DIR`, for example
  `.local-output/parks/anbg.json`. Alerts without usable polygon/circle geometry, or whose geometry
  does not intersect a park boundary, are culled from that park's output. This is the local
  precursor to S3/CloudFront publication.
- RSS and Atom sources resolve canonical alert links; direct CAP-XML sources parse configured
  `cap_xml_root_element` wrappers or a single `<alert>` document.
- CAP alerts are normalized into GeoJSON Feature-shaped objects with CAP metadata and a null geometry
  placeholder for the later geometry stage.
- Lifecycle reduction now removes expired Alerts, suppresses Alerts referenced by CAP `Cancel`
  messages, and keeps `Update` messages while removing their superseded references. RSS/Atom
  canonical-document results are flattened before reduction.
- CAP polygon and circle values are converted to GeoJSON using Turf, and features can be assigned
  to every intersecting park boundary, allowing one alert to appear in multiple park outputs.
- The Lambda publishes each assigned park FeatureCollection to S3 at `alerts/<park>.json` with
  `Content-Type: application/geo+json` and `Cache-Control: max-age=60`. Publication is a no-op until
  boundary GeoJSON files are packaged under the configured `BOUNDARIES_DIR`.
- Per-source health and alert counts are persisted to DynamoDB.
- `npm test`, `npm run lint`, and `sam validate --lint` pass locally; `sam build` also succeeds with
  dependencies bundled from the project root.

This is intentionally not a complete publishing pipeline yet: Drupal-authored park attribution is
not available in the current feed-source response, and no boundary matching, expiry/cancellation
reduction, per-park S3 publication, or CloudFront delivery has been implemented.

### Ingestion pipeline

- **RSS/Atom sources:** resolve each item link to its canonical CAP XML document.
- **CAP-XML sources:** parsed directly, using each source's configured `cap_xml_root_element` to locate/iterate the `<alert>` elements within the document (there is no standard container element for multiple CAP alerts, so this must be configured per feed).
- **DataQuoll GeoJSON sources:** handled only by the explicit `dataquoll-geojson` adapter in section
  3. Other GeoJSON providers are not supported because their alert schemas are not standardized.
- **EDXL-DE sources** (per new requirement): unwrap the distribution envelope, extract the
  embedded/referenced CAP XML, and run it through the same canonical CAP pipeline. Fall back to the
  envelope's own `targetArea` only if the inner CAP `info.area` is absent. This is an
  **ingestion-only** capability — `cap_alerts`'s own Drupal output stays RSS+CAP-XML only, no EDXL-DE
  emission needed.
- **XXE hardening is mandatory** everywhere untrusted third-party XML is parsed (both here and in any
  Drupal-side parsing) — a real OWASP-relevant vulnerability otherwise.

### Filtering & cancellation semantics

- Each alert's `<info>` blocks (there can be several, e.g. per-language/per-event) are evaluated
  independently; the alert is included if **any** block passes the configured filters.
- Cancellation: CAP `msgType=Cancel`/`references` chain for CAP sources. For the DataQuoll GeoJSON
  adapter, the authoritative signal is its native `retraction.retracted` field; otherwise fall back to
  **2 consecutive missed polls** ("disappeared from feed") as a backstop expiry signal — this absorbs
  transient network blips without hiding genuine cancellations for long.
- Expiry: `info.expires < now` drops the alert immediately, regardless of source.
- **No cross-source deduplication** — different senders use different `identifier` namespaces, so
  attempting to merge duplicates risks false merges.
- **Geo filter:** normalize `polygon`/`circle` to GeoJSON; test intersection against each park's
  boundary. Any alert whose geometry intersects **more than one park's boundary** is duplicated into
  each matching park's per-park output (each evaluated against that park's own filter overrides) —
  keeps every park's JSON self-contained.
- **Partial-failure handling:** if one Feed Source fails/rate-limits mid-cycle, publish using fresh
  data from the succeeding sources plus **last-known-good cached data** (from DynamoDB) for the failed
  one, tagged `degraded: true` in output metadata — a single feed outage shouldn't take down alerts
  from every other source.

## 3. DataQuoll GeoJSON integration specifically — ⬜ Outstanding

[DataQuoll](https://docs.dataquoll.io/) aggregates 34 official Australian emergency-feed sources into
one API. (AusAlert, NEMA's cell-broadcast system, is unrelated — it has no developer API or data feed
at all, nothing to ingest there.) DataQuoll's own documented and recommended integration surface is
its normalized GeoJSON API. Do not special-case its nonstandard CAP-Atom `<id>` values as canonical
links: those URLs resolve to GeoJSON, not CAP XML, and treating them as Atom links would break the
generic Atom contract used for normal feeds.

### Scope and adapter contract

- Add an explicit `dataquoll-geojson` Feed Source format/adapter, rather than extending the generic
  `geojson` format or altering generic RSS/Atom parsing. The Drupal form describes it as a
  DataQuoll-specific normalized incident feed and only exposes its contract when selected.
- Ingest alert data only from **`GET /api/v1/incidents`** and **`GET /api/v1/incidents/nearby`**.
  Both return an incident GeoJSON `FeatureCollection`; `/incidents` is the production all-current-
  incidents source, while `/nearby` is optional/special-purpose because it requires a fixed point and
  radius. The verified `/states` response is `{states: [...]}` and `/status` is service/feed-health
  metadata (`status`, `feeds`, `counts`, etc.), so neither is an alert source and neither enters the
  alert lifecycle/output pipeline. They may later be polled separately for diagnostics only.
- Use one combined `/incidents` source URL per poll cycle, with comma-separated state codes and
  `featureType=incident,incident_area,warning_area,fire_ban_area`, so all-hazard area warnings are
  included. Do not configure an event-type restriction by default. `official=true`, DataQuoll
  severity/urgency/certainty/agency query parameters, and `bbox` are optional upstream volume controls;
  the aggregator's own filters remain authoritative.
- Send the Key-backed bearer credential with every DataQuoll request. Preserve all configured query
  parameters while adding `limit=500` unless a lower explicit limit is configured.

### Pagination and normalization

- Cursor pagination is mandatory. Each `/incidents` page is a `FeatureCollection` with
  `meta.next_cursor`, `meta.has_more`, `meta.total_count`, `meta.generatedAt`, and `links.next`.
  Treat the cursor as opaque: use `URL`/`searchParams.set('cursor', cursor)` for the next request,
  preserving the original URL parameters and authorization header. Stop only when `meta.next_cursor`
  is absent, never on a short page. Do not use `before`/`after` date bounds as a paging mechanism.
- Guard the sweep with a set of seen cursors and a bounded maximum page count. Record `pageCount`,
  `totalCount`, response `generatedAt`, and per-page fetch diagnostics. A malformed response,
  repeated cursor, failed page, or page-limit breach fails the whole source, rather than publishing a
  partial snapshot as current.
- Validate each feature is a GeoJSON `Feature` with a stable `id`; preserve its supplied geometry
  (Point, Polygon, or MultiPolygon) rather than deriving CAP geometry. Do not infer a geometry type
  from DataQuoll `featureType`: a real `featureType: "incident"` can have a Polygon, as well as a
  point. Normalize into the aggregator feature shape: `properties.source.feedSourceId`, plus
  `properties.source.originState`/`originAgency`/`originFeedId`; `title` → `headline`; `eventType`
  → `event`; `details.description` → `description`; `timestamps.reported` → `sent`; and
  `timestamps.updated` → `effective`. Preserve DataQuoll `status`, `warningLevel`, `featureType`,
  `location`, `timestamps.fetched`, and source fields under explicit DataQuoll/origin metadata rather
  than treating them as CAP values.
- `details.expires`, `category`, and `retraction` are optional and are absent from real current-alert
  responses. Map `details.expires` to `expires` only when present; never manufacture an expiry or a
  category from `eventType`/`featureType`. Source filtering must therefore treat a missing expiry as
  non-expired, and a configured category allowlist must exclude a DataQuoll feature unless an explicit
  DataQuoll-to-category mapping is separately defined from the `/api/v1/schema` contract.
  `properties.retraction.retracted === true` is the native cancellation signal when it is returned;
  retain `retraction.retractedAt`/`reason` for diagnostics. The adapter must define how an update is
  identified using the stable feature `id` and `timestamps.updated`, because GeoJSON has no CAP
  `msgType`/`references` chain.
- Apply the established pipeline after all pages have been collected: native retraction and optional
  expiry reduction → aggregator source filters → geographic assignment → park overrides → S3 output.
  Do not apply source filters page-by-page if later pages are needed to resolve a same-ID update or
  retraction.

### Delivery and verification

- Unit tests must cover: first and subsequent authenticated requests; `limit=500`; query preservation;
  exact opaque cursor propagation; all-page aggregation; missing `next_cursor`; repeated cursor;
  failed later page; invalid FeatureCollection/feature rejection; normalized property mapping;
  Point/Polygon/MultiPolygon assignment; retraction/update reduction; and source/park filtering after
  the complete sweep.
- Add fixture-based tests for `/states` and `/status` confirming they are rejected by the alert adapter
  with a clear error, preventing accidental configuration as alert feeds.
- Measure a full live sweep before deployment. The observed volume of 4,285 records needs about nine
  requests at `limit=500`; evaluate it against the current 60-second Lambda timeout, rate limits,
  geometry work, and S3 publication. Increase timeout/memory or narrow the configured upstream scope
  only from measured results.
- **Attribution:** retain the response `attribution` URL and surface it in the published output/Gatsby
  map credit for all DataQuoll-derived features.

## 4. Output schema — ⬜ Outstanding

One static file per park on CloudFront, path convention `/alerts/<gatsby_endpoint_shortcode>.json`
(e.g. `/alerts/knp.json`). No query-parameter API — a plain cached static object is simpler and
cheaper than a live request router, matching req 9. A `_app` mobile consumer strips its suffix and
requests the same file as the paired website shortcode.

```jsonc
{
  "type": "FeatureCollection",
  "schemaVersion": 1,
  "park": "knp",
  "generatedAt": "2026-09-14T05:00:00Z",
  "sources": [
    { "id": "drupal-cap-alerts", "status": "ok", "lastSuccess": "2026-09-14T05:00:00Z" },
    { "id": "dataquoll", "status": "degraded", "lastSuccess": "2026-09-14T04:58:00Z" }
  ],
  "attribution": ["https://dataquoll.io/api/v1/attribution"],
  "features": [
    {
      "type": "Feature",
      "id": "urn:oasis:names:tc:emergency:cap:1.2:...-20260914T0500...",
      "geometry": { "type": "Polygon", "coordinates": [ /* ... */ ] },
      "properties": {
        "source": {
          "feedSourceId": "dataquoll",          // our configured CAP Feed Source
          "originAgency": "RFS",                // underlying agency, when applicable
          "originFeedId": "act-currentincidents" // underlying feed id, when applicable
        },
        "sourceType": "dataquoll-geojson",      // cap-xml | atom | dataquoll-geojson | edxl-de
        "identifier": "...",
        "sender": "...",
        "senderName": "...",
        "event": "Bushfire",
        "category": "Fire",
        "headline": "...",
        "description": "...",
        "instruction": "...",
        "severity": "Severe",
        "certainty": "Observed",
        "urgency": "Immediate",
        "status": "Actual",
        "msgType": "Alert",
        "sent": "2026-09-14T04:55:00+10:00",
        "effective": "2026-09-14T04:55:00+10:00",
        "expires": "2026-09-14T10:00:00+10:00",
        "areaDesc": "...",
        "link": "https://.../api/alerts/{identifier}",
        "degraded": false
      }
    },
    {
      "type": "Feature",
      "geometry": null,
      "properties": {
        "circle": { "center": [149.0, -35.3], "radiusKm": 5 },
        "...": "same property shape as above — rendered as a true L.circle, not a pre-approximated polygon"
      }
    }
  ]
}
```

- `schemaVersion` is included from day one so future consumers (mobile apps) can detect breaking
  changes safely.
- CAP `circle` values have no native GeoJSON equivalent; rather than approximating them into a
  `Polygon` (losing exactness), they're carried as `geometry: null` + `properties.circle` and rendered
  via a true `L.circle` on the Leaflet layer.

## 5. Gatsby frontend — runtime CAP Aggregator retrieval — ⬜ Outstanding

- The CAP Aggregator is the single source of current Alert data for all runtime frontend surfaces.
  Gatsby must not duplicate expiry, cancellation-chain, supersession, or incident-collapsing logic
  in GraphQL or React.
- **Leaflet maps:** fetch `/alerts/<GATSBY_PARK>.json` on mount, then
  `setInterval(fetchAlerts, 60000)`, replacing the layer's GeoJSON source in place — Leaflet layers
  update live without a full map re-render.
- **Drupal Place pages:** when a Place page loads, use its related Location identifier(s) to select
  the matching current Alert features from the per-park Aggregator response, or call a dedicated
  Aggregator Location query if that is added later. Render the current filtered Alerts in the Place
  page's alert callout, rather than rendering the raw Drupal Alert nodes from build-time GraphQL.
- **Access Report maps and tables:** use the same runtime Aggregator response to populate Alert
  overlays, popup content, and table rows. The existing build-time Location data remains useful for
  map points, Location metadata, and Place links; only volatile Alert content is runtime-fetched.
- Include stable Location identifiers in Aggregator features, for example
  `properties.locationIds: ["drupal-location-uuid"]`, so Place pages and Access Reports can join
  current Aggregator Alerts to Drupal Locations without relying on stale Alert-node relationships.
- Remove CAP Alerts from the Gatsby GraphQL/build-time data path for the live Alert display
  (retire the `remoteCapData.tsx.hidden` approach). The Drupal GraphQL relationship may remain for
  association and build-time discovery, but its Alert payload must not be treated as current.
- Compare `generatedAt` against current time and show a **stale-data indicator** if it exceeds a
  threshold (e.g. 5 minutes) — a cheap client-side backstop alongside the CloudWatch alarms, covering
  a silent pipeline failure.
- **Togglable layers (req 8), fine-grained:** the layer-toggle list is built **dynamically** from
  whatever `source.feedSourceId`/`source.originAgency` values are actually present in the fetched
  JSON — not a fixed, hardcoded list — since which agencies are active varies over time and a fixed
  list would silently miss new ones or show dead toggles for retired ones. Raw agency codes (e.g.
  `RFS`, `ESA`) are resolved to human-readable labels using DataQuoll's own `/api/v1/schema` reference
  endpoint (fetched/cached periodically by the aggregator), rather than a hand-maintained mapping that
  could drift.
- Non-CAP data (park boundaries, Location nodes) stays build-time GraphQL as today — only the
  volatile alert data becomes runtime-fetched.

### Current build-time Alert behavior

Drupal-authored Alert nodes now render successfully in the Gatsby frontend through the build-time
GraphQL relationship. This is an ingestion and relationship milestone only: the nodes currently
undergo **no lifecycle filtering** before Gatsby consumes them. Expired and cancelled Alerts remain
visible, as do new and updated Alerts that belong to the same incident chain. A single incident with
five Alert messages therefore appears as five separate frontend Alerts rather than one current
incident Alert. The planned Aggregator must resolve expiry, cancellation references, and incident
chain collapsing before the live-map output is considered complete.

## 6. Gatsby build-time GraphQL — establishing Alert/Location/Place association — 🟨 Partial

Drupal's Alert→Location relationship exists to establish the association needed to connect a Place to
an Alert, not to provide current Alert content. For example: Alert A affects Location A; Location A
is referenced by Place B (`Place.field_related_location`). When Place B loads in Gatsby, the frontend
uses Location A's stable identifier to retrieve current Alert data from the CAP Aggregator.

The Aggregator does not write relationships back to Drupal. It must, however, preserve or derive the
Location identifiers needed by the frontend join for Drupal-authored Alerts and for external Alerts
matched geographically to the same Locations.

- **Alert → Location:** already covered by section 1.C's auto-relate logic — no new geometry code
  needed for Drupal-authored Alerts. This populates the Alert node's existing
  `field_location_reference` field, which is association metadata for Gatsby discovery only.
- **Location → Alert (reverse lookup):** [gatsby-node.js](../parks_australia/frontend/gatsby-node.js)
  already has a `createResolvers` entry for exactly this, currently commented out under
  `// TODO: Disabled to launch Locations separately to CAP Alerts, revert when integrating CAP Alerts`:
  a `relatedCapAlerts` field on `node__location`, resolving to all `node__cap_alert_message` nodes
  whose `field_location_reference` contains that Location's id (via `context.nodeModel.findAll` with
  an `elemMatch` filter). This reverse resolver can identify related Location IDs during the build,
  but its Alert payload is not authoritative or current. **Action: retain/re-enable only as
  association discovery while migrating the rendered Alert content to the Aggregator.**
- **Place → Location → Aggregator Alert:** `Place.field_related_location` already forward-references
  Location. The Place component should read the Location ID(s), fetch the current per-park
  Aggregator response, and render only matching, already-filtered features.
- **Access Report Location → Aggregator Alert:** the existing Location GraphQL data continues to
  provide coordinates, titles, access status, and Place links for map/table rows. Alert overlays,
  popup content, and alert table rows must come from the same current Aggregator response rather than
  from `relatedCapAlerts` nodes.
- **Freshness:** Drupal saves continue to trigger Gatsby builds for association/content changes via
  the Gatsby Endpoints module, but Alert freshness for Place pages and Access Reports comes from the
  runtime Aggregator polling cycle, not from the last build.

## Requirement coverage summary

| Req | Covered by |
|---|---|
| 1. 60s refresh | Gatsby client polling + 60s Lambda/cache cycle (~120s worst-case staleness, accepted) |
| 2. Feed URLs managed in Drupal, per-park + filter criteria | `CAP Feed Source` config entity + per-park overrides |
| 3. Cancellation-chain filtering | Aggregator: CAP references/expires, native retraction fields, 2-poll grace-period backstop |
| 4. Auto-relate Location nodes | Drupal `hook_ENTITY_TYPE_presave` + geometry test, scoped to matching `field_site` |
| 5. External feed alerts never persisted as nodes | Aggregator only, no Drupal node creation |
| 6. Real-time filtering incl. cancellation on Gatsby | Aggregator output already filtered; map, Place pages, and Access Reports consume it at runtime |
| 7. RSS/Atom/XML feed support | Aggregator resolves RSS/Atom item links → canonical CAP XML |
| 8. Togglable Leaflet layers | Dynamic, per `feedSourceId`/`originAgency`, fine-grained |
| 9. Low long-term maintenance | Single shared pipeline (Drupal = "just a feed"), serverless hosting, Drupal-editable config entity, DataQuoll schema reused for labels |
| New: DataQuoll GeoJSON ingestion | Explicit `dataquoll-geojson` adapter for DataQuoll's documented incident schema only |
| New: EDXL-DE ingestion | Envelope unwrap → canonical CAP pipeline (ingestion-only) |
| New: Alert ↔ Place relationship via GraphQL | Section 6 — Drupal relationship establishes Location/Place association; current Alert data is retrieved from the Aggregator |
| New: Source healthcheck change-only logging | Section 2, "Source healthcheck logging" — UTC-timestamped log entry on first poll and on any status/error change only |

## Open items / prerequisites

- Obtain the per-park boundary GeoJSON files (to be provided, versioned in the aggregator repo).
- Provision a DataQuoll API key for the `/api/v1/incidents` GeoJSON feed.
- Confirm AWS account/environment for the new aggregator repo's SAM deployment.