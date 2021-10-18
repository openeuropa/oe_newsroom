<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\OeNewsroomNewsletterTrait;
use Drupal\user\Entity\Role;

/**
 * Test the subscription to multiple newsletters.
 *
 * @group oe_newsroom_newsletter
 */
class SubscribeMultipleNewslettersTest extends BrowserTestBase {

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
    $this->configureNewsroom();
    $this->configureNewsletter();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsletter',
      'unsubscribe from newsletter',
    ]);
    $this->createNewsletterPages(TRUE);
  }

  /**
   * Test if a user can subscribe to multiple newsletters.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeMultipleNewsletters(): void {
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    // Subscribe multiple Newsletters.
    $this->drupalGet($this->subscribePath);
    $assertSession->pageTextContains('Subscribe to newsletter');
    $assertSession->pageTextContains('This is the introduction text.');
    $page->fillField('Your e-mail', 'mail@example.com');
    $assertSession->pageTextContains('Newsletter lists');
    $assertSession->pageTextContains('Please select which newsletter list interests you.');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Newsletter lists field is required.');
    $page->checkField('Newsletter collection 1');
    $page->checkField('Newsletter 2');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Unsubscribe the newsletters.
    $this->drupalGet($this->unsubscribePath);
    $assertSession->pageTextContains('Unsubscribe from newsletter');
    $page->fillField('Your e-mail', 'mail@example.com');
    $assertSession->pageTextContains('Newsletter lists');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Newsletter lists field is required.');
    $page->checkField('Newsletter collection 1');
    $page->checkField('Newsletter 2');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');
  }

  /**
   * Test if a user can resubscribe to multiple newsletters.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeMultipleNewslettersTwice(): void {
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    // Subscribe the Newsletter collection 1.
    $this->drupalGet($this->subscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('Newsletter collection 1');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Subscribe two newsletters.
    $this->drupalGet($this->subscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('Newsletter collection 1');
    $page->checkField('Newsletter 2');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    // Currently, this is the correct behaviour.
    // For more information see: the NewsroomMessengerBase::subscribe.
    $assertSession->pageTextContains('A subscription for this service is already registered for this email address');

    // Unsubscribe the newsletters.
    $this->drupalGet($this->unsubscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('Newsletter collection 1');
    $page->checkField('Newsletter 2');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');

    // Unsubscribe the newsletter while the email is already unsubscribed.
    $this->drupalGet($this->unsubscribePath);
    $page->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('Newsletter 2');
    $page->pressButton('Unsubscribe');
    // Currently, this is correct behaviour because of the API.
    $assertSession->pageTextContains('Successfully unsubscribed!');
  }

}
