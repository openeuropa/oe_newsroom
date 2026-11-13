<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom\Newsroom;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Block to subscribe to node notifications.
 */
#[Block(
  id: 'oe_newsroom_node_subscribe_block',
  admin_label: new TranslatableMarkup('Newsroom node subscribe block'),
  category: new TranslatableMarkup('OE Newsroom Node'),
)]
class NodeSubscribeBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly RouteMatchInterface $routeMatch,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('current_route_match'),
    );
  }

  protected function getNode(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');
    return $node instanceof NodeInterface ? $node : NULL;
  }

  public function build(): array {
    $node = $this->getNode();
    if (!$node) {
      return [];
    }

    $url = Url::fromRoute('oe_newsroom_node.subscribe_form', ['node' => $node->id()]);

    return [
      '#theme' => 'oe_newsroom_node_subscribe_button',
      '#url' => $url,
      '#label' => $this->t('Subscribe'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    $node = $this->getNode();
    if (!$node instanceof NodeInterface) {
      return AccessResult::forbidden()->addCacheContexts(['route']);
    }

    return $node->access('oe_newsroom_node.subscribe', $account, TRUE);
  }

  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), ['route']);
  }

  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:' . Newsroom::CONFIG_NAME,
      'config:oe_newsroom_node.settings',
    ]);
  }

}
