# CAP Alerts Aggregator Connector

Drupal configuration module for supplying enabled CAP Feed Sources to an
external CAP alerts aggregator. It stores source URLs, credentials through the
Key module, and source-level filters; it does not fetch or ingest alerts itself.

The protected endpoint is:

```bash
GET /api/cap-alerts/feed-sources
```

Callers must send the configured `X-Cap-Aggregator-Secret` header. The response
contains resolved source credentials, so it is private and explicitly marked
uncacheable.

## Base Module

The base module provides:

- CAP Feed Source configuration entities.
- RSS, Atom, CAP XML, and EDXL-DE source format choices.
- Optional Key-backed credentials.
- Source-level severity, certainty, urgency, category, message-type, sender, and
  geometry filters.
- Duplicate URL validation.
- The protected Feed Source API endpoint.

The base module deliberately does not include provider-specific source formats
or organisation-specific park concepts. Optional integrations add those
behaviors.

## Optional Submodules

### DataQuoll Adaptor

`cap_alerts_aggregator_connector_dataquoll_adaptor` adds the `dataquoll-geojson`
Feed Source format for DataQuoll's documented normalized incident GeoJSON API.
It is separate so DataQuoll pagination, authentication, and schema assumptions
are not part of the portable base connector.

Enable it only when the paired CAP aggregator deployment has the corresponding
DataQuoll ingestion adapter.

### Parks Overrides

`cap_alerts_aggregator_connector_parks_overrides` depends on the
organisation-specific `gatsby_endpoints` module. It adds per-`gatsby_endpoint`
filter override groups to Feed Sources.

A park with no override uses the source filters after geographic matching. An
override replaces only the filter values that are populated; blank fields
inherit the corresponding source filter. A group with every filter blank has no
effect. Only one override group is permitted for each park.

The submodule stores override configuration separately and appends
`parkOverrides` to the protected Feed Source API response while enabled.
