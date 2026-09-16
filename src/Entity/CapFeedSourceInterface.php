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
   * Gets the feed format (rss|atom|cap-xml|geojson|edxl-de).
   */
  public function getFeedFormat(): string;

  /**
   * Gets the ID of the Key entity holding this feed's credential, if any.
   */
  public function getCredentialKeyId(): string;

}
