<?php

namespace Drupal\cap_alerts_aggregator_connector;

use Drupal\Core\Config\Entity\ConfigEntityListBuilder;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Url;

/**
 * Provides a listing of CAP Feed Source entities.
 */
class CapFeedSourceListBuilder extends ConfigEntityListBuilder {

  /**
   * {@inheritdoc}
   */
  public function render() {
    $build = parent::render();
    $build['endpoint_info'] = $this->buildEndpointInfo();
    $build['endpoint_info']['#weight'] = -10;
    return $build;
  }

  /**
   * Builds the explanatory markup shown above the feed sources table.
   */
  private function buildEndpointInfo(): array {
    $endpointPath = Url::fromRoute('cap_alerts_aggregator_connector.api.feed_sources')->toString();
    $settingsUrl = Url::fromRoute('cap_alerts_aggregator_connector.settings_form');

    return [
      '#type' => 'container',
      '#attributes' => ['class' => ['cap-feed-source-endpoint-info']],
      'path' => [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('<strong>Manage your CAP Alert Feed sources here.</strong></p><p>These feeds are not imported into Drupal, but are instead exposed via a custom endpoint to be ingested into a CAP aggregator elsewhere: <code>@path</code>', [
          '@path' => $endpointPath,
        ]) . '</p>',
      ],
      'explanation' => [
        '#type' => 'markup',
        '#markup' => '<p>' . $this->t('This endpoint is protected because it exposes any Key values you may add to the Feed sources.</p><p>Access it by configuring a shared secret and by sending it in an <code>X-Cap-Aggregator-Secret</code> request header. Requests with a missing or incorrect secret receive a 403 response with no feed data.</p><p>Configure the shared secret on the <a href=":settings_url">CAP Aggregator Connector settings</a> page.', [
          ':settings_url' => $settingsUrl->toString(),
        ]) . '</p><p>Note that the endpoint health of a Feed source is not tested by this listing, as this module does not fetch any data from them.</p>',
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildHeader() {
    $header['label'] = $this->t('Label');
    $header['feed_url'] = $this->t('Feed URL');
    $header['status'] = $this->t('Status');
    $header['feed_format'] = $this->t('Format');
    return $header + parent::buildHeader();
  }

  /**
   * {@inheritdoc}
   */
  public function buildRow(EntityInterface $entity) {
    /** @var \Drupal\cap_alerts_aggregator_connector\Entity\CapFeedSourceInterface $entity */
    $row['label'] = $entity->label();
    $row['feed_url'] = $entity->getFeedUrl();
    $row['status'] = $entity->isFeedEnabled() ? $this->t('Enabled') : $this->t('Disabled');
    $row['feed_format'] = $entity->getFeedFormat();
    return $row + parent::buildRow($entity);
  }

}
