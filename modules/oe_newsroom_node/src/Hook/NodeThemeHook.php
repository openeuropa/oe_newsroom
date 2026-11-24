<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Hook;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;

/**
 * Implements theme hooks for the oe_newsroom_node module.
 */
class NodeThemeHook {

  use StringTranslationTrait;

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'oe_newsroom_node_subscribe_button' => [
        'variables' => [
          'nid' => NULL,
          'url' => NULL,
          'label' => NULL,
          'attributes' => [],
        ],
      ],
    ];
  }

  /**
   * Implements template_preprocess_oe_newsroom_node_subscribe_button().
   */
  #[Hook('preprocess_oe_newsroom_node_subscribe_button')]
  public function preprocess(array &$variables): void {
    if ($variables['nid'] && !$variables['url']) {
      $variables['url'] = Url::fromRoute('oe_newsroom_node.subscribe_form', ['node' => $variables['nid']]);
    }

    if (!$variables['label']) {
      $variables['label'] = $this->t('Subscribe to notifications');
    }

    if (!isset($variables['attributes']['class']) || !is_array($variables['attributes']['class'])) {
      $variables['attributes']['class'] = [];
    }
    $variables['attributes']['class'][] = 'use-ajax';
    $variables['attributes']['data-dialog-type'] = 'modal';
    $variables['attributes']['data-dialog-options'] = Json::encode(['width' => 800]);

    $variables['#attached']['library'][] = 'core/drupal.dialog.ajax';
  }

}
