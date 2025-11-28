<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_management\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Mail\MailManagerInterface;

/**
 * Provides a oe_newsroom_management form.
 */
final class RequestAccessForm extends FormBase {

  use AutowireTrait;
  use DependencySerializationTrait;

  public function __construct(
    protected MailManagerInterface $mailManager,
    protected LanguageManagerInterface $languageManager,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oe_newsroom_management_request_access';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#required' => TRUE,
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Submit'),
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $module = 'oe_newsroom_management';
    $key = 'request_access_mail';
    $to = $form_state->getValue('email');
    $langcode = $this->languageManager->getDefaultLanguage()->getId();

    $result = $this->mailManager->mail($module, $key, $to, $langcode, [
      'email' => $to,
    ]);

    if ($result['result'] !== TRUE) {
      $this->logger('oe_newsroom_management')->error('There was a problem sending the email.');
    }

    $this->messenger()->addWarning($this->t('An email has been sent to your email address.'));
  }

}
