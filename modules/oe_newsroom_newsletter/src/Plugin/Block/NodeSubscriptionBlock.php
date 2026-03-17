<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Block;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\Context\EntityContextDefinition;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\oe_newsroom\Newsroom;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClientInterface;
use Drupal\oe_newsroom_newsletter\Form\NodeSubscribeForm;
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
class NodeSubscriptionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    string $plugin_id,
    array $plugin_definition,
    protected NewsroomClientInterface $newsroomClient,
    protected FormBuilderInterface $formBuilder,
    protected ConfigFactoryInterface $configFactory,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration(): array {
    return [
      'intro_text' => '',
      'successful_subscription_message' => '',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form = parent::blockForm($form, $form_state);

    // Should intro text be a rich text field?
    $form['intro_text'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Introduction text'),
      '#description' => $this->t('Text which will show on top of the form.'),
      '#default_value' => $this->configuration['intro_text'],
    ];
    $form['successful_subscription_message'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Successful subscription message'),
      '#description' => $this->t('Text which will shown if the user successfully subscribed to the newsletters. Leave empty to use the message returned by the Newsroom API.'),
      '#maxlength' => 255,
      '#default_value' => $this->configuration['successful_subscription_message'],
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    parent::blockSubmit($form, $form_state);

    $this->configuration['intro_text'] = $form_state->getValue('intro_text');
    $this->configuration['successful_subscription_message'] = $form_state->getValue('successful_subscription_message');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    /** @var \Drupal\node\NodeInterface $node */
    $node = $this->getContextValue('node');

    $privacy_uri = $this->configFactory->get(NewsroomNewsletter::CONFIG_NAME)->get('privacy_uri');
    $sv_id = $this->configFactory->get(Newsroom::CONFIG_NAME)->get('sv_id');

    if (!$this->newsroomClient->isConfigured() || empty($privacy_uri) || empty($sv_id)) {
      return [];
    }

    return $this->formBuilder->getForm(
      NodeSubscribeForm::class,
      $node,
      $this->configuration['intro_text'],
      $this->configuration['successful_subscription_message']
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function blockAccess(AccountInterface $account): AccessResultInterface {
    // @todo This will require it's own permission.
    return AccessResult::allowedIfHasPermission($account, 'subscribe to newsroom newsletters');
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
