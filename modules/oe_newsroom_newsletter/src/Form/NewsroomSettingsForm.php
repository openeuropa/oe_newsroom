<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;

/**
 * Newsroom Settings Form.
 */
class NewsroomSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      OeNewsroomNewsletter::CONFIG_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_newsroom_newsletter_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(OeNewsroomNewsletter::CONFIG_NAME);

    $form['privacy_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Privacy URL'),
      '#description' => $this->t(
        "URL to the privacy page. Enter an internal path such as %internal or an external URL such as %url. Enter %front to link to the front page. Use %lang_code as a language token.",
        [
          '%front' => '<front>',
          '%internal' => '/node/2',
          '%url' => 'https://ec.europa.eu',
          '%lang_code' => '[lang_code]',
        ]
      ),
      '#maxlength' => 255,
      '#default_value' => $config->get('privacy_uri'),
      '#required' => TRUE,
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $uri = trim($form['privacy_uri']['#value']);

    if (parse_url($uri, PHP_URL_SCHEME) === NULL) {
      if (strpos($uri, '<front>') !== FALSE && $uri !== '<front>') {
        // Only support the <front> token if it's on its own.
        $form_state->setError($form['privacy_uri'], t('The path %uri is invalid.', ['%uri' => $uri]));
        return;
      }

      if (strpos($uri, '<front>') === 0) {
        $uri = '/' . substr($uri, strlen('<front>'));
      }
      $uri = 'internal:' . $uri;
    }

    // @see \Drupal\link\Plugin\Field\FieldWidget\LinkWidget::validateUriElement()
    if (
      parse_url($uri, PHP_URL_SCHEME) === 'internal'
      && !in_array($form['privacy_uri']['#value'][0], [
        '/',
        '?',
        '#',
      ], TRUE)
      && substr($form['privacy_uri']['#value'], 0, strlen('<front>')) !== '<front>'
    ) {
      $form_state->setError($form['privacy_uri'], t('The specified target is invalid. Manually entered paths should start with one of the following characters: / ? #'));
      return;
    }

    try {
      $url = Url::fromUri($uri);
      $url->toString(TRUE);
    }
    catch (\Exception $exception) {
      // Mark the url as invalid if any kind of exception is being thrown by
      // the Url class.
      $url = FALSE;
    }
    if ($url === FALSE || ($url->isExternal() && !in_array(parse_url($url->getUri(), PHP_URL_SCHEME), UrlHelper::getAllowedProtocols()))) {
      $form_state->setError($form['privacy_uri'], t('The path %uri is invalid.', ['%uri' => $uri]));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config(OeNewsroomNewsletter::CONFIG_NAME)
      ->set('privacy_uri', $form_state->getValue('privacy_uri'))
      ->save();
  }

}
