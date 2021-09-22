<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\oe_newsroom\Exception\InvalidApiConfiguration;
use Drupal\oe_newsroom\NewsroomMessengerFactoryInterface;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subscribe Form.
 */
class SubscribeForm extends FormBase {

  /**
   * API for newsroom calls.
   *
   * @var \Drupal\oe_newsroom\Api\NewsroomMessengerInterface
   */
  protected $newsroomMessenger;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * {@inheritDoc}
   */
  public function __construct(NewsroomMessengerFactoryInterface $newsroomMessengerFactory, LanguageManagerInterface $languageManager) {
    $this->newsroomMessenger = $newsroomMessengerFactory->get();
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_newsroom.messenger_factory'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'oe_newsroom_newsletter_subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $currentUser = $this->currentUser();
    $config = $this->config(OeNewsroomNewsletter::CONFIG_NAME);

    // Choose the proper language according to the user setting or interface
    // settings.
    $selected_language = $ui_language = $this->languageManager->getCurrentLanguage()->getId();
    if (!$currentUser->isAnonymous() && $currentUser->getPreferredLangcode(FALSE) !== '') {
      $selected_language = $currentUser->getPreferredLangcode(FALSE);
    }

    $path = str_replace('[lang_code]', str_replace('pt-pt', 'pt', $ui_language), $config->get('privacy_uri'));
    if (empty($path)) {
      $this->messenger()->addWarning($this->t('Subscription form can by only used after privacy url is set.'));
      return [
        '#markup' => '',
      ];
    }

    $attributes = [];
    if (!empty($config->get('link_classes'))) {
      $attributes['attributes'] = [
        'class' => explode(' ', $config->get('link_classes')),
      ];
    }
    if (UrlHelper::isExternal($path)) {
      $privacy_uri = Url::fromUri($path, $attributes);
    }
    else {
      $privacy_uri = Url::fromUserInput($path, $attributes);
    }

    // Add wrapper for ajax.
    // @todo I think this will break if somebody puts multiple subscription form
    // to the same page... However I can't find right now in the core a proper
    // solution for this issue.
    $form['#prefix'] = '<div id="newsroom-newsletter-subscription-form">';
    $form['#suffix'] = '</div>';

    // Start building up form.
    $form['intro_text'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'edit-intro-text',
        ],
      ],
    ];
    $form['intro_text']['div_value'] = [
      '#markup' => $config->get('intro_text'),
    ];

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Your e-mail'),
      '#weight' => '0',
      '#default_value' => $currentUser->isAnonymous() ? '' : $currentUser->getEmail(),
      '#required' => TRUE,
    ];
    $distribution_lists = $config->get('distribution_list');
    $distribution_list_options = [];
    foreach ($distribution_lists as $distribution_list) {
      $distribution_list_options[$distribution_list['sv_id']] = $distribution_list['name'];
    }
    if (count($distribution_list_options) > 1) {
      $form['distribution_list'] = [
        '#type' => 'checkboxes',
        '#title' => $this->t('Newsletter lists'),
        '#description' => $this->t('Please select which newsletter list interests you.'),
        '#options' => $distribution_list_options,
        '#weight' => '0',
        '#required' => TRUE,
      ];
    }
    else {
      $id = array_keys($distribution_list_options)[0];
      $form['distribution_list'] = [
        '#type' => 'hidden',
        '#value' => $id,
        '#default_value' => $id,
      ];
    }
    $languages = $this->languageManager->getLanguages();
    $options = [];
    foreach ($languages as $language) {
      $options[$language->getId()] = $language->getName();
    }
    if (!empty($config->get('newsletters_language'))) {
      $options = array_intersect_key($options, array_flip($config->get('newsletters_language')));
    }
    if (!array_key_exists($selected_language, $options)) {
      $selected_language = $config->get('newsletters_language_default');
    }
    if (count($options) > 1) {
      $form['newsletters_language'] = [
        '#type' => 'select',
        '#title' => $this->t('Select the language of your received newsletter'),
        '#options' => $options,
        '#default_value' => $selected_language,
        '#weight' => '0',
      ];
    }
    else {
      $form['newsletters_language'] = [
        '#type' => 'hidden',
        '#value' => $selected_language,
        '#default_value' => $selected_language,
      ];
    }
    $form['agree_privacy_statement'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('By checking this box, I confirm that I want to register for this service, and I agree with the @privacy_link', ['@privacy_link' => Link::fromTextAndUrl($this->t('privacy statement'), $privacy_uri)->toString()]),
      '#weight' => '0',
      '#required' => TRUE,
    ];
    $form['actions'] = [
      '#type' => 'actions',
      'submit' => [
        '#type' => 'submit',
        '#value' => $this->t('Subscribe'),
        '#ajax' => [
          'callback' => '::submitFormCallback',
        ],
      ],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get form values.
    $values = $form_state->getValues();

    $distribution_list = is_array($values['distribution_list']) ? array_keys(array_filter($values['distribution_list'])) : [$values['distribution_list']];

    try {
      // Let's call the subscription service.
      $response = $this->newsroomMessenger->subscribe($values['email'], $distribution_list, [], $values['newsletters_language']);
      // Set response (if there is) into form state, if somebody need it.
      if (is_array($response)) {
        $form_state->set('subscription', $response);

        // Set the correct message here.
        $this->subscriptionMessage($response);
      }
    }
    catch (\InvalidArgumentException $e) {
      $this->messenger()->addError($e->getMessage());
    }
    catch (InvalidApiConfiguration $e) {
      $this->messenger()->addError($e->getMessage());
    }
    catch (ServerException $e) {
      $this->logger('oe_newsroom_newsletter')->error('An error occurred with %code code and a %message message in the %file file %line line.\n\rTrace: %trace', [
        '%code' => $e->getCode(),
        '%message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
        '%trace' => $e->getTraceAsString(),
      ]);
      $this->messenger()->addError($this->t('An error happened in the communication. If this persist, connect with the site owner.'));
    }
    catch (BadResponseException $e) {
      $this->messenger()->addError($e->getMessage());
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function subscriptionMessage(array $subscription): void {
    $config = $this->config(OeNewsroomNewsletter::CONFIG_NAME);

    if ($subscription['isNewSubscription'] === TRUE) {
      $success_message = $config->get('success_subscription_text');
      // Success message should be translatable and it can be set from the
      // Newsroom Settings Form.
      // @codingStandardsIgnoreLine
      $this->messenger()->addStatus(empty($success_message) ? trim($subscription['feedbackMessage']) : $success_message);
    }
    else {
      $already_reg_message = $config->get('already_registered_text');
      // Already registered message should be translatable and it can be set
      // from the Newsroom Settings Form.
      // @codingStandardsIgnoreLine
      $this->messenger()->addWarning(empty($already_reg_message) ? trim($subscription['feedbackMessage']) : $already_reg_message);
    }
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
      $response->addCommand(new HtmlCommand('#newsroom-newsletter-subscription-form', $form));
    }
    else {
      $messages = ['#type' => 'status_messages'];
      $response->addCommand(new HtmlCommand('#newsroom-newsletter-subscription-form', $messages));
    }

    return $response;
  }

}
