<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Block;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_newsroom\OeNewsroom;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClient;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\Form\SubscribeForm;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Newsletter subscription block.
 *
 * @Block(
 *   id = "oe_newsroom_newsletter_subscription_block",
 *   admin_label = @Translation("Newsletter subscription block"),
 *   category = @Translation("OE Newsroom Newsletter")
 * )
 *
 * @internal This class depends on the client that will be later moved to a
 *   dedicated library. This class will be refactored and this will break any
 *   dependencies on it.
 */
class NewsletterSubscriptionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Privacy Uri.
   *
   * @var array|mixed|null
   */
  protected $privacyUri;

  /**
   * The Newsroom newsletter client.
   *
   * @var \Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface
   */
  protected $newsroomClient;

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilderInterface
   */
  private $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, NewsroomClientInterface $newsroomClient, FormBuilderInterface $form_builder, ConfigFactoryInterface $configFactory) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->privacyUri = $configFactory->get(OeNewsroomNewsletter::CONFIG_NAME)->get('privacy_uri');
    $this->formBuilder = $form_builder;
    $this->newsroomClient = $newsroomClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      NewsroomClient::create($container),
      $container->get('form_builder'),
      $container->get('config.factory'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'newsletters_language' => [],
      'newsletters_language_default' => '',
      'intro_text' => '',
      'successful_subscription_message' => '',
      'distribution_lists' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    $form['newsletters_language'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Newsletter languages'),
      '#description' => $this->t('Select the languages in which the newsletter is available. Leave empty to show all languages. Only the languages currently enabled in the site are available.'),
      '#default_value' => $this->configuration['newsletters_language'],
      '#multiple' => TRUE,
    ];
    $form['newsletters_language_default'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Default newsletter language'),
      '#description' => $this->t("This language will be set as default if the user's preferred language is not available."),
      '#default_value' => $this->configuration['newsletters_language_default'],
    ];
    $form['intro_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introduction text'),
      '#description' => $this->t('Text which will show on top of the form.'),
      '#default_value' => $this->configuration['intro_text'],
    ];
    $form['successful_subscription_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Successful subscription message'),
      '#description' => $this->t('Text which will shown if the user successfully subscribed to the newsletters. Leave empty to use the message returned by the Newsroom API.'),
      '#maxlength' => 255,
      '#default_value' => $this->configuration['successful_subscription_message'],
    ];
    $form['distribution_lists'] = [
      '#type' => 'multivalue',
      '#title' => $this->t('Newsletter distribution lists'),
      '#description' => $this->t("If there's a single choice here, it will remain hidden on the (un)subscription form."),
      '#cardinality' => 5,
      '#required' => TRUE,
      'sv_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Sv IDs'),
        '#description' => $this->t('Comma-separated list of newsletter/distribution list IDs.'),
        '#maxlength' => 128,
      ],
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name of the distribution list'),
        '#description' => $this->t('This is used to help the user identify which list they want to subscribe to.'),
        '#maxlength' => 128,
      ],
      '#default_value' => $this->configuration['distribution_lists'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state): void {
    parent::blockValidate($form, $form_state);

    if (!empty($form_state->getValue('newsletters_language')) && !in_array($form_state->getValue('newsletters_language_default'), $form_state->getValue('newsletters_language'))) {
      $form_state->setError($form['newsletters_language_default'], $this->t('The default language should be part of the possible newsletter languages.'));
    }

    // Since the distribution lists field is required, no need to run validation
    // when less than two distributions exist.
    if (count($form_state->getValue('distribution_lists', [])) < 2) {
      return;
    }

    // The multivalue element rekeys the items to have consecutive deltas.
    // To set the validation, we need to access the original unprocessed deltas.
    $unprocessed_lists = NestedArray::getValue($form_state->getUserInput(), $form['distribution_lists']['#parents']);
    unset($unprocessed_lists[0]);
    foreach ($unprocessed_lists as $delta => $list) {
      if (empty($list['sv_id']) xor empty($list['name'])) {
        $form_state->setError($form['distribution_lists'][$delta], $this->t('Both sv IDs and name are required.'));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);

    $this->configuration['newsletters_language'] = $form_state->getValue('newsletters_language') ?? [];
    $this->configuration['newsletters_language_default'] = $form_state->getValue('newsletters_language_default') ?: '';
    $this->configuration['distribution_lists'] = $form_state->getValue('distribution_lists');
    $this->configuration['intro_text'] = $form_state->getValue('intro_text');
    $this->configuration['successful_subscription_message'] = $form_state->getValue('successful_subscription_message');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if (!$this->newsroomClient->isConfigured() || empty($this->configuration['distribution_lists']) || empty($this->privacyUri)) {
      return [];
    }

    return $this->formBuilder->getForm(
      SubscribeForm::class,
      $this->configuration['distribution_lists'],
      $this->configuration['newsletters_language'],
      $this->configuration['newsletters_language_default'],
      $this->configuration['intro_text'],
      $this->configuration['successful_subscription_message']
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'subscribe to newsroom newsletters');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['languages']);
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:' . OeNewsroom::CONFIG_NAME,
      'config:' . OeNewsroomNewsletter::CONFIG_NAME,
    ]);
  }

}
