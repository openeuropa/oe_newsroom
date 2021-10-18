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
use Drupal\multivalue_form_element\Element\MultiValue;
use Drupal\oe_newsroom_newsletter\Form\SubscribeForm;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Newsletter Subscription Block.
 *
 * @Block(
 *   id = "oe_newsroom_newsletter_subscription_block",
 *   admin_label = @Translation("Newsletter Subscription Block"),
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
      '#title' => $this->t('Select the selectable languages for newsletter'),
      '#description' => $this->t('Empty = all possible languages. For unilingual distribution lists select the correct language. If one language is selected, this will remain hidden from the user on the (un)subscribe from. Only site languages can be chosen as the content is normally pulled from the site.'),
      '#default_value' => $config['newsletters_language'] ?? [],
      '#multiple' => TRUE,
    ];
    $form['newsletters_language_default'] = [
      '#type' => 'language_select',
      '#title' => $this->t('Select the default language for newsletter'),
      '#description' => $this->t("This language will be selected if the user's preferred language is not selectable."),
      '#default_value' => $config['newsletters_language_default'] ?? [],
    ];
    $form['distribution_list'] = [
      '#type' => 'multivalue',
      '#title' => $this->t('Newsletter distribution lists'),
      '#description' => $this->t("If there's a single choice here, it will remain hidden on the (un)subscription form."),
      '#cardinality' => MultiValue::CARDINALITY_UNLIMITED,
      '#required' => TRUE,
      'sv_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Sv IDs'),
        '#description' => $this->t('ID(s) of the newsletter/distribution list'),
        '#maxlength' => 128,
        '#size' => 64,
      ],
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name of the distribution list'),
        '#description' => $this->t('This is used to help identify for the user which list it want to subscribe.'),
        '#maxlength' => 128,
        '#size' => 64,
      ],
      '#default_value' => $config['distribution_list'] ?? [],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    parent::blockSubmit($form, $form_state);
    // The language_select field is buggy, it doesn't give back empty value.
    $user_input = $form_state->getUserInput();

    $this->configuration['newsletters_language'] = !isset($user_input['newsletters_language']) ? $form_state->getValue('newsletters_language') : [];
    $this->configuration['newsletters_language_default'] = $form_state->getValue('newsletters_language_default');
    $this->configuration['distribution_list'] = $form_state->getValue('distribution_list');
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->formBuilder->getForm(SubscribeForm::class, $this->configuration['distribution_list'], $this->configuration['newsletters_language'], $this->configuration['newsletters_language_default']);
  }

  /**
   * {@inheritDoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'subscribe to newsletter');
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
    return Cache::mergeTags(parent::getCacheTags(), ['config:' . OeNewsroomNewsletter::OE_NEWSLETTER_CONFIG_VAR_NAME]);
  }

}
