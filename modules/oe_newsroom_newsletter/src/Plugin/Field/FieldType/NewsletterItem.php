<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Field\FieldType;

use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemBase;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\TypedData\DataDefinition;
use Drupal\Core\TypedData\Exception\MissingDataException;

/**
 * Defines the field type for OpenEuropa Newsroom Newsletter fields.
 *
 * @FieldType(
 *   id = "oe_newsroom_newsletter",
 *   label = @Translation("Newsroom newsletter"),
 *   description = @Translation("Stores the configuration for a Newsroom newsletter."),
 *   default_widget = "oe_newsroom_newsletter_default",
 *   default_formatter = "oe_newsroom_newsletter_subscribe_form"
 * )
 */
class NewsletterItem extends FieldItemBase implements NewsletterItemInterface {

  /**
   * {@inheritdoc}
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    return [
      'enabled' => DataDefinition::create('boolean')
        ->setLabel(t('Enable newsletter subscriptions')),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function schema(FieldStorageDefinitionInterface $field_definition) {
    return [
      'columns' => [
        'enabled' => [
          'description' => 'Whether or not subscribing to newsletters is currently enabled.',
          'type' => 'int',
          'unsigned' => TRUE,
          'size' => 'tiny',
        ],
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public static function generateSampleValue(FieldDefinitionInterface $field_definition) {
    return [
      'enabled' => TRUE,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function isEmpty() {
    return FALSE;
  }

  /**
   * {@inheritdoc}
   */
  public function isEnabled(): bool {
    try {
      return !$this->isEmpty() && (bool) $this->get('enabled')->getValue();
    }
    catch (MissingDataException $e) {
      return FALSE;
    }
  }

}
