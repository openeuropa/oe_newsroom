<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom\Domain\NodeSubscriptionService;
use Drupal\oe_newsroom\Exception\Domain\OperationError;
use Drupal\oe_newsroom\ExceptionLogger;
use Drupal\oe_newsroom_newsletter\NewsroomNewsletter;

/**
 * Subscribe form.
 *
 * @internal This class depends on the client that will be later moved to a
 *   dedicated library. This class will be refactored and this will break any
 *   dependencies on it.
 */
class NodeSubscribeForm extends FormBase {

  use AutowireTrait;

  /**
   * Message to show on successful subscription.
   *
   * This is initialized in ->buildForm().
   */
  protected string $successMessage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected NodeSubscriptionService $nodeSubscriptionService,
    protected LanguageManagerInterface $languageManager,
    protected ExceptionLogger $exceptionLogger,
  ) {}

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oe_newsroom_newsletter_node_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, ?NodeInterface $node = NULL, string $intro_text = '', string $success_message = ''): array {
    if ($node === NULL) {
      return [];
    }
    assert($node instanceof NodeInterface);

    try {
      $node_is_known = $this->nodeSubscriptionService->nodeAllowsSubscribing($node);
    }
    catch (OperationError $e) {
      // Unable to determine if the node is known.
      $this->exceptionLogger->logException($e, 'An error prevents the form from being shown.');
      // @todo Determine the best place for this output.
      //   Should the user see this at all?
      $this->messenger()->addError($this->t('The subscription functionality is currently not available.'));
      return [];
    }

    if (!$node_is_known) {
      // The node is not available for subscriptions.
      return [];
    }

    $this->successMessage = $success_message;
    $form_state->set('node_id', $node->id());
    $form['#id'] = Html::getUniqueId($this->getFormId());

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
      '#default_value' => $this->currentUser()->isAnonymous() ? '' : $this->currentUser()->getEmail(),
      '#required' => TRUE,
    ];
    $options['attributes']['class'][] = 'oe-newsroom__privacy-url';
    $form['agree_privacy_statement'] = [
      '#type' => 'checkbox',
      // @todo Confirm if it's the correct way of translating text with a link.
      '#title' => $this->t('By checking this box, I confirm that I want to register for this service, and I agree with the @privacy_link', [
        '@privacy_link' => Link::fromTextAndUrl(
          $this->t('privacy statement'),
          Url::fromUri($this->getPrivacyUri(), $options),
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

    $cache = new BubbleableMetadata();

    $cache->addCacheableDependency($node);
    $cache->applyTo($form);

    return $form;
  }

  /**
   * Validate callback for the privacy element.
   *
   * This allows to show a custom message instead of the standard
   * "field is required" one.
   */
  public function validatePrivacyElement($element, FormStateInterface $form_state, $form): void {
    if (empty($element['#value'])) {
      $form_state->setError($form['agree_privacy_statement'], $this->t('You must agree with the privacy statement.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $values = $form_state->getValues();
    $node_id = $form_state->get('node_id');

    try {
      $this->nodeSubscriptionService->subscribe((int) $node_id, $values['email']);
    }
    // @todo Distinguish different failure scenarios.
    catch (OperationError $e) {
      $this->exceptionLogger->logException($e, 'Failed to node subscribe submit.');
      $this->messenger()->addWarning($this->t(
        'An error occured processing the subscription request, please try again later. If the error persists, contact the site owner.',
      ));
      return;
    }

    $success_message = $this->successMessage ?: $this->t('You have been successfully subscribed.');
    $this->messenger()->addStatus($success_message);
  }

  /**
   * Gets the privacy URI.
   *
   * @return string
   *   The privacy URI.
   */
  protected function getPrivacyUri(): string {
    $uri = $this->config(NewsroomNewsletter::CONFIG_NAME)->get('privacy_uri');
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
