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
 *     "credential",
 *     "min_severity",
 *     "min_certainty",
 *     "min_urgency",
 *     "category_allowlist",
 *     "msgtype_denylist",
 *     "agency_allowlist",
 *     "agency_denylist",
 *     "require_geometry"
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
   * Optional credential (bearer token/API key) required to fetch the feed.
   *
   * TODO: store via the Key module instead of plain config once it can be
   * installed in this environment (composer.io/GitHub auth is currently
   * blocked here) — see /memories/repo/cap-alerts-parks-australia-drupal-notes.md.
   */
  protected string $credential = '';

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

}
