<?php

declare(strict_types = 1);

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
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    // The language_select field is buggy, it doesn't give back empty value.
    $user_input = $form_state->getUserInput();

    $this->config(OeNewsroom::OE_NEWSLETTER_CONFIG_VAR_NAME)
      ->set('universe', $form_state->getValue('universe'))
      ->set('app', $form_state->getValue('app'))
      ->set('hash_method', $form_state->getValue('hash_method'))
      ->set('normalized', $form_state->getValue('normalized'))
      ->save();
  }

}
