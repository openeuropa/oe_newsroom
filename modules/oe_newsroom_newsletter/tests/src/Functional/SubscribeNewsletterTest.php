<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\OeNewsroomNewsletterTrait;
use Drupal\user\Entity\Role;

/**
 * Test the subscription to the newsletter.
 *
 * @group oe_newsroom_newsletter
 */
class SubscribeNewsletterTest extends BrowserTestBase {

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setApiPrivateKey();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsletter',
      'unsubscribe from newsletter',
    ]);
    $this->createNewsletterPages();
  }

  /**
   * Test the subscription form if a user can subscribe to a newsletter.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeNewsletter(): void {
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    // Try to subscribe the newsletter with default configuration.
    $this->drupalGet($this->subscribePath);
    $assertSession->pageTextContains('Subscription form can be only used after privacy url is set.');
    $assertSession->pageTextContains('Subscribe to newsletter');
    $assertSession->pageTextNotContains('This is the introduction text.');
    $assertSession->pageTextNotContains('Your e-mail');
    $assertSession->pageTextNotContains('Newsletter lists');
    $assertSession->pageTextNotContains('Please select which newsletter list interests you.');
    $assertSession->hiddenFieldNotExists('distribution_list');
    $assertSession->hiddenFieldNotExists('newsletters_language');
    $assertSession->pageTextNotContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $assertSession->buttonNotExists('Subscribe');

    // Try to unsubscribe the newsletter with default configuration.
    $this->drupalGet($this->unsubscribePath);
    $assertSession->pageTextContains('Unsubscribe from newsletter');
    $assertSession->pageTextNotContains('This is the introduction text.');
    $assertSession->pageTextNotContains('Newsletter lists');
    $assertSession->pageTextNotContains('Please select which newsletter list interests you.');
    $assertSession->hiddenFieldValueEquals('distribution_list', '123');
    $assertSession->pageTextNotContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('The subscription service is not configured at the moment. Please try again later.');

    // Try to subscribe the newsletter after setting newsletter configuration.
    $this->configureNewsletter();

    $this->drupalGet($this->subscribePath);
    $assertSession->pageTextNotContains('Subscription form can be only used after privacy url is set.');
    $assertSession->pageTextContains('This is the introduction text.');
    $page->fillField('Your e-mail', 'mail@example.com');
    $assertSession->pageTextNotContains('Newsletter lists');
    $assertSession->pageTextNotContains('Please select which newsletter list interests you.');
    $assertSession->hiddenFieldValueEquals('distribution_list', '123');
    $assertSession->hiddenFieldValueEquals('newsletters_language', 'en');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('The subscription service is not configured at the moment. Please try again later.');

    // Tests after setting the newsroom configuration.
    $this->configureNewsroom();

    // Test missing e-mail address and unchecked privacy statement (subscribe).
    $this->drupalGet($this->subscribePath);
    $assertSession->pageTextContains('This is the introduction text.');
    $assertSession->linkByHrefExists('/privacy-url');
    $assertSession->linkExists('privacy statement');
    $page->hasField('Your e-mail');
    $page->hasUncheckedField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Your e-mail field is required.');
    $assertSession->pageTextContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement field is required.');

    // Test invalid e-mail address (subscribe).
    $this->drupalGet($this->subscribePath);
    $page->fillField('Your e-mail', '@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('The email address @example.com is not valid.');

    // Subscribe the newsletter.
    $this->drupalGet($this->subscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Test missing e-mail address (unsubscribe).
    $this->drupalGet($this->unsubscribePath);
    $page->hasField('Your e-mail');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Your e-mail field is required.');

    // Test invalid e-mail address (unsubscribe).
    $this->drupalGet($this->unsubscribePath);
    $page->fillField('Your e-mail', '@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('The email address @example.com is not valid.');

    // Unsubscribe the newsletter.
    $this->drupalGet($this->unsubscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $assertSession->hiddenFieldValueEquals('distribution_list', '123');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');
  }

  /**
   * Test the subscription form if a user can subscribe to a newsletter twice.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeNewsletterTwice(): void {
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    $this->configureNewsletter();
    $this->configureNewsroom();

    // Subscribe the newsletter.
    $this->drupalGet($this->subscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Subscribe the newsletter while the email is already subscribed.
    $this->drupalGet($this->subscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('A subscription for this service is already registered for this email address');

    // Unsubscribe the newsletter.
    $this->drupalGet($this->unsubscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');

    // Unsubscribe the newsletter while the email is already unsubscribed.
    $this->drupalGet($this->unsubscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->pressButton('Unsubscribe');
    // Currently, this is the correct behaviour because of the API.
    $assertSession->pageTextContains('Successfully unsubscribed!');
  }

}
