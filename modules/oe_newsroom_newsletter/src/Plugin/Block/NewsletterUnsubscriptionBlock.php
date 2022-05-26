<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_newsroom\OeNewsroom;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClient;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\Form\UnsubscribeForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Newsletter unsubscription block.
 *
 * @Block(
 *   id = "oe_newsroom_newsletter_unsubscription_block",
 *   admin_label = @Translation("Newsletter unsubscription block"),
 *   category = @Translation("OE Newsroom Newsletter")
 * )
 */
class NewsletterUnsubscriptionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * API for newsroom calls.
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
  public function __construct(array $configuration, $plugin_id, $plugin_definition, NewsroomClientInterface $newsroomClient, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
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
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'distribution_lists' => [],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

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
        '#description' => $this->t('This is used to help the user identify which list they want to unsubscribe from.'),
        '#maxlength' => 128,
      ],
      '#default_value' => $this->configuration['distribution_lists'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);
    $this->configuration['distribution_lists'] = $form_state->getValue('distribution_lists');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->formBuilder->getForm(UnsubscribeForm::class, $this->configuration['distribution_lists']);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    if (!$this->newsroomClient->isConfigured() || empty($this->configuration['distribution_lists'])) {
      return AccessResult::forbidden()->addCacheTags(['config:' . OeNewsroom::CONFIG_NAME]);
    }

    return AccessResult::allowedIfHasPermission($account, 'unsubscribe from newsroom newsletters');
  }

}
