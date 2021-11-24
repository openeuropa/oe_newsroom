<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom\Functional;

use Drupal\oe_newsroom\OeNewsroom;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Newsroom API configuration.
 *
 * @group oe_newsroom
 */
class NewsroomConfigurationTest extends BrowserTestBase {

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
   * Test the Newsroom configuration page.
   */
  public function testNewsroomConfigurationPage(): void {
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

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
    $assertSession->elementAttributeContains('css', 'input#edit-universe', 'required', 'required');
    $assertSession->elementAttributeContains('css', 'input#edit-app-id', 'required', 'required');
    $page->fillField('Universe acronym', 'Site1');
    $page->fillField('App ID', 'Site1_app');
    $page->hasSelect('Hash method');
    $this->assertEquals([
      'sha256' => 'SHA-256',
      'md5' => 'MD5',
    ], $this->getOptions('Hash method'));
    $page->hasCheckedField('Normalize before hashing');
    $page->pressButton('Save configuration');
    $assertSession->pageTextContains('The configuration options have been saved.');

    // Validate the saved values.
    $config = $this->config(OeNewsroom::CONFIG_NAME);
    $this->assertEquals('sha256', $config->get('hash_method'));
    $this->assertTrue($config->get('normalised'));
    $this->assertEquals('Site1', $config->get('universe'));
    $this->assertEquals('Site1_app', $config->get('app_id'));
  }

}
