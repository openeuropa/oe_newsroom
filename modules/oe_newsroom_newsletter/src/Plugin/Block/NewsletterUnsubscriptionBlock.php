<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Block;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
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
 *
 * @internal This class depends on the client that will be later moved to a
 *   dedicated library. This class will be refactored and this will break any
 *   dependencies on it.
 */
class NewsletterUnsubscriptionBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  public function blockValidate($form, FormStateInterface $form_state): void {
    parent::blockValidate($form, $form_state);

    // Collect all the sv IDs specified across all distribution lists.
    $distribution_lists = $form_state->getValue('distribution_lists', []);
    $sv_ids = array_unique(array_reduce($distribution_lists, function ($carry, $item) {
      return array_merge($carry, explode(',', $item['sv_id']));
    }, []));
    // Since there is no queue system implemented, limit the amount of requests
    // triggered with a single unsubscribe action.
    if (count($sv_ids) > 5) {
      $form_state->setError($form['distribution_lists'], $this->t('Too many sv IDs specified between all distribution lists. Maximum 5 allowed, @count found.', [
        '@count' => count($sv_ids),
      ]));
    }

    // Since the distribution lists field is required, no need to run validation
    // when less than two distributions exist.
    if (count($distribution_lists) < 2) {
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
    $this->configuration['distribution_lists'] = $form_state->getValue('distribution_lists');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    if (!$this->newsroomClient->isConfigured() || empty($this->configuration['distribution_lists'])) {
      return [];
    }

    return $this->formBuilder->getForm(UnsubscribeForm::class, $this->configuration['distribution_lists']);
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'unsubscribe from newsroom newsletters');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:' . OeNewsroom::CONFIG_NAME,
    ]);
  }

}
