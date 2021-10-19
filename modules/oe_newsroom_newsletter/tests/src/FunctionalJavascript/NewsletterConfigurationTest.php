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
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsletter',
      'unsubscribe from newsletter',
    ]);
    $this->user = $this->createUser([
      'administer blocks',
      'manage newsroom newsletter settings',
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
    $assertSession->elementAttributeContains('css', 'textarea#edit-intro-text', 'required', 'required');
    $assertSession->elementAttributeContains('css', 'input#edit-privacy-uri', 'required', 'required');
    $page->fillField('Introduction text', 'This is the introduction text.');
    $page->fillField('Privacy uri', '/privacy-uri');
    $page->pressButton('Save configuration');
    $assertSession->pageTextContains('The configuration options have been saved.');

    // Test with missing configuration translation module.
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $assertSession->pageTextNotContains('Translate newsroom newsletter settings form');

    // Create pages through UI.
    $default_theme = $this->config('system.theme')->get('default');
    $this->drupalCreateContentType(['type' => 'page']);

    // Create subscribe page.
    $subscribe_page = $this->drupalCreateNode(['type' => 'page']);
    $subscribe_path = 'node/' . $subscribe_page->id();

    // Place subscribe block.
    $block_name = 'oe_newsroom_newsletter_subscription_block';
    $edit = [
      'region' => 'content',
    ];
    $edit['settings[distribution_list][0][sv_id]'] = '123';
    $edit['settings[distribution_list][0][name]'] = 'Example newsletter 1';
    $edit['visibility[request_path][pages]'] = '/node/1';
    $this->drupalGet('admin/structure/block/add/' . $block_name . '/' . $default_theme);
    $page->clickLink('Pages');
    $this->submitForm($edit, 'Save block');

    // Create unsubscribe page.
    $unsubscribe_page = $this->drupalCreateNode(['type' => 'page']);
    $unsubscribe_path = 'node/' . $unsubscribe_page->id();

    // Place unsubscribe block.
    $block_name = 'oe_newsroom_newsletter_unsubscription_block';
    $edit = [
      'region' => 'content',
    ];
    $edit['settings[distribution_list][0][sv_id]'] = '123';
    $edit['settings[distribution_list][0][name]'] = 'Example newsletter 1';
    $edit['visibility[request_path][pages]'] = '/node/2';
    $this->drupalGet('admin/structure/block/add/' . $block_name . '/' . $default_theme);
    $page->clickLink('Pages');
    $this->submitForm($edit, 'Save block');

    $this->drupalLogout();

    // Test missing private key.
    $this->drupalGet($subscribe_path);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('The subscription service is not configured at the moment. Please try again later.');

    // Test successful subscription doesn't show the fields.
    $this->setApiPrivateKey();
    $this->drupalGet($subscribe_path);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');
    $assertSession->pageTextNotContains('This is the introduction text.');
    $assertSession->pageTextNotContains('Your e-mail');
    $assertSession->pageTextNotContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');

    // Set custom success/failure subscription messages.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $page->fillField('Message in case of successful subscription', 'Success. Your email address have been subscribed to the newsletter.');
    $page->fillField('Message in case if user is already registered', 'Failure. Your email address was already subscribed to the newsletter.');
    $page->pressButton('Save configuration');
    $assertSession->pageTextContains('The configuration options have been saved.');

    $this->drupalLogout();
    // Display custom failure message.
    $this->drupalGet($subscribe_path);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Failure. Your email address was already subscribed to the newsletter.');

    // Test successful unsubscription doesn't show the field.
    $this->drupalGet($unsubscribe_path);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Successfully unsubscribed!');
    $assertSession->pageTextNotContains('Your e-mail');

    // Display custom success message.
    $this->drupalGet($subscribe_path);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->assertWaitOnAjaxRequest();
    $assertSession->pageTextContains('Success. Your email address have been subscribed to the newsletter.');

    // Multiple newsletter information should not be shown.
    $this->drupalGet($subscribe_path);
    $assertSession->pageTextNotContains('Newsletter lists');
    $assertSession->pageTextNotContains('Please select which newsletter list interests you.');
    $assertSession->hiddenFieldValueEquals('distribution_list', '123');
    $assertSession->hiddenFieldValueEquals('newsletters_language', 'en');

    // Configure multiple newsletters.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/structure/block/manage/newslettersubscriptionblock');
    $page->fillField('settings[distribution_list][1][sv_id]', '456');
    $page->fillField('settings[distribution_list][1][name]', 'Example newsletter 2');
    $page->pressButton('Save block');
    $this->drupalGet('admin/structure/block/manage/newsletterunsubscriptionblock');
    $page->fillField('settings[distribution_list][1][sv_id]', '456');
    $page->fillField('settings[distribution_list][1][name]', 'Example newsletter 2');
    $page->pressButton('Save block');
    $assertSession->pageTextContains('The block configuration has been saved.');
    $this->drupalLogout();

    $this->drupalGet($subscribe_path);
    $assertSession->pageTextContains('Newsletter lists');
    $assertSession->pageTextContains('Please select which newsletter list interests you.');
    $page->hasUncheckedField('Example newsletter 1');
    $page->hasUncheckedField('Example newsletter 2');

    // Unsubscribe the newsletters.
    $this->drupalGet($unsubscribe_path);
    $assertSession->pageTextContains('Newsletter lists');
    $assertSession->pageTextContains('Please select which newsletter list interests you.');
    $page->hasUncheckedField('Example newsletter 1');
    $page->hasUncheckedField('Example newsletter 2');
  }

}
