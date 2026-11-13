<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

class NodeSubscribeAccessCheck implements AccessInterface {

  public function access(AccountInterface $account, ?NodeInterface $node = NULL): AccessResultInterface {
    if (!$node instanceof NodeInterface) {
      return AccessResult::forbidden();
    }

    return $node->access('oe_newsroom_node.subscribe', $account, TRUE);
  }

}
