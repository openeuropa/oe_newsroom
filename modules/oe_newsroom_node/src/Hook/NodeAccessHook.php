<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Hook;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

class NodeAccessHook {

  #[Hook('node_access')]
  public function access(NodeInterface $node, string $operation, AccountInterface $account): AccessResultInterface {
    if ($operation !== 'oe_newsroom_node.subscribe') {
      return AccessResult::neutral();
    }

    $permission_access = AccessResult::allowedIfHasPermission($account, 'subscribe to newsroom node notifications');
    $view_access = $node->access('view', $account, TRUE);

    return $permission_access->andIf($view_access);
  }

}
