<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\oe_newsroom\Newsroom;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClient;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\Exception\ClientException;
use Drupal\oe_newsroom_newsletter\NewsroomNewsletter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Subscribe form.
 *
 * @internal This class depends on the client that will be later moved to a
 *   dedicated library. This class will be refactored and this will break any
 *   dependencies on it.
 */
class SubscribeForm extends NewsletterFormBase {

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
   * {@inheritdoc}
   */
  public function __construct(NewsroomClientInterface $newsroomClient, AccountProxyInterface $accountProxy, MessengerInterface $messenger, LoggerChannelFactoryInterface $logger, LanguageManagerInterface $languageManager) {
    parent::__construct($newsroomClient, $accountProxy, $messenger, $logger);
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      NewsroomClient::create($container),
      $container->get('current_user'),
      $container->get('messenger'),
      $container->get('logger.factory'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'oe_newsroom_newsletter_subscribe_form';
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function buildForm(array $form, FormStateInterface $form_state, array $distribution_lists = [], array $newsletters_language = [], string $newsletters_language_default = '', string $intro_text = '', string $successful_message = ''): array {
    $this->successfulMessage = $successful_message;

    // Choose the proper language according to the user setting or interface
    // settings.
    $selected_language = $ui_language = $this->languageManager->getCurrentLanguage()->getId();
    if (!$this->accountProxy->isAnonymous() && $this->accountProxy->getPreferredLangcode(FALSE) !== '') {
      $selected_language = $this->accountProxy->getPreferredLangcode(FALSE);
    }

    $form['#id'] = Html::getUniqueId($this->getFormId());

    // Start building up form.
    $form['intro_text'] = [
      '#type' => 'container',
    ];
    $form['intro_text']['content'] = [
      '#plain_text' => $intro_text,
    ];

    $form = parent::buildForm($form, $form_state, $distribution_lists);

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

    $options['attributes']['class'][] = 'oe-newsroom__privacy-url';
    $form['agree_privacy_statement'] = [
      '#type' => 'checkbox',
      // @todo Confirm if it's the correct way of translating text with a link.
      '#title' => $this->t('By checking this box, I confirm that I want to register for this service, and I agree with the @privacy_link', [
        '@privacy_link' => Link::fromTextAndUrl(
          $this->t('privacy statement'),
          Url::fromUri($this->getPrivacyUri($ui_language), $options),
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
    // Get form values.
    $values = $form_state->getValues();

    $distribution_lists = is_array($values['distribution_lists']) ? array_keys(array_filter($values['distribution_lists'])) : [$values['distribution_lists']];

    try {
      // Let's call the subscription service.
      $response = $this->newsroomClient->subscribe($values['email'], $distribution_lists, [], $values['newsletters_language']);
      // Set response (if there is) into form state, if somebody need it.
      $form_state->set('subscription', $response);

      $this->messenger->addStatus($this->successfulMessage ?: $response['feedbackMessage'] ?: $this->t('You have been successfully subscribed.'));
    }
    catch (ClientException $e) {
      $this->messenger->addError($this->t('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.'));
      $this->logger->get('oe_newsroom_newsletter')->error('Exception thrown with code %code while subscribing email %email to the newsletter(s) with ID(s) %sv_ids and universe %universe: %exception', [
        '%code' => $e->getCode(),
        '%email' => $values['email'],
        '%universe' => $this->config(Newsroom::CONFIG_NAME)->get('universe'),
        '%sv_ids' => implode(',', $distribution_lists),
        '%exception' => $e->getMessage(),
      ]);
    }
  }

  /**
   * Gets the privacy URI.
   *
   * @param string $language
   *   The language code.
   *
   * @return string
   *   The privacy URI.
   */
  protected function getPrivacyUri(string $language): string {
    $uri = $this->config(NewsroomNewsletter::CONFIG_NAME)->get('privacy_uri');
    if (parse_url($uri, PHP_URL_SCHEME) === NULL) {
      if (strpos($uri, '<front>') === 0) {
        $uri = '/' . substr($uri, strlen('<front>'));
      }
      $uri = 'internal:' . $uri;
    }
    // @todo Adapt to the common OE approach for pt-pt.
    return str_replace('[lang_code]', str_replace('pt-pt', 'pt', $language), $uri);
  }

  /**
   * {@inheritdoc}
   */
  protected function getDistributionListsFieldDescription(): TranslatableMarkup {
    return $this->t('Please select the newsletter lists you want to subscribe to.');
  }

}
