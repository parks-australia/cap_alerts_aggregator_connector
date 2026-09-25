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
  private const BASE_FEED_FORMATS = [
    'rss' => 'RSS (items link to canonical CAP XML)',
    'atom' => 'Atom (entries link to canonical CAP XML)',
    'cap-xml' => 'CAP XML (feed itself is the alert)',
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
    $feedFormats = self::BASE_FEED_FORMATS;
    \Drupal::moduleHandler()->alter('cap_feed_source_formats', $feedFormats);
    $form['feed_format'] = [
      '#type' => 'select',
      '#title' => $this->t('Feed format'),
      '#description' => $this->t('The format must be set explicitly; the aggregator does not auto-detect it. Note that RSS and Atom feeds will only be imported if their items link to valid CAP-XML alerts. Embedded alerts in the <code>content</code> element will be ignored, as will links returning non-CAP XML responses.'),
      '#options' => $feedFormats,
      '#default_value' => $entity->get('feed_format'),
      '#required' => TRUE,
    ];
    $form['cap_xml_root_element'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CAP-XML root element name'),
      '#description' => $this->t('CAP v1.2 only defines the &lt;alert&gt; element itself, not a container for multiple alerts in one feed, so this varies from feed to feed. Enter the parent/root element name wrapping the &lt;alert&gt; elements in this feed, e.g. "alerts".'),
      '#default_value' => $entity->get('cap_xml_root_element'),
      '#states' => [
        'visible' => [
          ':input[name="feed_format"]' => ['value' => 'cap-xml'],
        ],
        'required' => [
          ':input[name="feed_format"]' => ['value' => 'cap-xml'],
        ],
      ],
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
      '#title' => $this->t('Aggregator filters'),
      '#description' => $this->t('These filters are applied by the CAP Aggregator after it retrieves and normalizes alerts. They do not add query parameters to the Feed URL or reduce requests made to the provider.'),
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
      '#title' => $this->t('CAP sender allowlist'),
      '#description' => $this->t('Comma-separated CAP &lt;sender&gt; values to include. Leave blank to allow all.'),
      '#default_value' => $entity->get('agency_allowlist'),
    ];
    $form['filters']['agency_denylist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CAP sender denylist'),
      '#description' => $this->t('Comma-separated CAP &lt;sender&gt; values to exclude.'),
      '#default_value' => $entity->get('agency_denylist'),
    ];
    $form['filters']['require_geometry'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Require polygon/circle geometry'),
      '#description' => $this->t('Only applies to CAP-XML-style sources.'),
      '#default_value' => $entity->get('require_geometry'),
    ];

    \Drupal::moduleHandler()->alter('cap_feed_source_form', $form, $form_state, $entity);

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('feed_format') === 'cap-xml' && trim((string) $form_state->getValue('cap_xml_root_element')) === '') {
      $form_state->setErrorByName('cap_xml_root_element', $this->t('The CAP-XML root element name is required when the feed format is CAP XML.'));
    }
    $duplicates = $this->getDuplicateFeedSourceLabels((string) $form_state->getValue('feed_url'));
    if ($duplicates) {
      $form_state->setErrorByName('feed_url', $this->t('This Feed URL is already used by: @sources. Configure one source and use its filters or per-park overrides instead.', [
        '@sources' => implode(', ', $duplicates),
      ]));
    }
    \Drupal::moduleHandler()->alter('cap_feed_source_validate', $form, $form_state, $this->entity);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $result = parent::save($form, $form_state);
    \Drupal::moduleHandler()->invokeAll('cap_feed_source_save', [$this->entity, $form_state]);
    $this->messenger()->addStatus($this->t('Saved the %label CAP Feed Source.', [
      '%label' => $this->entity->label(),
    ]));
    $form_state->setRedirectUrl($this->entity->toUrl('collection'));
    return $result;
  }

  /**
  * Returns labels of other feed sources with the same normalized URL.
   */
  private function getDuplicateFeedSourceLabels(string $feedUrl): array {
    $normalizedFeedUrl = $this->normalizeFeedUrl($feedUrl);
    if ($normalizedFeedUrl === '') {
      return [];
    }

    $storage = $this->entityTypeManager->getStorage('cap_feed_source');
    $duplicates = [];
    foreach ($storage->loadMultiple() as $source) {
      /** @var \Drupal\cap_alerts_aggregator_connector\Entity\CapFeedSourceInterface $source */
      if ($source->id() === $this->entity->id()) {
        continue;
      }
      if ($this->normalizeFeedUrl($source->getFeedUrl()) === $normalizedFeedUrl) {
        $duplicates[] = $source->label();
      }
    }
    return $duplicates;
  }

  /**
   * Makes harmless URL presentation differences compare consistently.
   */
  private function normalizeFeedUrl(string $feedUrl): string {
    $feedUrl = trim($feedUrl);
    $fragmentPosition = strpos($feedUrl, '#');
    if ($fragmentPosition !== FALSE) {
      $feedUrl = substr($feedUrl, 0, $fragmentPosition);
    }
    return rtrim($feedUrl, '/');
  }

}
