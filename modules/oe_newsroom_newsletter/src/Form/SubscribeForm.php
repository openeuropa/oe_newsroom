<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Url;
use Drupal\oe_newsroom\Exception\InvalidApiConfiguration;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClient;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ServerException;
// @codingStandardsIgnoreLine
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subscribe Form.
 *
 * Arguments:
 *  - A distribution lists array
 *    ex. array(0 => array('sv_id' => 20, 'name' => 'XY Newsletter'))
 *  - A selectable list array of languages
 *    ex. array(0 => 'de', 1 => 'en')
 *  - A default language string
 *    ex. 'en'
 *  - An intro text string
 *    ex. 'This is the introduction text.'
 *  - A successful subscription message string
 *    ex. 'Subscribed.'
 */
class SubscribeForm extends NewsletterFormBase {

  /**
   * API for newsroom calls.
   *
   * @var \Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface
   */
  protected $newsroomClient;

  /**
   * Language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Successful subscription message.
   *
   * @var string
   */
  protected $successfulMessage;

  /**
   * Account proxy.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private $accountProxy;

  /**
   * {@inheritDoc}
   */
  public function __construct(NewsroomClientInterface $newsroomClient, LanguageManagerInterface $languageManager, AccountProxyInterface $accountProxy, MessengerInterface $messenger) {
    $this->newsroomClient = $newsroomClient;
    $this->languageManager = $languageManager;
    $this->accountProxy = $accountProxy;
    $this->messenger = $messenger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      NewsroomClient::create($container),
      $container->get('language_manager'),
      $container->get('current_user'),
      $container->get('messenger')
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
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $distribution_lists = [], array $newsletters_language = [], string $newsletters_language_default = '', string $intro_text = '', string $successful_message = '') {
    $config = $this->config(OeNewsroomNewsletter::CONFIG_NAME);
    $this->successfulMessage = $successful_message;

    // Choose the proper language according to the user setting or interface
    // settings.
    $selected_language = $ui_language = $this->languageManager->getCurrentLanguage()->getId();
    if (!$this->accountProxy->isAnonymous() && $this->accountProxy->getPreferredLangcode(FALSE) !== '') {
      $selected_language = $this->accountProxy->getPreferredLangcode(FALSE);
    }

    $attributes['attributes']['class'][] = 'oe-newsroom__privacy-url';

    $uri = $config->get('privacy_uri');
    if (parse_url($uri, PHP_URL_SCHEME) === NULL) {
      if (strpos($uri, '<front>') === 0) {
        $uri = '/' . substr($uri, strlen('<front>'));
      }
      $uri = 'internal:' . $uri;
    }
    // @todo Adapt to the common OE approach for pt-pt.
    $uri = str_replace('[lang_code]', str_replace('pt-pt', 'pt', $ui_language), $uri);

    $form['#id'] = Html::getUniqueId($this->getFormId());

    // Start building up form.
    $form['intro_text'] = [
      '#type' => 'container',
    ];
    $form['intro_text']['content'] = [
      '#markup' => $intro_text,
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
        '#description' => $this->t('Please select which newsletter list interests you.'),
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
    $languages = $this->languageManager->getLanguages();
    if (!empty($newsletters_language)) {
      $languages = array_intersect_key($languages, array_flip($newsletters_language));
    }
    $options = [];
    foreach ($languages as $language) {
      $options[$language->getId()] = $language->getName();
    }
    if (!array_key_exists($selected_language, $options)) {
      $selected_language = $newsletters_language_default;
    }
    if (count($options) > 1) {
      $form['newsletters_language'] = [
        '#type' => 'select',
        '#title' => $this->t('Select the language in which you want to receive the newsletters'),
        '#options' => $options,
        '#default_value' => $selected_language,
      ];
    }
    else {
      $form['newsletters_language'] = [
        '#type' => 'value',
        '#value' => $selected_language,
      ];
    }
    $form['agree_privacy_statement'] = [
      '#type' => 'checkbox',
      // @todo Confirm if it's the correct way of translating text with a link.
      '#title' => $this->t('By checking this box, I confirm that I want to register for this service, and I agree with the @privacy_link', ['@privacy_link' => Link::fromTextAndUrl($this->t('privacy statement'), Url::fromUri($uri, $attributes))->toString()]),
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
   * {@inheritdoc}
   */
  public function validatePrivacyElement($element, FormStateInterface $form_state, $form) {
    if (empty($element['#value'])) {
      $form_state->setError($form['agree_privacy_statement'], t('You must agree with the privacy statement.'));
    }
  }

  /**
   * {@inheritdoc}
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    // Get form values.
    $values = $form_state->getValues();

    $distribution_lists = is_array($values['distribution_lists']) ? array_keys(array_filter($values['distribution_lists'])) : [$values['distribution_lists']];

    try {
      // Let's call the subscription service.
      $response = $this->newsroomClient->subscribe($values['email'], $distribution_lists, [], $values['newsletters_language']);
      // Set response (if there is) into form state, if somebody need it.
      if (is_array($response)) {
        $form_state->set('subscription', $response);

        // Set the correct message here.
        $this->subscriptionMessage($response, $this->successfulMessage);
      }
    }
    catch (InvalidApiConfiguration $e) {
      $this->messenger->addError(t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->getLogger('oe_newsroom_newsletter')->error('Exception thrown while subscribing with %code code and a %message message in the %file file %line line.\n\rTrace: %trace', [
        '%code' => $e->getCode(),
        '%message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
        '%trace' => $e->getTraceAsString(),
      ]);
    }
    catch (ServerException $e) {
      $this->messenger->addError(t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->getLogger('oe_newsroom_newsletter')->error('Exception thrown while subscribing with %code code and a %message message in the %file file %line line.\n\rTrace: %trace', [
        '%code' => $e->getCode(),
        '%message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
        '%trace' => $e->getTraceAsString(),
      ]);
    }
    catch (BadResponseException $e) {
      $this->messenger->addError(t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->getLogger('oe_newsroom_newsletter')->error('Exception thrown while subscribing with %code code and a %message message in the %file file %line line.\n\rTrace: %trace', [
        '%code' => $e->getCode(),
        '%message' => $e->getMessage(),
        '%file' => $e->getFile(),
        '%line' => $e->getLine(),
        '%trace' => $e->getTraceAsString(),
      ]);
    }
  }

  /**
   * {@inheritDoc}
   */
  protected function subscriptionMessage(array $subscription, string $success_message): void {
    $config = $this->config(OeNewsroomNewsletter::CONFIG_NAME);

    // Success message should be translatable and it can be set from the
    // Newsroom Settings Form.
    $this->messenger->addStatus(empty($success_message) ? trim($subscription['feedbackMessage']) : $success_message);
  }

}
