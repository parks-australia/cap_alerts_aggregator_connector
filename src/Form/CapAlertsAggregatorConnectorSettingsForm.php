<?php

namespace Drupal\cap_alerts_aggregator_connector\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the shared secret used to authenticate the CAP Aggregator.
 */
class CapAlertsAggregatorConnectorSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['cap_alerts_aggregator_connector.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'cap_alerts_aggregator_connector_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('cap_alerts_aggregator_connector.settings');
    $form['aggregator_secret_key'] = [
      '#type' => 'key_select',
      '#title' => $this->t('Aggregator shared secret'),
      '#description' => $this->t('The CAP Aggregator must send this value in the <code>X-Cap-Aggregator-Secret</code> header when calling /api/cap-alerts/feed-sources. Manage Keys at <a href=":url">/admin/config/system/keys</a>.', [':url' => '/admin/config/system/keys']),
      '#default_value' => $config->get('aggregator_secret_key'),
      '#empty_option' => $this->t('- None set (endpoint will refuse all requests) -'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config('cap_alerts_aggregator_connector.settings')
      ->set('aggregator_secret_key', $form_state->getValue('aggregator_secret_key'))
      ->save();
    parent::submitForm($form, $form_state);
  }

}
