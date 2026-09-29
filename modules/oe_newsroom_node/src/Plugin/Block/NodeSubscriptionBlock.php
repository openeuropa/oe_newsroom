<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Drupal\oe_newsroom\Newsroom;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Provides a node subscription block.
 */
#[Block(
  id: 'oe_newsroom_node_subscription_block',
  admin_label: new TranslatableMarkup('Newsroom node subscription block'),
  category: new TranslatableMarkup('OE Newsroom Node'),
)]
class NodeSubscriptionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    protected readonly RouteMatchInterface $routeMatch,
    protected readonly ConfigFactoryInterface $configFactory,
    protected readonly RequestStack $requestStack,
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
      $container->get('current_route_match'),
      $container->get('config.factory'),
      $container->get('request_stack'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $node = $this->getNode();
    if (!$node instanceof NodeInterface) {
      return [];
    }

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

    // When the user returns from the verification email, the node URL contains
    // a confirmation flag and a success indicator. Show a confirmation message
    // on success, or an error message if something went wrong.
    $query = $this->requestStack->getCurrentRequest()->query;
    if ($query->has('newsroom_node_subscribed')) {
      if ($query->get('success') === '1') {
        $build['confirmation'] = [
          '#theme' => 'status_messages',
          '#message_list' => [
            'status' => [$this->t('Your subscription has been confirmed.')],
          ],
          '#weight' => -10,
        ];
      }
      elseif ($query->get('success') === '0') {
        $build['confirmation'] = [
          '#theme' => 'status_messages',
          '#message_list' => [
            'error' => [$this->t('Something went wrong while confirming your subscription. Please try again.')],
          ],
          '#weight' => -10,
        ];
      }
    }

    return $build;
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account) {
    return AccessResult::allowedIfHasPermission($account, 'subscribe to newsroom node notifications');
  }

  /**
   * Gets the node from the current route.
   *
   * @return \Drupal\node\NodeInterface|null
   *   The node, or NULL if the current route has no node.
   */
  protected function getNode(): ?NodeInterface {
    $node = $this->routeMatch->getParameter('node');
    return $node instanceof NodeInterface ? $node : NULL;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts(): array {
    return Cache::mergeContexts(parent::getCacheContexts(), [
      'route',
      'url.query_args:newsroom_node_subscribed',
      'url.query_args:success',
    ]);
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
