<?php

namespace Drupal\cap_alerts_aggregator_connector\Form;

use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Form\FormStateInterface;

/**
 * Add/edit form for the CAP Feed Source config entity.
 */
class CapFeedSourceForm extends EntityForm {

  /**
   * Valid CAP v1.2 feed formats supported by the aggregator.
   */
  private const FEED_FORMATS = [
    'rss' => 'RSS (items link to canonical CAP XML)',
    'atom' => 'Atom (entries link to canonical CAP XML)',
    'cap-xml' => 'CAP XML (feed itself is the alert)',
    'geojson' => 'GeoJSON (other external CAP aggregators)',
    'edxl-de' => 'EDXL-DE (distribution envelope)',
  ];

  /**
   * CAP v1.2 severity values, in ascending order.
   */
  private const SEVERITIES = ['Unknown', 'Minor', 'Moderate', 'Severe', 'Extreme'];

  /**
   * CAP v1.2 certainty values, in ascending order.
   */
  private const CERTAINTIES = ['Unknown', 'Unlikely', 'Possible', 'Likely', 'Observed'];

  /**
   * CAP v1.2 urgency values, in ascending order.
   */
  private const URGENCIES = ['Unknown', 'Past', 'Future', 'Expected', 'Immediate'];

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state): array {
    $form = parent::form($form, $form_state);
    /** @var \Drupal\cap_alerts_aggregator_connector\Entity\CapFeedSourceInterface $entity */
    $entity = $this->entity;

    $form['label'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label'),
      '#default_value' => $entity->label(),
      '#required' => TRUE,
    ];
    $form['id'] = [
      '#type' => 'machine_name',
      '#default_value' => $entity->id(),
      '#machine_name' => [
        'exists' => ['Drupal\cap_alerts_aggregator_connector\Entity\CapFeedSource', 'load'],
      ],
      '#disabled' => !$entity->isNew(),
    ];
    $form['status'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#default_value' => $entity->status(),
    ];
    $form['feed_url'] = [
      '#type' => 'url',
      '#title' => $this->t('Feed URL'),
      '#default_value' => $entity->get('feed_url'),
      '#required' => TRUE,
    ];
    $form['feed_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Feed format'),
      '#description' => $this->t('The format must be set explicitly; the aggregator does not auto-detect it.'),
      '#options' => self::FEED_FORMATS,
      '#default_value' => $entity->get('feed_format'),
      '#required' => TRUE,
    ];
    $form['credential_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Credential'),
      '#description' => $this->t('The Key holding this feed\'s bearer token/API key, if it requires authentication. Manage Keys at <a href=":url">/admin/config/system/keys</a>.', [':url' => '/admin/config/system/keys']),
      '#default_value' => $entity->get('credential_key'),
      '#empty_option' => $this->t('- No credential required -'),
    ];

    $form['filters'] = [
      '#type' => 'details',
      '#title' => $this->t('Filters'),
      '#open' => TRUE,
    ];
    $form['filters']['min_severity'] = [
      '#type' => 'select',
      '#title' => $this->t('Minimum severity'),
      '#options' => array_combine(self::SEVERITIES, self::SEVERITIES),
      '#default_value' => $entity->get('min_severity'),
    ];
    $form['filters']['min_certainty'] = [
      '#type' => 'select',
      '#title' => $this->t('Minimum certainty'),
      '#options' => array_combine(self::CERTAINTIES, self::CERTAINTIES),
      '#default_value' => $entity->get('min_certainty'),
    ];
    $form['filters']['min_urgency'] = [
      '#type' => 'select',
      '#title' => $this->t('Minimum urgency'),
      '#options' => array_combine(self::URGENCIES, self::URGENCIES),
      '#default_value' => $entity->get('min_urgency'),
    ];
    $form['filters']['category_allowlist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category allowlist'),
      '#description' => $this->t('Comma-separated CAP categories to include. Leave blank to allow all.'),
      '#default_value' => $entity->get('category_allowlist'),
    ];
    $form['filters']['msgtype_denylist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('msgType denylist'),
      '#description' => $this->t('Comma-separated CAP msgType values to exclude, e.g. "Error".'),
      '#default_value' => $entity->get('msgtype_denylist'),
    ];
    $form['filters']['agency_allowlist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agency allowlist'),
      '#description' => $this->t('Comma-separated originating agency codes to include. Leave blank to allow all.'),
      '#default_value' => $entity->get('agency_allowlist'),
    ];
    $form['filters']['agency_denylist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Agency denylist'),
      '#description' => $this->t('Comma-separated originating agency codes to exclude, e.g. non-hazard incident feeds.'),
      '#default_value' => $entity->get('agency_denylist'),
    ];
    $form['filters']['require_geometry'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require polygon/circle geometry'),
      '#description' => $this->t('Only applies to CAP-XML-style sources. Point-only GeoJSON sources are instead filtered by point-in-park-boundary.'),
      '#default_value' => $entity->get('require_geometry'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    $this->messenger()->addStatus($this->t('Saved the %label CAP Feed Source.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

}
