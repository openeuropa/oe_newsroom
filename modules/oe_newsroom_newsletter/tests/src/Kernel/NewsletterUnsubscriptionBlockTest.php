<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom_newsletter\Kernel;

use Drupal\Core\Block\BlockPluginInterface;
use Drupal\Core\Form\FormState;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests the newsletter unsubscription block configuration validation.
 */
class NewsletterUnsubscriptionBlockTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'multivalue_form_element',
    'oe_newsroom',
    'oe_newsroom_newsletter',
    'system',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installConfig([
      'oe_newsroom',
      'oe_newsroom_newsletter',
    ]);
  }

  /**
   * Tests that a complete configuration is accepted when embedded.
   */
  public function testEmbeddedValidationAcceptsCompleteLists(): void {
    $errors = $this->validateEmbedded([
      ['sv_id' => '111', 'name' => 'First list'],
      ['sv_id' => '222', 'name' => 'Second list'],
    ]);
    $this->assertNotContains('Both sv IDs and name are required.', $errors);
  }

  /**
   * Tests that an incomplete additional list is rejected when embedded.
   */
  public function testEmbeddedValidationRejectsIncompleteSecondList(): void {
    $errors = $this->validateEmbedded([
      ['sv_id' => '111', 'name' => 'First list'],
      ['sv_id' => '222', 'name' => ''],
    ]);
    $this->assertContains('Both sv IDs and name are required.', $errors);
  }

  /**
   * Tests that an incomplete first list is rejected when embedded.
   */
  public function testEmbeddedValidationRejectsIncompleteFirstList(): void {
    $errors = $this->validateEmbedded([
      ['sv_id' => '111', 'name' => ''],
      ['sv_id' => '222', 'name' => 'Second list'],
    ]);
    $this->assertContains('Both sv IDs and name are required.', $errors);
  }

  /**
   * Validates a block configuration the way an embedded widget would.
   *
   * Mimics block_field's BlockFieldWidget::validate(), which validates the
   * block configuration against a form state that only carries the cleaned
   * values, with no user input.
   *
   * @param array $distribution_lists
   *   The distribution lists to validate.
   *
   * @return string[]
   *   The validation error messages.
   *
   * @see \Drupal\block_field\Plugin\Field\FieldWidget\BlockFieldWidget::validate()
   */
  protected function validateEmbedded(array $distribution_lists): array {
    $configuration = ['distribution_lists' => $distribution_lists];

    $block = $this->container->get('plugin.manager.block')
      ->createInstance('oe_newsroom_newsletter_unsubscription_block', $configuration);
    $this->assertInstanceOf(BlockPluginInterface::class, $block);

    // Minimal representation of the built configuration form.
    $form = ['distribution_lists' => ['#parents' => ['settings', 'distribution_lists']]];

    // The form state only holds the cleaned values, with no user input.
    $form_state = (new FormState())->setValues($configuration);
    $this->assertNull($form_state->getUserInput());

    $block->validateConfigurationForm($form, $form_state);

    // The error key is not asserted: under the real block_field widget it is
    // re-based onto the host field parents by BlockFieldWidget::validate().
    return array_map('strval', $form_state->getErrors());
  }

}
