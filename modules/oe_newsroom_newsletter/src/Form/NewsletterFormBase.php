<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Render\PlainTextOutput;
use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\AnnounceCommand;
use Drupal\Core\Ajax\RemoveCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\NewsroomNewsletter;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Base form for subscription and unsubscription operations.
 *
 * @internal This class depends on the client that will be later moved to a
 *   dedicated library. This class will be refactored and this will break any
 *   dependencies on it.
 */
abstract class NewsletterFormBase extends FormBase {

  use AutowireTrait;

  public function __construct(
    protected readonly NewsroomClientInterface $newsroomClient,
    protected readonly AccountProxyInterface $accountProxy,
    MessengerInterface $messenger,
    #[Autowire('logger.channel.oe_newsroom_newsletter')]
    protected readonly LoggerInterface $logger,
  ) {
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $distribution_lists = []): array {
    $form['#id'] = Html::getUniqueId($this->getFormId());
    // Html::getUniqueId() randomises IDs during AJAX requests, so the rebuilt
    // form cannot know the ID rendered in the page. Round-trip the original.
    $form['newsroom_form_unique_id'] = [
      '#type' => 'hidden',
      '#default_value' => $form['#id'],
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#default_value' => $this->accountProxy->isAnonymous() ? '' : $this->accountProxy->getEmail(),
      '#required' => TRUE,
    ];
    if (count($distribution_lists) > 1) {
      $options = array_column($distribution_lists, 'name', 'sv_id');
      $form['distribution_lists'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Newsletters'),
        '#description' => $this->getDistributionListsFieldDescription(),
        '#options' => $options,
        '#required' => TRUE,
      ];
    }
    else {
      $id = $distribution_lists[0]['sv_id'];
      $form['distribution_lists'] = [
        '#type' => 'value',
        '#value' => $id,
      ];
    }

    return $form;
  }

  /**
   * Ajax callback to update the subscription form after it is submitted.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   An ajax response object.
   */
  public function submitFormCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->getErrors()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new ReplaceCommand(NULL, $form));
    }
    else {
      // Rendering status_messages consumes the messages, so collect them now.
      $announcement = [];
      foreach ($this->messenger->all() as $type_messages) {
        foreach ($type_messages as $message) {
          $announcement[] = PlainTextOutput::renderFromHtml((string) $message);
        }
      }
      // The block title is outside the form, so it is not replaced below.
      $title_class = NewsroomNewsletter::getBlockTitleClass($form_state->getValue('newsroom_form_unique_id'));
      $response->addCommand(new RemoveCommand('.' . $title_class));
      $messages = ['#type' => 'status_messages'];
      $response->addCommand(new ReplaceCommand(NULL, $messages));
      // Screen readers do not reliably announce AJAX-inserted messages.
      if ($announcement) {
        $response->addCommand(new AnnounceCommand(implode(' ', $announcement)));
      }
    }

    return $response;
  }

  /**
   * Returns the description to show under the description list field.
   *
   * @return \Drupal\Core\StringTranslation\TranslatableMarkup
   *   The field description translatable string.
   */
  abstract protected function getDistributionListsFieldDescription(): TranslatableMarkup;

}
