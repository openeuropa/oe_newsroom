<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom\Functional;

use Drupal\oe_newsroom\OeNewsroom;
use Drupal\Tests\BrowserTestBase;

/**
 * Test the Newsroom API configuration.
 *
 * @group oe_newsroom_newsletter
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
   * Test if the Newsroom factory retrieves the correct configuration values.
   *
   * @group oe_newsroom
   */
  public function testNewsroomConfiguration(): void {
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    $user = $this->createUser([
      'manage newsroom settings',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/system/newsroom-settings');
    $assertSession->elementAttributeContains('css', 'input#edit-universe', 'required', 'required');
    $assertSession->elementAttributeContains('css', 'input#edit-app', 'required', 'required');
    $page->fillField('Universe Acronym', 'Site1');
    $page->fillField('App', 'Site1_app');
    $page->hasCheckedField('Is normalized?');
    $page->pressButton('Save configuration');
    $assertSession->pageTextContains('The configuration options have been saved.');

    // Validate the saved values.
    $config = $this->config(OeNewsroom::CONFIG_NAME);
    $this->assertEquals('sha256', $config->get('hash_method'));
    $this->assertEquals('1', $config->get('normalized'));
    $this->assertEquals('Site1', $config->get('universe'));
    $this->assertEquals('Site1_app', $config->get('app'));
  }

}
