<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Form;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
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
 * A form to allow a visitor to subscribe to a node via Newsroom.
 */
class NodeSubscribeForm extends FormBase {

  use AutowireTrait;

  /**
   * The form ID.
   */
  public const FORM_ID = 'oe_newsroom_node_subscribe_form';

  public function __construct(
    protected readonly NodeSubscriptionEndpoints $nodeSubscriptionEndpoints,
    protected readonly AccountProxyInterface $accountProxy,
    MessengerInterface $messenger,
    #[Autowire('logger.channel.oe_newsroom_node')]
    protected readonly LoggerInterface $logger,
    protected LanguageManagerInterface $languageManager,
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
   * A page title callback.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node object from url.
   *
   * @return \Drupal\Component\Render\MarkupInterface
   *   The page title.
   */
  public function title(NodeInterface $node): MarkupInterface {
    return t('Subscribe to @title', ['@title' => $node->label()]);
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL): array {
    if ($node === NULL) {
      return [];
    }
    // Store the node id to use on submit.
    $form_state->set('node_id', (int) $node->id());

    $intro_text = $this->t(
      'By subscribing to the updates of @title, you will be notified whenever changes are made.',
      ['@title' => $node->getTitle()],
    );

    // Start building up form.
    $form['intro_text'] = [
      '#type' => 'container',
    ];
    $form['intro_text']['content'] = [
      '#type' => 'inline_template',
      '#template' => '{{ value|nl2br }}',
      '#context' => ['value' => $intro_text],
    ];
    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#default_value' => $this->accountProxy->isAnonymous() ? '' : $this->accountProxy->getEmail(),
      '#required' => TRUE,
    ];
    $form['agree_privacy_statement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('By checking this box, I confirm that I want to register for this service, and I agree with the @privacy_link', [
        '@privacy_link' => Link::fromTextAndUrl(
          $this->t('privacy statement'),
          Url::fromUri($this->getPrivacyUrl(), [
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

    // The confirmation link redirects through a controller that handles the
    // result and then sends the user to the node page.
    $redirect_url = Url::fromRoute('oe_newsroom_node.subscribe_verify', ['node' => $node_id], [
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

      $this->messenger->addStatus($this->t('If you are not already subscribed to this content, a confirmation email will be sent to the email address provided. Please click the link in the email to confirm your subscription.'));
      $form_state->setRedirect('entity.node.canonical', ['node' => $node_id]);
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
   * Gets the privacy URI.
   *
   * @return string
   *   The privacy URI.
   */
  protected function getPrivacyUrl(): string {
    $uri = $this->config('oe_newsroom_node.settings')->get('privacy_url');
    if (parse_url($uri, PHP_URL_SCHEME) === NULL) {
      if (str_starts_with($uri, '<front>')) {
        $uri = '/' . substr($uri, strlen('<front>'));
      }
      $uri = 'internal:' . $uri;
    }
    $language = $this->languageManager->getCurrentLanguage()->getId();
    // @todo Adapt to the common OE approach for pt-pt.
    return str_replace('[lang_code]', str_replace('pt-pt', 'pt', $language), $uri);
  }

}
