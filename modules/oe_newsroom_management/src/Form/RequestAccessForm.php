<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_management\Form;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\oe_newsroom\Endpoint\ExternalAuthEndpoints;
use Drupal\oe_newsroom\Exception\Api\ApiException;
use Drupal\oe_newsroom_management\TokenManager;

/**
 * Provides a oe_newsroom_management form.
 */
final class RequestAccessForm extends FormBase {

  use AutowireTrait;
  use DependencySerializationTrait;

  public function __construct(
    protected TokenManager $tokenManager,
    protected ExternalAuthEndpoints $externalAuthEndpoints,
    TranslationInterface $translation,
    MessengerInterface $messenger,
  ) {
    $this->setStringTranslation($translation);
    $this->setMessenger($messenger);
  }

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
    $email = $form_state->getValue('email');
    $token = $this->tokenManager->get($email);
    $url = Url::fromRoute(
      'oe_newsroom_management.node_subscriptions_management_anonymous',
      [
        'email' => $email,
        'token' => $token,
      ],
      [
        'absolute' => TRUE,
      ],
    );
    $text = $this->t('Manage subscriptions');
    try {
      $this->externalAuthEndpoints->tokenEmail($email, $url->toString(), (string) $text);
    }
    catch (ApiException $e) {
      Error::logException(
        $this->getLogger('oe_newsroom_management'),
        $e,
        "Failed external auth request for '@email'.<br>" .
        Error::DEFAULT_ERROR_MESSAGE . '<br>' .
        '<pre>@backtrace_string</pre>',
      );
      $this->messenger->addError($this->t('We were unable to send the verification email. Please try again later, or contact the site administrator.'));
      return;
    }

    $this->messenger->addWarning($this->t('A verification email will be sent to your email address, with a link that grants access to the subscriptions management. This normally does not take longer than 5 minutes.'));
  }

}
