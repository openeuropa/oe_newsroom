<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Plugin\Block;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\Core\Url;
use Drupal\oe_newsroom\Newsroom;
use Drupal\oe_newsroom_newsletter\NewsroomNewsletter;

/**
 * Block to subscribe to node notifications.
 */
#[Block(
  id: 'oe_newsroom_node_subscription_block',
  admin_label: new TranslatableMarkup('Node subscription block'),
  context_definitions: [
    'node' => new EntityContextDefinition('entity:node', new TranslatableMarkup("Node")),
  ]
)]
class NodeSubscriptionBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'button_text' => 'Subscribe',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    // Should intro text be a rich text field?
    $form['button_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Button text'),
      '#description' => $this->t('Text on the button that opens the subscribe form dialog.'),
      '#maxlength' => 255,
      '#default_value' => $this->configuration['button_text'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);

    $this->configuration['button_text'] = $form_state->getValue('button_text');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');

    $url = Url::fromRoute('oe_newsroom_node.subscribe', [
      'node' => (string) $node->id(),
    ]);

    return [
      'button' => [
        '#type' => 'link',
        '#title' => $this->t('Subscribe'),
        '#url' => $url,
        '#attributes' => [
          'class' => [
            'use-ajax',
            'button',
          ],
          'data-dialog-type' => 'modal',
          'data-dialog-options' => Json::encode([
            'width' => 600,
          ]),
        ],
      ],
      '#attached' => [
        'library' => [
          'core/drupal.dialog.ajax',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // @todo This will require it's own permission.
    return AccessResult::allowedIfHasPermission($account, 'subscribe to newsroom nodes');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags(): array {
    return Cache::mergeTags(parent::getCacheTags(), [
      'config:' . Newsroom::CONFIG_NAME,
      'config:' . NewsroomNewsletter::CONFIG_NAME,
    ]);
  }

}
