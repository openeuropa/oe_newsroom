<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Field\FieldWidget;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * The default field widget for the Newsroom newsletter field.
 *
 * @FieldWidget(
 *   id = "oe_newsroom_newsletter_default",
 *   label = @Translation("Default"),
 *   field_types = {
 *     "oe_newsroom_newsletter"
 *   },
 * )
 */
class NewsletterWidget extends WidgetBase {

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\link\LinkItemInterface $item */
    $item = $items[$delta];

    $element['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable newsletter subscriptions'),
      '#default_value' => $item->get('enabled')->getValue(),
    ];

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {
    foreach ($values as &$value) {
      $value['enabled'] = (bool) $value['enabled'];
      unset($value['settings']);
    }
    return $values;
  }

}
