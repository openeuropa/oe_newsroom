<?php

namespace Drupal\oe_newsroom_newsletter\Form;

use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\oe_newsroom\Api\NewsroomMessengerInterface;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;
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
  public function __construct(NewsroomMessengerInterface $newsroomMessenger, LanguageManagerInterface $languageManager) {
    $this->newsroomMessenger = $newsroomMessenger;
    $this->languageManager = $languageManager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_newsroom.messenger'),
      $container->get('language_manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'subscribe_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $currentUser = $this->currentUser();
    $config = $this->config(OeNewsroomNewsletter::OE_NEWSLETTER_CONFIG_VAR_NAME);

    // Choose the proper language according to the user setting or interface
    // settings.
    $selected_language = $ui_language = $this->languageManager->getCurrentLanguage()->getId();
    if (!$currentUser->isAnonymous() && $currentUser->getPreferredLangcode(FALSE) !== '') {
      $selected_language = $currentUser->getPreferredLangcode(FALSE);
    }

    $path = str_replace('[lang_code]', str_replace('pt-pt', 'pt', $ui_language), $config->get('privacy_uri'));
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
    $options = array_intersect_key($options, array_flip($config->get('newsletters_language')));
    if (array_key_exists($selected_language, $options)) {
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
      '#title' => '<span>' . $this->t('By checking this box, I confirm that I want to register for this service, and I agree with the @privacy_link', ['@privacy_link' => Link::fromTextAndUrl($this->t('privacy statement'), $privacy_uri)->toString()]) . '</span>',
      '#weight' => '0',
      '#required' => TRUE,
    ];
    $form['subscribe'] = [
      '#type' => 'submit',
      '#value' => $this->t('Subscribe'),
      '#weight' => '0',
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

    // Let's call the subscription service.
    $response = $this->newsroomMessenger->subscribe($values['email'], $distribution_list, [], $values['newsletters_language']);
    // Set response (if there is) into form state, if somebody need it.
    if (is_array($response)) {
      $form_state->set('subscription', $response);

      // Set the correct message here.
      $this->subscriptionMessage($response);
    }
  }

  /**
   * {@inheritDoc}
   */
  public function subscriptionMessage(array $subscription): void {
    $config = $this->config(OeNewsroomNewsletter::OE_NEWSLETTER_CONFIG_VAR_NAME);

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

}
