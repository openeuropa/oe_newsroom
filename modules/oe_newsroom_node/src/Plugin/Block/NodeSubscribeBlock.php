<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\oe_newsroom\Newsroom;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a node subscription block.
 */
#[Block(
  id: 'oe_newsroom_node_subscribe_block',
  admin_label: new TranslatableMarkup('Newsroom node subscribe block'),
  category: new TranslatableMarkup('OE Newsroom Node'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup('Node')),
  ],
)]
class NodeSubscribeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly ConfigFactoryInterface $configFactory,
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
      $container->get(ConfigFactoryInterface::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->getContextValue('node');

    // The privacy URL must be configured for the subscribe form to work.
    if (empty($this->configFactory->get('oe_newsroom_node.settings')->get('privacy_url'))) {
      return [];
    }

    $url = Url::fromRoute('oe_newsroom_node.subscribe_modal', ['node' => $node->id()]);

    $build = [
      'link' => [
        '#type' => 'link',
        '#title' => $this->t('Subscribe to notifications'),
        '#url' => $url,
        '#attributes' => [
          'class' => ['use-ajax', 'oe-newsroom-node__subscribe-link'],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode(['width' => 800]),
        ],
        '#attached' => [
          'library' => ['core/drupal.dialog.ajax'],
        ],
      ],
    ];

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'subscribe to newsroom node notifications');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:' . Newsroom::CONFIG_NAME,
      'config:oe_newsroom_node.settings',
    ]);
  }

}
