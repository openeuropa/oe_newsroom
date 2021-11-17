<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\OeNewsroomNewsletterTrait;
use Drupal\user\Entity\Role;

/**
 * Test the Newsletter configuration.
 *
 * @group oe_newsroom_newsletter
 */
class NewsletterConfigurationTest extends WebDriverTestBase {

  use OeNewsroomNewsletterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'node',
    'oe_newsroom_newsletter_mock',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The user with permissions.
   *
   * @var \Drupal\user\Entity\User|false
   */
  protected $user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->configureNewsroom();
    $this->unsetApiPrivateKey();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsroom newsletters',
      'unsubscribe from newsroom newsletters',
    ]);
    $this->user = $this->createUser([
      'administer blocks',
      'administer newsroom newsletter configuration',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Test the newsletter settings.
   *
   * @group oe_newsroom_newsletter
   */
  public function testNewsletterSettings(): void {
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    // Configure newsletter.
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $assertSession->elementAttributeContains('css', 'input#edit-privacy-uri', 'required', 'required');
    $page->fillField('Privacy URL', '/privacy-uri');
    $page->pressButton('Save configuration');
    $assertSession->pageTextContains('The configuration options have been saved.');

    // Test with missing configuration translation module.
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $assertSession->pageTextNotContains('Translate newsroom newsletter settings form');

    $default_theme = $this->config('system.theme')->get('default');

    // Place subscribe block.
    $block_name = 'oe_newsroom_newsletter_subscription_block';
    $edit = [
      'id' => 'subscribe',
      'region' => 'content',
    ];
    $edit['settings[intro_text]'] = 'This is the introduction text.';
    $edit['settings[distribution_lists][0][sv_id]'] = '123';
    $edit['settings[distribution_lists][0][name]'] = 'Example newsletter 1';
    $this->drupalGet('admin/structure/block/add/' . $block_name . '/' . $default_theme);
    $page->pressButton('Edit');
    $this->submitForm($edit, 'Save block');

    // Place unsubscribe block.
    $block_name = 'oe_newsroom_newsletter_unsubscription_block';
    $edit = [
      'id' => 'unsubscribe',
      'region' => 'content',
    ];
    $edit['settings[distribution_lists][0][sv_id]'] = '123';
    $edit['settings[distribution_lists][0][name]'] = 'Example newsletter 1';
    $this->drupalGet('admin/structure/block/add/' . $block_name . '/' . $default_theme);
    $page->pressButton('Edit');
    $this->submitForm($edit, 'Save block');

    $this->drupalLogout();

    // Test missing private key.
    $this->drupalGet('<front>');
    $assertSession->elementNotExists('css', '#block-subscribe');
    $assertSession->elementNotExists('css', '#block-unsubscribe');
    $assertSession->fieldNotExists('Your e-mail');

    $this->setApiPrivateKey();

    // @todo Find better way.
    drupal_flush_all_caches();

    // Test successful subscription doesn't show the fields.
    $this->drupalGet('<front>');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');
    $assertSession->pageTextNotContains('This is the introduction text.');
    $assertSession->elementTextNotContains('css', '#block-subscribe', 'Your e-mail');
    $assertSession->pageTextNotContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');

    // Set custom successful subscription message.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/structure/block/manage/subscribe');
    $page->fillField('Successful subscription message', 'Success. Your email address have been subscribed to the newsletter.');
    $page->pressButton('Save block');
    $assertSession->pageTextContains('The block configuration has been saved.');

    $this->drupalLogout();

    // Display custom success message.
    $this->drupalGet('<front>');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Success. Your email address have been subscribed to the newsletter.');

    // Test successful unsubscription doesn't show the field.
    $this->drupalGet('<front>');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->fillField('Your e-mail', 'mail@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Successfully unsubscribed!');
    $assertSession->elementTextNotContains('css', '#block-unsubscribe', 'Your e-mail');

    // Multiple newsletter information should not be shown.
    $this->drupalGet('<front>');
    $assertSession->pageTextNotContains('Newsletters');
    $assertSession->pageTextNotContains('Please select which newsletter list interests you.');

    // Configure multiple newsletters.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/structure/block/manage/subscribe');
    $page->fillField('settings[distribution_lists][1][sv_id]', '456');
    $page->fillField('settings[distribution_lists][1][name]', 'Example newsletter 2');
    $page->pressButton('Save block');
    $this->drupalGet('admin/structure/block/manage/unsubscribe');
    $page->fillField('settings[distribution_lists][1][sv_id]', '456');
    $page->fillField('settings[distribution_lists][1][name]', 'Example newsletter 2');
    $page->pressButton('Save block');
    $assertSession->pageTextContains('The block configuration has been saved.');
    $this->drupalLogout();

    $this->drupalGet('<front>');
    $assertSession->pageTextContains('Newsletters');
    $assertSession->pageTextContains('Please select which newsletter list interests you.');
    $page->hasUncheckedField('Example newsletter 1');
    $page->hasUncheckedField('Example newsletter 2');

    // Unsubscribe the newsletters.
    $this->drupalGet('<front>');
    $assertSession->pageTextContains('Newsletters');
    $assertSession->pageTextContains('Please select which newsletter list interests you.');
    $page->hasUncheckedField('Example newsletter 1');
    $page->hasUncheckedField('Example newsletter 2');
  }

}
