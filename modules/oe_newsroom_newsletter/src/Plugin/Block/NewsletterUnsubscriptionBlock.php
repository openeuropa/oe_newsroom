<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormBuilderInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\oe_newsroom_newsletter\Form\UnsubscribeForm;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a Newsletter Unsubscription Block.
 *
 * @Block(
 *   id = "oe_newsroom_newsletter_unsubscription_block",
 *   admin_label = @Translation("Newsletter Unsubscription Block"),
 *   category = @Translation("OE Newsroom Newsletter")
 * )
 */
class NewsletterUnsubscriptionBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  private $formBuilder;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, FormBuilderInterface $form_builder) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->formBuilder = $form_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    return $this->formBuilder->getForm(UnsubscribeForm::class);
  }

}
