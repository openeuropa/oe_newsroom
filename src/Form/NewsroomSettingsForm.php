<?php

namespace Drupal\oe_newsroom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_newsroom\OeNewsroom;

/**
 * Newsroom Settings Form.
 */
class NewsroomSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      OeNewsroom::OE_NEWSLETTER_CONFIG_VAR_NAME,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'newsroom_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(OeNewsroom::OE_NEWSLETTER_CONFIG_VAR_NAME);

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
    $form['newsletters_language'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Select the selectable languages for newsletter'),
      '#description' => $this->t('Empty = all possible languages'),
      '#default_value' => $config->get('newsletters_language'),
      '#multiple' => TRUE,
    ];
    $form['newsletters_language_default'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Select the default language for newsletter'),
      '#description' => $this->t("This language will be selected if the user's preferred language is not selectable."),
      '#default_value' => $config->get('newsletters_language_default'),
    ];
    $form['sv_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Sv IDs'),
      '#description' => $this->t('ID(s) of the newsletter/distribution list'),
      '#maxlength' => 128,
      '#size' => 64,
      '#default_value' => $config->get('sv_id'),
      '#required' => TRUE,
    ];
    $form['hash_method'] = [
      '#type' => 'select',
      '#title' => $this->t('Hash method'),
      '#description' => $this->t('Hashing algorithm'),
      '#options' => [
        'sha256' => $this->t('SHA-256'),
        'md5' => $this->t('MD5'),
      ],
      '#size' => 1,
      '#default_value' => $config->get('hash_method'),
    ];
    $form['normalized'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Is normalized?'),
      '#description' => $this->t('Newsroom has a normalized setting which determinates if the hashing was done on a normalized data (lowercased)'),
      '#default_value' => $config->get('normalized'),
    ];
    $form['universe'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Universe Acronym'),
      '#description' => $this->t('Universe Acronym which is usually the site&#039;s name acronym'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('universe'),
      '#required' => TRUE,
    ];
    $form['app'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App'),
      '#description' => $this->t('App short name'),
      '#maxlength' => 64,
      '#size' => 64,
      '#default_value' => $config->get('app'),
      '#required' => TRUE,
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
    // The language_select field is buggy, it doesn't give back empty value.
    $user_input = $form_state->getUserInput();

    $this->config(OeNewsroom::OE_NEWSLETTER_CONFIG_VAR_NAME)
      ->set('intro_text', $form_state->getValue('intro_text'))
      ->set('success_subscription_text', $form_state->getValue('success_subscription_text'))
      ->set('already_registered_text', $form_state->getValue('already_registered_text'))
      ->set('privacy_uri', $form_state->getValue('privacy_uri'))
      ->set('link_classes', $form_state->getValue('link_classes'))
      ->set('newsletters_language', $user_input['newsletters_language'] ? $form_state->getValue('newsletters_language') : [])
      ->set('newsletters_language_default', $form_state->getValue('newsletters_language_default'))
      ->set('sv_id', $form_state->getValue('sv_id'))
      ->set('hash_method', $form_state->getValue('hash_method'))
      ->set('normalized', $form_state->getValue('normalized'))
      ->set('universe', $form_state->getValue('universe'))
      ->set('app', $form_state->getValue('app'))
      ->save();
  }

}
