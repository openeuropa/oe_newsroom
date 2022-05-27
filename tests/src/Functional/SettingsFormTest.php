<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom\Functional;

use Drupal\oe_newsroom\OeNewsroom;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Newsroom configuration form.
 *
 * @group oe_newsroom
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test the Newsroom settings form.
   */
  public function testSettingsForm(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Anonymous doesn't have access to the page.
    $this->drupalGet('admin/config/system/newsroom-settings');
    $this->assertSession()->statusCodeEquals(403);

    // User without permission doesn't have access to the page.
    $user = $this->createUser();
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/system/newsroom-settings');
    $this->assertSession()->statusCodeEquals(403);

    $user = $this->createUser([
      'administer newsroom configuration',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/system/newsroom-settings');
    $assert_session->elementAttributeContains('css', '#edit-universe', 'required', 'required');
    $assert_session->elementAttributeContains('css', '#edit-app-id', 'required', 'required');
    $page->fillField('Universe acronym', 'Site1');
    $page->fillField('App ID', 'Site1_app');
    $this->assertEquals([
      'sha256' => 'SHA-256',
      'md5' => 'MD5',
    ], $this->getOptions('Hash method'));
    $assert_session->checkboxChecked('Normalise before hashing');
    $page->pressButton('Save configuration');
    $assert_session->pageTextContains('The configuration options have been saved.');

    // Validate the saved values.
    $config = $this->config(OeNewsroom::CONFIG_NAME);
    $this->assertSame('sha256', $config->get('hash_method'));
    $this->assertTrue($config->get('normalised'));
    $this->assertSame('Site1', $config->get('universe'));
    $this->assertSame('Site1_app', $config->get('app_id'));
  }

}
