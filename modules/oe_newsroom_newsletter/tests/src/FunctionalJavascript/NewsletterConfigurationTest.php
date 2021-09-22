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
    'oe_newsroom',
    'oe_newsroom_newsletter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->enableMock();
    $this->configureNewsroom();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['manage own newsletter subscription']);
  }

  /**
   * Test the subscription form if a user can subscribe to a newsletter.
   *
   * @group oe_newsroom_newsletter
   */
  public function testNewsletterConfiguration(): void {
    $user = $this->createUser([
      'manage newsroom newsletter settings',
    ]);
    $this->drupalLogin($user);
    // Configure newsletter.
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->assertSession()->elementAttributeContains('css', 'textarea#edit-intro-text', 'required', 'required');
    $this->assertSession()->elementAttributeContains('css', 'input#edit-privacy-uri', 'required', 'required');
    $this->getSession()->getPage()->fillField('edit-distribution-list-0-sv-id', '678');
    $this->getSession()->getPage()->fillField('edit-distribution-list-0-name', 'Example newsletter 1.');
    $this->getSession()->getPage()->fillField('Introduction text', 'Example introduction.');
    $this->getSession()->getPage()->fillField('Privacy uri', '/privacy-uri');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $this->drupalLogout();
    // Test missing e-mail address and unchecked privacy statement.
    $this->drupalGet('newsletter/subscribe');
    $this->assertSession()->pageTextContains('Example introduction.');
    $this->assertSession()->linkByHrefExists('/privacy-uri');
    $this->assertSession()->linkExists('privacy statement');
    $this->getSession()->getPage()->hasField('Your e-mail');
    $this->getSession()->getPage()->hasUncheckedField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Your e-mail field is required.');
    $this->assertSession()->pageTextContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement field is required.');

    // Test invalid e-mail address.
    $this->getSession()->getPage()->fillField('Your e-mail', '@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('The email address @example.com is not valid.');

    // Test missing private key.
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('The subscription service is not configured at the moment. Please try again later.');

    // Test successful subscription doesn't show the fields.
    $this->setApiPrivateKey();
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');
    $this->assertSession()->pageTextNotContains('Example introduction.');
    $this->assertSession()->pageTextNotContains('Your e-mail');
    $this->assertSession()->pageTextNotContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');

    // Set custom success/failure subscription messages.
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->getSession()->getPage()->fillField('Message in case of successful subscription', 'Success. Your email address have been subscribed to the newsletter.');
    $this->getSession()->getPage()->fillField('Message in case if user is already registered', 'Failure. Your email address was already subscribed to the newsletter.');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $this->drupalLogout();
    // Display custom failure message.
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Failure. Your email address was already subscribed to the newsletter.');

    // Display custom success message.
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail2@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Success. Your email address have been subscribed to the newsletter.');
  }

}
