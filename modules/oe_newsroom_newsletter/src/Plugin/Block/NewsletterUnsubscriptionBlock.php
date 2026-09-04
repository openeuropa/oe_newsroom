<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Block;

use Drupal\Component\Utility\Html;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\oe_newsroom\Newsroom;
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

  use NewsletterDistributionListsTrait;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly NewsroomClientInterface $newsroomClient,
    private readonly FormBuilderInterface $formBuilder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(NewsroomClientInterface::class),
      $container->get('form_builder'),
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

    $form['distribution_lists'] = $this->distributionListsElement(
      $this->t("If there's a single choice here, it will remain hidden on the (un)subscription form."),
      $this->t('This is used to help the user identify which list they want to unsubscribe from.'),
    );

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

    $this->validateDistributionLists($form, $form_state);
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

    $build = $this->formBuilder->getForm(UnsubscribeForm::class, $this->configuration['distribution_lists']);

    // Since Drupal 11.4 the block wrapper no longer inherits the form's
    // #attributes, so add the form-id class via #wrapper_attributes (a no-op
    // on older core, which still copies the class from #attributes).
    $build['#wrapper_attributes']['class'][] = Html::getClass(UnsubscribeForm::FORM_ID);

    return $build;
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
      'config:' . Newsroom::CONFIG_NAME,
    ]);
  }

}
