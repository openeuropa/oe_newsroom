<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Plugin\Block;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;

/**
 * Shared distribution lists logic for the newsletter blocks.
 */
trait NewsletterDistributionListsTrait {

  /**
   * Builds the distribution lists form element.
   *
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $description
   *   The description of the distribution lists element.
   * @param \Drupal\Core\StringTranslation\TranslatableMarkup $name_description
   *   The description of the distribution list name field.
   *
   * @return array
   *   The distribution lists render element.
   */
  protected function distributionListsElement(TranslatableMarkup $description, TranslatableMarkup $name_description): array {
    return [
      '#type' => 'multivalue',
      '#title' => $this->t('Newsletter distribution lists'),
      '#description' => $description,
      '#cardinality' => 5,
      '#required' => TRUE,
      'sv_id' => [
        '#type' => 'textfield',
        '#title' => $this->t('Sv IDs'),
        '#description' => $this->t('Comma-separated list of newsletter/distribution list IDs.'),
        '#maxlength' => 128,
      ],
      'name' => [
        '#type' => 'textfield',
        '#title' => $this->t('Name of the distribution list'),
        '#description' => $name_description,
        '#maxlength' => 128,
      ],
      '#default_value' => $this->configuration['distribution_lists'],
    ];
  }

  /**
   * Validates the distribution lists element.
   *
   * @param array $form
   *   The block configuration form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The form state.
   */
  protected function validateDistributionLists(array $form, FormStateInterface $form_state): void {
    // Since the distribution lists field is required, no need to run validation
    // when less than two distributions exist.
    if (count($form_state->getValue('distribution_lists', [])) < 2) {
      return;
    }

    // The user input holds the original, unprocessed deltas (the multivalue
    // element rekeys them), so when present we can flag the exact rows.
    $user_input = $form_state->getUserInput();
    $unprocessed_lists = empty($user_input) ? NULL : NestedArray::getValue($user_input, $form['distribution_lists']['#parents']);
    if (is_array($unprocessed_lists)) {
      // Skip the first list, which is always required.
      foreach (array_slice($unprocessed_lists, 1, NULL, TRUE) as $delta => $list) {
        if (empty($list['sv_id']) xor empty($list['name'])) {
          $form_state->setError($form['distribution_lists'][$delta], $this->t('Both sv IDs and name are required.'));
        }
      }
      return;
    }

    // When embedded (e.g. block_field) there is no user input: validate the
    // cleaned values and flag the whole element instead of a specific row.
    foreach ($form_state->getValue('distribution_lists', []) as $list) {
      if (empty($list['sv_id']) xor empty($list['name'])) {
        $form_state->setError($form['distribution_lists'], $this->t('Both sv IDs and name are required.'));
        return;
      }
    }
  }

}
