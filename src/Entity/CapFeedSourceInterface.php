<?php

namespace Drupal\cap_alerts_aggregator_connector\Entity;

use Drupal\Core\Config\Entity\ConfigEntityInterface;

/**
 * Provides an interface for CAP Feed Source entities.
 */
interface CapFeedSourceInterface extends ConfigEntityInterface {

  /**
   * Gets the feed URL.
   */
  public function getFeedUrl(): string;

  /**
  * Gets the feed format.
   */
  public function getFeedFormat(): string;

  /**
   * Checks if the feed is enabled.
   */
  public function isFeedEnabled(): bool;

  /**
   * Gets the root/parent element name wrapping <alert>s in a CAP-XML feed.
   *
   * Only meaningful when the feed format is 'cap-xml'.
   */
  public function getCapXmlRootElement(): string;

  /**
   * Gets the ID of the Key entity holding this feed's credential, if any.
   */
  public function getCredentialKeyId(): string;

  /**
   * Gets the per-park filter overrides for this source.
   *
   * @return array<int, array<string, string>>
   *   Each item: {gatsby_endpoint, min_severity, min_certainty, min_urgency,
   *   category_allowlist, agency_allowlist, agency_denylist}.
   */
  public function getParkOverrides(): array;

}
