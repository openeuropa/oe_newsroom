<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\oe_newsroom\Newsroom;

/**
 * Newsroom settings form.
 */
class NewsroomSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      Newsroom::CONFIG_NAME,
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
    $config = $this->config(Newsroom::CONFIG_NAME);

    $form['universe'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Universe acronym'),
      '#maxlength' => 64,
      '#default_value' => $config->get('universe'),
      '#required' => TRUE,
    ];
    $form['app_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('App ID'),
      '#description' => $this->t('App short name'),
      '#maxlength' => 64,
      '#default_value' => $config->get('app_id'),
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
    $form['normalised'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Normalise before hashing'),
      '#description' => $this->t('Newsroom has a normalised setting which indicates if the hashing was done on a normalised data (lowercase)'),
      '#default_value' => $config->get('normalised'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config(Newsroom::CONFIG_NAME)
      ->set('universe', $form_state->getValue('universe'))
      ->set('app_id', $form_state->getValue('app_id'))
      ->set('hash_method', $form_state->getValue('hash_method'))
      ->set('normalised', $form_state->getValue('normalised'))
      ->save();
  }

}
