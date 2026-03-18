<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\oe_newsroom\Domain\NewsletterSubscribeService;
use Drupal\oe_newsroom\Exception\Domain\OperationFailure;
use Drupal\oe_newsroom\ExceptionLogger;

/**
 * Unsubscribe form.
 *
 * @internal This class depends on the client that will be later moved to a
 *   dedicated library. This class will be refactored and this will break any
 *   dependencies on it.
 */
class UnsubscribeForm extends NewsletterFormBase {

  public function __construct(
    protected NewsletterSubscribeService $newsletterSubscribeService,
    AccountProxyInterface $accountProxy,
    MessengerInterface $messenger,
    LoggerChannelFactoryInterface $logger,
    protected ExceptionLogger $exceptionLogger,
  ) {
    parent::__construct($accountProxy, $messenger, $logger);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oe_newsroom_newsletter_unsubscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $distribution_lists = []): array {
    $form = parent::buildForm($form, $form_state, $distribution_lists);

    $form['#id'] = Html::getUniqueId($this->getFormId());

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Unsubscribe'),
        '#ajax' => [
          'callback' => '::submitFormCallback',
          'wrapper' => $form['#id'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    // Get form values.
    $values = $form_state->getValues();

    $distribution_lists = is_array($values['distribution_lists']) ? array_keys(array_filter($values['distribution_lists'])) : [$values['distribution_lists']];

    try {
      // Let's call the unsubscription service.
      $this->newsletterSubscribeService->unsubscribe($values['email'], $distribution_lists);
      $this->messenger->addStatus($this->t('Successfully unsubscribed!'));
    }
    catch (OperationFailure $e) {
      $this->exceptionLogger->logException($e, 'Failed to unsubscribe in form submit.');
      $this->messenger->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  protected function getDistributionListsFieldDescription(): TranslatableMarkup {
    return $this->t('Please select the newsletter lists you want to unsubscribe from.');
  }

}
