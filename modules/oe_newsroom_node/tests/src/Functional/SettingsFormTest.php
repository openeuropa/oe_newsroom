<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom_node\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the node subscription settings added to the main settings form.
 *
 * @group oe_newsroom_node
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom_node',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (version_compare(\Drupal::VERSION, '11.3', '<')) {
      $this->markTestSkipped('This test only runs in Drupal >= 11.3.');
    }

    parent::setUp();
  }

  /**
   * Tests that the settings are added to the main form and are saved.
   */
  public function testSettingsForm(): void {
    $assert_session = $this->assertSession();

    // The node subscription settings are added to the main Newsroom settings
    // form, which is protected by the parent module permission.
    $this->drupalGet('admin/config/system/newsroom-settings');
    $assert_session->statusCodeEquals(403);

    $this->drupalLogin($this->createUser(['administer newsroom configuration']));
    $this->drupalGet('admin/config/system/newsroom-settings');
    $assert_session->statusCodeEquals(200);

    // The node subscription privacy URL field is present on the main form.
    $assert_session->pageTextContains('Node subscription');
    $assert_session->fieldExists('node_privacy_url');

    // Saving valid values stores the privacy URL in the dedicated node config.
    // The node service ID is required when this module is enabled.
    $this->submitForm([
      'universe' => 'example-universe',
      'app_id' => 'example-app',
      'node_service_id' => 42,
      'node_privacy_url' => '/my-privacy-page',
    ], 'Save configuration');
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $this->assertSame('internal:/my-privacy-page', $this->config('oe_newsroom_node.settings')->get('privacy_url'));
    $this->assertSame(42, $this->config('oe_newsroom.settings')->get('node_service_id'));
  }

  /**
   * Tests the required fields on the settings form.
   */
  public function testSettingsFieldRequired(): void {
    $assert_session = $this->assertSession();

    $this->drupalLogin($this->createUser(['administer newsroom configuration']));
    $this->drupalGet('admin/config/system/newsroom-settings');

    // Submitting without the privacy URL and node service ID shows both
    // required errors and the configuration is not saved.
    $this->submitForm([
      'universe' => 'example-universe',
      'app_id' => 'example-app',
      'node_service_id' => '',
      'node_privacy_url' => '',
    ], 'Save configuration');
    $assert_session->statusMessageContains('Privacy URL field is required.', 'error');
    $assert_session->statusMessageContains('Node notification service ID field is required.', 'error');
    $assert_session->statusMessageNotContains('The configuration options have been saved.');
    $this->assertSame('', $this->config('oe_newsroom_node.settings')->get('privacy_url'));

    // Submitting valid values saves the configuration.
    $this->submitForm([
      'universe' => 'example-universe',
      'app_id' => 'example-app',
      'node_service_id' => 42,
      'node_privacy_url' => '/node-privacy',
    ], 'Save configuration');
    $assert_session->statusMessageContains('The configuration options have been saved.', 'status');
    $this->assertSame('internal:/node-privacy', $this->config('oe_newsroom_node.settings')->get('privacy_url'));
    $this->assertSame(42, $this->config('oe_newsroom.settings')->get('node_service_id'));
  }

}
