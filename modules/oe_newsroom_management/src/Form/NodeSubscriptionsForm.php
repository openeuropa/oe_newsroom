<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_management\Form;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Utility\Error;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClient;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\Exception\ClientException;

/**
 * Handles different request for node notifications.
 */
final class NodeSubscriptionsForm extends FormBase {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected NewsroomClientInterface $newsroomClient,
    protected EntityTypeManagerInterface $entityTypeManager,
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

    $response = $this->newsroomClient->subscriptions($email);
    if (!isset($response[0]['subscribedNotificationTopicType'])) {
      return $form;
    }

    // Get user frequency and use if there is.
    // All node notifications share the same frequency.
    $frequency = 2101;
    if (isset($response[0]['frequency'])) {
      $frequency = match($response[0]["frequency"]) {
        'On Publication' => 2101,
        'Daily' => 2102,
        'Weekly' => 2103,
      };
    }

    $form['frequency'] = [
      '#type' => 'select',
      '#title' => $this->t('Frequency to receive notifications for this site.'),
      '#options' => [
        2101 => 'On publication',
        2102 => 'Daily',
        2103 => 'Weekly',
      ],
      '#default_value' => $frequency,
      '#weight' => 0,
    ];

    $node_storage = $this->entityTypeManager->getStorage('node');

    $subscriptions = $response[0]['subscribedNotificationTopicType'];
    // @todo Here we should filter by the configured app, universe and sv_id.
    foreach ($subscriptions as $subscription) {
      $node_id = $subscription['externalId'];
      $node = $node_storage->load($node_id);

      // @todo What happens if a node is deleted here but not it's corresponding
      // notification.
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
    $frequency = $form_state->getValue('frequency');
    $nid = $form_state->get('nid');
    $email = $form_state->get('email');

    try {
      $this->newsroomClient->nodeNotificationSubscribe($nid, $email, (int) $frequency);
      $this->messenger()->addStatus($this->t('Frequency updated.'));
    }
    catch (ClientException $e) {
      $this->messenger()->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->logger('oe_newsroom_newsletter')->error('%type thrown while updating frequency for email %email to the node with ID %node_id: @message and frequency %frequency in %function (line %line of %file).', [
        '%email' => $email,
        '%node_id' => $nid,
        '%frequency' => $frequency,
      ] + Error::decodeException($e));
    }
  }

  /**
   * {@inheritdoc}
   */
  public static function deleteSubmit(array $form, FormStateInterface $form_state): void {
    $triggering_element = $form_state->getTriggeringElement();
    $test = array_slice($triggering_element['#array_parents'], 0, -1);
    $row = NestedArray::getValue($form, $test);
    $node_id = $row['#node_id'];
    $newsroomClient = NewsroomClient::create(\Drupal::getContainer());
    $email = $form_state->get('email');

    try {
      $newsroomClient->nodeNotificationUnsubscribe(
        node_id: $node_id,
        email: $email,
        request_authentication: FALSE,
      );
      \Drupal::messenger()->addStatus('Subscription succesfully deleted.');
    }
    catch (ClientException $e) {
      \Drupal::messenger()->addError(t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      \Drupal::logger('oe_newsroom_newsletter')->error('%type thrown while unsubscribing email %email to the node with ID %node_id: @message in %function (line %line of %file).', [
        '%email' => $email,
        '%node_id' => $node_id,
      ] + Error::decodeException($e));
    }
  }

}
