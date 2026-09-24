<?php

namespace Drupal\cap_alerts_aggregator_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityBase;

/**
 * Defines the CAP Feed Source config entity.
 *
 * Represents one external CAP-compatible feed (RSS/Atom/CAP-XML/GeoJSON/
 * EDXL-DE) polled by the CAP Aggregator. These are always park-agnostic —
 * unlike Drupal-authored `cap_alert_message` nodes (which carry an explicit
 * `field_site`), external alerts are attributed to a park by the aggregator
 * via geometry matching against each park's boundary, never configured here.
 *
 * @ConfigEntityType(
 *   id = "cap_feed_source",
 *   label = @Translation("CAP Feed Source"),
 *   label_collection = @Translation("CAP Feed Sources"),
 *   handlers = {
 *     "list_builder" = "Drupal\cap_alerts_aggregator_connector\CapFeedSourceListBuilder",
 *     "form" = {
 *       "add" = "Drupal\cap_alerts_aggregator_connector\Form\CapFeedSourceForm",
 *       "edit" = "Drupal\cap_alerts_aggregator_connector\Form\CapFeedSourceForm",
 *       "delete" = "Drupal\Core\Entity\EntityDeleteForm"
 *     },
 *     "route_provider" = {
 *       "html" = "Drupal\Core\Entity\Routing\AdminHtmlRouteProvider",
 *     },
 *   },
 *   config_prefix = "cap_feed_source",
 *   admin_permission = "administer cap feed sources",
 *   entity_keys = {
 *     "id" = "id",
 *     "label" = "label",
 *     "status" = "status"
 *   },
 *   links = {
 *     "collection" = "/admin/config/services/cap-alerts/feeds",
 *     "add-form" = "/admin/config/services/cap-alerts/feeds/add",
 *     "edit-form" = "/admin/config/services/cap-alerts/feeds/{cap_feed_source}",
 *     "delete-form" = "/admin/config/services/cap-alerts/feeds/{cap_feed_source}/delete"
 *   },
 *   config_export = {
 *     "id",
 *     "label",
 *     "status",
 *     "feed_url",
 *     "feed_format",
 *     "cap_xml_root_element",
 *     "credential_key",
 *     "min_severity",
 *     "min_certainty",
 *     "min_urgency",
 *     "category_allowlist",
 *     "msgtype_denylist",
 *     "agency_allowlist",
 *     "agency_denylist",
 *     "require_geometry",
 *     "park_overrides"
 *   }
 * )
 */
class CapFeedSource extends ConfigEntityBase implements CapFeedSourceInterface {

  /**
   * The machine name.
   */
  protected string $id;

  /**
   * The human-readable label.
   */
  protected string $label;

  /**
   * The feed URL.
   */
  protected string $feed_url = '';

  /**
   * The feed format: rss|atom|cap-xml|geojson|edxl-de.
   */
  protected string $feed_format = 'rss';

  /**
   * Root/parent element name wrapping the <alert> elements in a CAP-XML feed.
   *
   * CAP v1.2 only defines the <alert> element itself, not a container for
   * multiple alerts in one document/feed, so this varies from feed to feed.
   * Only meaningful (and required) when feed_format is 'cap-xml'.
   */
  protected string $cap_xml_root_element = '';

  /**
   * ID of the Key entity holding this feed's bearer token/API key, if any.
   */
  protected string $credential_key = '';

  /**
   * Minimum CAP severity to include: Extreme|Severe|Moderate|Minor|Unknown.
   */
  protected string $min_severity = 'Unknown';

  /**
   * Minimum CAP certainty to include: Observed|Likely|Possible|Unlikely|Unknown.
   */
  protected string $min_certainty = 'Unknown';

  /**
   * Minimum CAP urgency to include: Immediate|Expected|Future|Past|Unknown.
   */
  protected string $min_urgency = 'Unknown';

  /**
   * Comma-separated list of CAP categories to allow (empty = allow all).
   */
  protected string $category_allowlist = '';

  /**
   * Comma-separated list of CAP msgType values to exclude.
   */
  protected string $msgtype_denylist = '';

  /**
   * Comma-separated list of originating agency codes to allow (empty = all).
   */
  protected string $agency_allowlist = '';

  /**
   * Comma-separated list of originating agency codes to exclude.
   */
  protected string $agency_denylist = '';

  /**
   * Whether a polygon/circle is required for an alert to be shown.
   */
  protected bool $require_geometry = FALSE;

  /**
   * Per-park filter overrides for this (typically multi-park) source.
   *
   * Each item: {gatsby_endpoint, min_severity, min_certainty, min_urgency,
   * category_allowlist, agency_allowlist, agency_denylist}. Blank override
   * values mean "use this source's base filter" for that field.
   *
   * @var array<int, array<string, string>>
   */
  protected array $park_overrides = [];

  /**
   * {@inheritdoc}
   */
  public function getFeedUrl(): string {
    return $this->feed_url;
  }

  /**
   * {@inheritdoc}
   */
  public function getFeedFormat(): string {
    return $this->feed_format;
  }
  
  /**
   * {@inheritdoc}
   */
  public function isFeedEnabled(): bool {
    return $this->status;
  }

  /**
   * {@inheritdoc}
   */
  public function getCapXmlRootElement(): string {
    return $this->cap_xml_root_element;
  }

  /**
   * {@inheritdoc}
   */
  public function getCredentialKeyId(): string {
    return $this->credential_key;
  }

  /**
   * {@inheritdoc}
   */
  public function getParkOverrides(): array {
    return $this->park_overrides;
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    parent::calculateDependencies();
    if ($this->credential_key !== '') {
      $this->addDependency('config', 'key.key.' . $this->credential_key);
    }
    return $this;
  }

}
