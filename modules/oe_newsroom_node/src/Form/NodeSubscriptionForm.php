<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\MessageCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom\Endpoint\NodeSubscriptionEndpoints;
use Drupal\oe_newsroom\Value\EmailAction\SendCustomizedEmail;
use Drupal\oe_newsroom\Value\NotificationFrequency;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Node subscription form.
 */
class NodeSubscriptionForm extends FormBase {

  use AutowireTrait;

  /**
   * The form ID.
   */
  public const FORM_ID = 'oe_newsroom_node_subscription_form';

  public function __construct(
    protected readonly NodeSubscriptionEndpoints $nodeSubscriptionEndpoints,
    protected readonly AccountProxyInterface $accountProxy,
    MessengerInterface $messenger,
    #[Autowire('logger.channel.oe_newsroom_node')]
    protected readonly LoggerInterface $logger,
  ) {
    $this->setMessenger($messenger);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return self::FORM_ID;
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    // Store the node id to use on submit.
    $form_state->set('node_id', (int) $node->id());

    $form['#id'] = Html::getUniqueId($this->getFormId());

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#default_value' => $this->accountProxy->isAnonymous() ? '' : $this->accountProxy->getEmail(),
      '#required' => TRUE,
    ];

    $privacy_url = (string) $this->config('oe_newsroom_node.settings')->get('privacy_url');
    $form['agree_privacy_statement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('By checking this box, I confirm that I want to register for this service, and I agree with the @privacy_link', [
        '@privacy_link' => Link::fromTextAndUrl(
          $this->t('privacy statement'),
          Url::fromUri($privacy_url, [
            'attributes' => ['class' => ['oe-newsroom__privacy-url']],
          ]),
        )->toString(),
      ]),
      '#element_validate' => ['::validatePrivacyElement'],
    ];

    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Subscribe'),
        '#ajax' => [
          'callback' => '::submitFormCallback',
          'wrapper' => $form['#id'],
        ],
      ],
    ];

    return $form;
  }

  /**
   * Validate callback for the privacy element.
   *
   * @param array $element
   *   The privacy-statement form element.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   * @param array $form
   *   The complete subscription form.
   */
  public function validatePrivacyElement(array $element, FormStateInterface $form_state, array $form): void {
    if (empty($element['#value'])) {
      $form_state->setError($form['agree_privacy_statement'], $this->t('You must agree with the privacy statement.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $email = $form_state->getValue('email');
    $node_id = $form_state->get('node_id');

    // The confirm link in the verification email redirects here, back to the
    // node page, with a query flag so a confirmation message can be shown.
    $redirect_url = Url::fromRoute('entity.node.canonical', ['node' => $node_id], [
      'query' => [
        'newsroom_node_subscribed' => 1,
      ],
      'absolute' => TRUE,
    ])->toString();

    // @todo Add event/hook to allow to set different parameters.
    try {
      $this->nodeSubscriptionEndpoints->subscribe(
        $node_id,
        $email,
        NotificationFrequency::OnPublication,
        // Verification email ("hard opt-in"). The confirm button leads back to
        // the node page.
        new SendCustomizedEmail(
          subject: 'Confirm your subscription',
          redirect: $redirect_url,
        ),
        // Success email, sent after the subscription is confirmed.
        new SendCustomizedEmail(
          subject: 'You have been subscribed',
        ),
      );

      $this->messenger->addStatus($this->t('A confirmation email has been sent to your address. Please click the link in the email to confirm your subscription.'));
    }
    catch (\Exception $e) {
      $this->messenger->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      // Rebuild the form so the modal stays open and the user can retry.
      $form_state->setRebuild();
      $this->logger->error('%type thrown while subscribing email to node %node_id: @message in %function (line %line of %file).', [
        '%node_id' => $node_id,
      ] + Error::decodeException($e));
    }
  }

  /**
   * Ajax callback for the submit button.
   *
   * @param array $form
   *   The subscription form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current form state.
   *
   * @return \Drupal\Core\Ajax\AjaxResponse
   *   The Ajax commands that update or close the modal form.
   */
  public function submitFormCallback(array &$form, FormStateInterface $form_state): AjaxResponse {
    $response = new AjaxResponse();

    if ($form_state->getErrors() || $form_state->isRebuilding()) {
      unset($form['#prefix'], $form['#suffix']);
      $form['status_messages'] = [
        '#type' => 'status_messages',
        '#weight' => -10,
      ];
      $response->addCommand(new ReplaceCommand(NULL, $form));
      return $response;
    }

    $response->addCommand(new CloseModalDialogCommand());
    $response->addCommand(new MessageCommand(
      $this->t('A confirmation email has been sent to your address. Please click the link in the email to confirm your subscription.'),
      NULL,
      ['type' => 'status'],
    ));
    return $response;
  }

}
