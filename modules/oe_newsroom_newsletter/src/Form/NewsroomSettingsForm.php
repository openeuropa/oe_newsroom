<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\multivalue_form_element\Element\MultiValue;
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

    $form['intro_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introduction text'),
      '#description' => $this->t('Text which will show on top of the page'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $config->get('intro_text'),
      '#required' => TRUE,
    ];
    $form['success_subscription_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message in case of successful subscription'),
      '#description' => $this->t('Text which will shown if the user successfully subscribed to the newsletters, if not provided the newsrooms API message will be used.'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $config->get('success_subscription_text'),
    ];
    $form['already_registered_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Message in case if user is already registered'),
      '#description' => $this->t('Text which will shown if the user is already subscribed to the newsletters when he tries to subscribe, if not provided the newsrooms API message will be used.'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $config->get('already_registered_text'),
    ];
    $form['privacy_uri'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Privacy uri'),
      '#description' => $this->t("Provide here a relative or absolute url for the privacy page.<br>
       If it's an internal relative page use like: / for homepage, /node/2 for node page, /contact for aliases.<br>
       If it's an external page use like: https://ec.europa.eu or http://ec.europa.eu .
       Use [lang_code] as a language token. It will be replaced both in internal and external URLs. If you use internal which is not an alias URL, then it's not required to use the token to have language support.
       Internal absolute url is not suggested, however it's possible like the external page usage."),
      '#maxlength' => 255,
      '#size' => 64,
      '#default_value' => $config->get('privacy_uri'),
      '#required' => TRUE,
    ];
    $form['link_classes'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Link class'),
      '#description' => $this->t("Custom classes for the privacy link."),
      '#default_value' => $config->get('link_classes'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    if (!empty($form_state->getValue('newsletters_language')) && !in_array($form_state->getValue('newsletters_language_default'), $form_state->getValue('newsletters_language'))) {
      $form_state->setError($form['newsletters_language_default'], $this->t('The default language should be part of the possible newsletter languages.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config(OeNewsroomNewsletter::CONFIG_NAME)
      ->set('intro_text', $form_state->getValue('intro_text'))
      ->set('success_subscription_text', $form_state->getValue('success_subscription_text'))
      ->set('already_registered_text', $form_state->getValue('already_registered_text'))
      ->set('privacy_uri', $form_state->getValue('privacy_uri'))
      ->set('link_classes', $form_state->getValue('link_classes'))
      ->save();
  }

}
