<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;

/**
 * Adds the node subscription settings to the main Newsroom settings form.
 *
 * This is done via a form alter instead of a dedicated settings form and route.
 */
class NewsroomSettingsFormAlter {

  use StringTranslationTrait;

  public function __construct(
    protected readonly ConfigFactoryInterface $configFactory,
    TranslationInterface $stringTranslation,
  ) {
    $this->setStringTranslation($stringTranslation);
  }

  /**
   * Implements hook_form_FORM_ID_alter() for the newsroom settings form.
   */
  #[Hook('form_newsroom_settings_form_alter')]
  public function alterForm(array &$form, FormStateInterface $form_state): void {
    $config = $this->configFactory->get('oe_newsroom_node.settings');

    // The node notification service ID is provided by the parent module but is
    // mandatory for the node subscription feature to work.
    if (isset($form['node_service_id'])) {
      $form['node_service_id']['#required'] = TRUE;
    }

    $form['node_subscription'] = [
      '#type' => 'details',
      '#title' => $this->t('Node subscription'),
      '#open' => TRUE,
    ];
    $form['node_subscription']['node_privacy_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Privacy URL'),
      '#description' => $this->t('URL to the privacy page used by the node subscription form. This is independent from the newsletter privacy URL.'),
      '#maxlength' => 255,
      '#default_value' => str_replace('internal:', '', (string) $config->get('privacy_url')),
      '#required' => TRUE,
      '#element_validate' => [[$this, 'validateUriElement']],
    ];

    $form['#submit'][] = [$this, 'submitForm'];
  }

  /**
   * Submit callback to save the node subscription settings.
   *
   * @param array $form
   *   The Newsroom settings form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Store the value as a URI so it can be passed to Url::fromUri() directly.
    // Paths without a scheme are treated as internal.
    $url = trim($form_state->getValue('node_privacy_url'));
    if (parse_url($url, PHP_URL_SCHEME) === NULL) {
      $url = 'internal:' . $url;
    }

    $this->configFactory
      ->getEditable('oe_newsroom_node.settings')
      ->set('privacy_url', $url)
      ->save();
  }

  /**
   * Validates and normalizes a user-entered URI.
   *
   * @param array $element
   *   The privacy URL form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $form
   *   The complete settings form.
   */
  public function validateUriElement(array &$element, FormStateInterface $form_state, array &$form): void {
    $value = trim((string) $element['#value']);
    $uri = $value;

    if ($value !== '' && parse_url($value, PHP_URL_SCHEME) === NULL) {
      if (str_starts_with($value, '<front>')) {
        $uri = '/' . substr($value, strlen('<front>'));
      }
      $uri = 'internal:' . $uri;
    }

    $form_state->setValueForElement($element, $uri);

    // Internal paths must start with '/', '?' or '#'. Relative internal paths
    // are normalized to the URI format used by the configuration value.
    if (
      parse_url($uri, PHP_URL_SCHEME) === 'internal' &&
      $value !== '' &&
      !in_array($value[0], ['/', '?', '#'], TRUE) &&
      !str_starts_with($value, '<front>')
    ) {
      $form_state->setError($element, $this->t('Manually entered paths should start with one of the following characters: / ? #'));
    }
  }

}
