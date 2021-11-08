<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
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
 */
class NewsletterSubscriptionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  private $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    $form = parent::blockForm($form, $form_state);

    $config = $this->getConfiguration();

    $form['newsletters_language'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Newsletter languages'),
      '#description' => $this->t('Select the languages in which the newsletter is available. Leave empty to show all languages. Only the languages currently enabled in the site are available.'),
      '#default_value' => $config['newsletters_language'] ?? [],
      '#multiple' => TRUE,
    ];
    $form['newsletters_language_default'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Default newsletter language'),
      '#description' => $this->t("This language will be set as default if the user's preferred language is not available."),
      '#default_value' => $config['newsletters_language_default'] ?? [],
    ];
    $form['intro_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introduction text'),
      '#description' => $this->t('Text which will show on top of the form'),
      '#maxlength' => 128,
      '#default_value' => $config['intro_text'] ?? '',
      '#required' => TRUE,
    ];
    $form['successful_subscription_message'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Successful subscription message'),
      '#description' => $this->t('Text which will shown if the user successfully subscribed to the newsletters. Leave empty to use the message returned by the Newsroom API.'),
      '#maxlength' => 128,
      '#default_value' => $config['successful_subscription_message'] ?? '',
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
      '#default_value' => $config['distribution_lists'] ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {
    parent::blockValidate($form, $form_state);

    if (!empty($form_state->getValue('newsletters_language')) && !in_array($form_state->getValue('newsletters_language_default'), $form_state->getValue('newsletters_language'))) {
      $form_state->setError($form['newsletters_language_default'], $this->t('The default language should be part of the possible newsletter languages.'));
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
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
  public function build() {
    $newsletters_language = $this->configuration['newsletters_language'] ?? [];
    $newsletters_language_default = $this->configuration['newsletters_language_default'] ?? '';
    $intro_text = $this->configuration['intro_text'] ?? '';
    $successful_message = $this->configuration['successful_subscription_message'] ?? '';
    return $this->formBuilder->getForm(SubscribeForm::class, $this->configuration['distribution_lists'], $newsletters_language, $newsletters_language_default, $intro_text, $successful_message);
  }

  /**
   * {@inheritDoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'subscribe to newsroom newsletters');
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheContexts() {
    return Cache::mergeContexts(parent::getCacheContexts(), ['languages']);
  }

  /**
   * {@inheritDoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(parent::getCacheTags(), ['config:' . OeNewsroomNewsletter::CONFIG_NAME]);
  }

}
