<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Url;
use Drupal\Core\Utility\Error;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\Exception\ClientException;
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
  protected string $successfulMessage;

  /**
   * {@inheritdoc}
   */
  public function __construct(
    protected NewsroomClientInterface $newsroomClient,
    protected LanguageManagerInterface $languageManager,
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
  public function buildForm(array $form, FormStateInterface $form_state, string $intro_text = '', string $successful_message = ''): array {
    $node = $this->getRouteMatch()->getParameter('node');
    if ($node === NULL) {
      return [];
    }

    try {
      $notifications = \Drupal::service(NewsroomClientInterface::class)->nodeNotificationGet($node->id());
    }
    catch (ClientException $e) {
      $this->messenger()->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->logger('oe_newsroom_newsletter')->error('%type thrown while getting notification for the node with ID %node_id. @message in %function (line %line of %file).', [
        '%node_id' => $node->id(),
      ] + Error::decodeException($e));
    }

    if (empty($notifications)) {
      return [];
    }

    $this->successfulMessage = $successful_message;
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

    // Get user frequency and use if there is.
    // All node notifications share the same frequency.
    $subscriptions_response = $this->newsroomClient->subscriptions($values['email']);

    $frequency = match ($subscriptions_response[0]['frequency'] ?? NULL) {
      'On Publication' => 2101,
      'Daily' => 2102,
      'Weekly' => 2103,
      // Default on publication.
      default => 2101,
    };

    try {
      // @todo Add event here to allow to change parameters.
      $subscribe_response = $this->newsroomClient->nodeNotificationSubscribe($node_id, $values['email'], $frequency);
      // Save the response (if there is) into form state just in case somebody
      // needs it.
      $form_state->set('subscription', $subscribe_response);
      // @todo Right now we prio the response message since it contains valuable
      // information, to see if there is any risk and reorder.
      $this->messenger()->addStatus($this->successfulMessage ?: $this->t('You have been successfully subscribed.'));
    }
    catch (ClientException $e) {
      $this->messenger()->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->logger('oe_newsroom_newsletter')->error('%type thrown while subscribing email %email to the node with ID %node_id: @message in %function (line %line of %file).', [
        '%email' => $values['email'],
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
  protected function getPrivacyUri(): string {
    $uri = $this->config(NewsroomNewsletter::CONFIG_NAME)->get('privacy_uri');
    if (parse_url($uri, PHP_URL_SCHEME) === NULL) {
      if (strpos($uri, '<front>') === 0) {
        $uri = '/' . substr($uri, strlen('<front>'));
      }
      $uri = 'internal:' . $uri;
    }
    $language = $this->languageManager->getCurrentLanguage()->getId();
    // @todo Adapt to the common OE approach for pt-pt.
    return str_replace('[lang_code]', str_replace('pt-pt', 'pt', $language), $uri);
  }

}
