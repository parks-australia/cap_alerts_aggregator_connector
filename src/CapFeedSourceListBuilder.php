<?php

namespace Drupal\cap_alerts_parks_australia;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;

/**
 * Provides a listing of CAP Feed Source entities.
 */
class CapFeedSourceListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['feed_url'] = $this->t('Feed URL');
    $header['feed_format'] = $this->t('Format');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\cap_alerts_parks_australia\Entity\CapFeedSourceInterface $entity */
    $row['label'] = $entity->label();
    $row['feed_url'] = $entity->getFeedUrl();
    $row['feed_format'] = $entity->getFeedFormat();
    return $row + parent::buildRow($entity);
  }

}
