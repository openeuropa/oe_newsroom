<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_management\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\oe_newsroom\Domain\NodeSubscriptionService;
use Drupal\oe_newsroom\Exception\Domain\OperationError;
use Drupal\oe_newsroom\Exception\Domain\OperationFailure;
use Drupal\oe_newsroom\ExceptionLogger;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * A form to manage node subscriptions.
 */
final class NodeSubscriptionsForm extends FormBase {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected EntityTypeManagerInterface $entityTypeManager,
    protected NodeSubscriptionService $nodeSubscriptionService,
    protected ExceptionLogger $exceptionLogger,
    #[Autowire(service: 'logger.channel.oe_newsroom')]
    protected LoggerInterface $logger,
    MessengerInterface $messenger,
    TranslationInterface $translation,
  ) {
    $this->setStringTranslation($translation);
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_newsroom_management_node_subscriptions';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, string $email = ''): array {
    $form = [];
    if ($email === '') {
      return $form;
    }

    $form_state->set('email', $email);
    $form['subcriptions'] = [
      '#type' => 'table',
      '#header' => [
        $this->t('Node ID'),
        $this->t('Title'),
        $this->t('Delete'),
      ],
      '#empty' => $this->t('No subscriptions found.'),
      '#rows' => [],
      '#weight' => 10,
    ];

    try {
      $frequency = $this->nodeSubscriptionService->fetchSubscriptionFrequency($email);
    }
    catch (OperationError $e) {
      $this->exceptionLogger->logException($e, 'A form cannot be shown due to an error');
      return [];
    }
    $subscribed_node_ids = $this->nodeSubscriptionService->fetchSubsribedNodeIds($email);
    if (!$subscribed_node_ids) {
      return $form;
    }

    $form['frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Frequency to receive notifications for this site.'),
      '#options' => $this->nodeSubscriptionService->getFrequencyOptions(),
      '#default_value' => $frequency->value,
      '#weight' => 0,
    ];

    $node_storage = $this->entityTypeManager->getStorage('node');

    // @todo Here we should filter by the configured app, universe and sv_id.
    foreach ($subscribed_node_ids as $node_id) {
      /** @var \Drupal\node\NodeInterface|null $node */
      $node = $node_storage->load($node_id);

      // @todo What happens if a node is deleted here but not it's corresponding
      //   notification?
      // For the moment only display existing nodes.
      if ($node === NULL) {
        continue;
      }

      $form['subcriptions'][$node_id] = [
        '#node_id' => $node_id,
        'nid' => ['#plain_text' => $node_id],
        'title' => ['#plain_text' => $node->getTitle()],
        // New route or make this a form.
        'delete' => [
          '#type' => 'submit',
          '#value' => $this->t('Delete'),
          '#submit' => ['::deleteSubmit'],
          '#name' => 'delete_subscription_' . $node_id,
          '#attributes' => [
            'class' => [
              'button--small',
              'button--danger',
              'button',
            ],
          ],
        ],
      ];
      // Save a node ID for a possible frequency update.
      $form_state->set('nid', $node_id);
    }

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#button_type' => 'primary',
        '#value' => $this->t('Save'),
      ],
      '#weight' => 20,
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Updating a node subscription frequency will update all.
    $frequency_value = $form_state->getValue('frequency');
    $frequency = NotificationFrequency::tryFrom($frequency_value);

    if (!$frequency) {
      // @todo Should this simply crash the page instead?
      $this->logger->error(sprintf('Unexpected frequency %s in form submission', var_export($frequency_value, TRUE)));
      $this->messenger()->addError($this->t('An error occured while processing your request. Nothing was updated. You may try again later. If the error persists, contact the site owner.'));
      return;
    }

    $email = $form_state->get('email');

    try {
      $this->nodeSubscriptionService->setFrequency($email, $frequency);
    }
    catch (OperationFailure $e) {
      $this->exceptionLogger->logException($e, 'Failed to update the frequency in a form submission.');
      $this->messenger()->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      return;
    }

    $this->messenger()->addStatus($this->t('The frequency was updated.'));
  }

  /**
   * Submit handler for the delete button.
   */
  public function deleteSubmit(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $test = array_slice($triggering_element['#array_parents'], 0, -1);
    $row = NestedArray::getValue($form, $test);
    $node_id = $row['#node_id'];
    $email = $form_state->get('email');

    try {
      $this->nodeSubscriptionService->unsubscribe($node_id, $email);
      \Drupal::messenger()->addStatus('Subscription succesfully deleted.');
    }
    catch (OperationFailure $e) {
      $this->exceptionLogger->logException($e, 'Failed to unsubscribe in a form submission.');
      $this->messenger->addError(t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
    }
  }

}
