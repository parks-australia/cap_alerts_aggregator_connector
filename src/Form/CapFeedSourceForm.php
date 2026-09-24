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
    'dataquoll-geojson' => 'DataQuoll GeoJSON (normalized incident feed)',
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
      '#description' => $this->t('Only applies to CAP-XML-style sources. DataQuoll GeoJSON features are matched to park boundaries using their supplied geometry.'),
      '#default_value' => $entity->get('require_geometry'),
    ];

    $form['park_overrides_wrapper'] = $this->buildParkOverridesForm($form_state);

    return $form;
  }

  /**
   * Builds the repeatable "per-park override" AJAX sub-form.
   */
  private function buildParkOverridesForm(FormStateInterface $form_state): array {
    /** @var \Drupal\cap_alerts_aggregator_connector\Entity\CapFeedSourceInterface $entity */
    $entity = $this->entity;

    if ($form_state->get('park_overrides_count') === NULL) {
      $existing = $entity->getParkOverrides();
      $form_state->set('park_overrides_count', max(count($existing), 1));
      $form_state->set('park_overrides_initial', array_values($existing));
    }
    $count = $form_state->get('park_overrides_count');
    $initial = $form_state->get('park_overrides_initial');

    $wrapper = [
      '#type' => 'details',
      '#title' => $this->t('Per-park overrides'),
      '#description' => $this->t('These overrides apply only to the matching park\'s published output, after geographic matching. Leave a field blank to use this source\'s base filter for that park.'),
      '#open' => !empty($initial),
      '#tree' => TRUE,
      '#prefix' => '<div id="park-overrides-wrapper">',
      '#suffix' => '</div>',
    ];

    $wrapper['park_overrides'] = ['#tree' => TRUE];
    for ($i = 0; $i < $count; $i++) {
      $default = $initial[$i] ?? [];
      $wrapper['park_overrides'][$i] = $this->buildParkOverrideRow($i, $default);
    }

    $wrapper['add'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another override'),
      '#submit' => ['::addParkOverride'],
      '#ajax' => [
        'callback' => '::parkOverridesAjax',
        'wrapper' => 'park-overrides-wrapper',
      ],
    ];

    return $wrapper;
  }

  /**
   * Builds one row of the park_overrides sub-form.
   */
  private function buildParkOverrideRow(int $delta, array $default): array {
    $row = [
      '#type' => 'fieldset',
      '#title' => $this->t('Override @n', ['@n' => $delta + 1]),
    ];
    $row['gatsby_endpoint'] = [
      '#type' => 'select',
      '#title' => $this->t('Park'),
      '#options' => ['' => $this->t('- Select -')] + $this->getWebsiteGatsbyEndpointOptions(),
      '#default_value' => $default['gatsby_endpoint'] ?? '',
    ];
    $row['min_severity'] = [
      '#type' => 'select',
      '#title' => $this->t('Minimum severity override'),
      '#options' => ['' => $this->t('- No override -')] + array_combine(self::SEVERITIES, self::SEVERITIES),
      '#default_value' => $default['min_severity'] ?? '',
    ];
    $row['min_certainty'] = [
      '#type' => 'select',
      '#title' => $this->t('Minimum certainty override'),
      '#options' => ['' => $this->t('- No override -')] + array_combine(self::CERTAINTIES, self::CERTAINTIES),
      '#default_value' => $default['min_certainty'] ?? '',
    ];
    $row['min_urgency'] = [
      '#type' => 'select',
      '#title' => $this->t('Minimum urgency override'),
      '#options' => ['' => $this->t('- No override -')] + array_combine(self::URGENCIES, self::URGENCIES),
      '#default_value' => $default['min_urgency'] ?? '',
    ];
    $row['category_allowlist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Category allowlist override'),
      '#default_value' => $default['category_allowlist'] ?? '',
    ];
    $row['agency_allowlist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CAP sender allowlist override'),
      '#default_value' => $default['agency_allowlist'] ?? '',
    ];
    $row['agency_denylist'] = [
      '#type' => 'textfield',
      '#title' => $this->t('CAP sender denylist override'),
      '#default_value' => $default['agency_denylist'] ?? '',
    ];
    return $row;
  }

  /**
   * Gets `{id: label}` options for non-"_app" gatsby_endpoint entities.
   */
  private function getWebsiteGatsbyEndpointOptions(): array {
    $storage = $this->entityTypeManager->getStorage('gatsby_endpoint');
    $options = [];
    foreach ($storage->loadMultiple() as $endpoint) {
      if (!str_ends_with($endpoint->id(), '_app')) {
        $options[$endpoint->id()] = $endpoint->label();
      }
    }
    return $options;
  }

  /**
   * Submit handler for the "Add another override" button.
   */
  public function addParkOverride(array &$form, FormStateInterface $form_state): void {
    $form_state->set('park_overrides_count', $form_state->get('park_overrides_count') + 1);
    $form_state->setRebuild();
  }

  /**
   * AJAX callback returning the rebuilt park_overrides wrapper.
   */
  public function parkOverridesAjax(array &$form, FormStateInterface $form_state): array {
    return $form['park_overrides_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form, $form_state);
    if ($form_state->getValue('feed_format') === 'cap-xml' && trim((string) $form_state->getValue('cap_xml_root_element')) === '') {
      $form_state->setErrorByName('cap_xml_root_element', $this->t('The CAP-XML root element name is required when the feed format is CAP XML.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state): int {
    $overrides = [];
    foreach ((array) $form_state->getValue(['park_overrides_wrapper', 'park_overrides']) as $row) {
      if (!empty($row['gatsby_endpoint'])) {
        $overrides[] = $row;
      }
    }
    $this->entity->set('park_overrides', array_values($overrides));

    $result = parent::save($form, $form_state);
    $duplicates = $this->getDuplicateFeedSourceLabels((string) $form_state->getValue('feed_url'));
    if ($duplicates) {
      $this->messenger()->addWarning($this->t('This Feed URL is also used by: @sources. The aggregator polls every enabled source independently, so overlapping feeds can publish duplicate alerts.', [
        '@sources' => implode(', ', $duplicates),
      ]));
    }
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
