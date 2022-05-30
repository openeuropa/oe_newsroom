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
class SubscribeNewsletterTestOLD extends BrowserTestBase {

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
    $this->configureNewsletter();
    $this->configureNewsroom();
    $this->placeNewsletterSubscriptionBlock();
    $this->placeNewsletterUnsubscriptionBlock();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsroom newsletters',
      'unsubscribe from newsroom newsletters',
    ]);
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

    // Test missing e-mail address and unchecked privacy statement (subscribe).
    $this->drupalGet('<front>');
    $assertSession->pageTextContains('This is the introduction text.');
    $assertSession->linkByHrefExists('/privacy-url');
    $assertSession->linkExists('privacy statement');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->hasField('Your e-mail');
    $assertSession->checkboxNotChecked('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Your e-mail field is required.');
    $assertSession->pageTextContains('You must agree with the privacy statement.');

    // Test invalid e-mail address (subscribe).
    $this->drupalGet('<front>');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', '@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('The email address @example.com is not valid.');

    // Subscribe the newsletter.
    $this->drupalGet('<front>');
    $assertSession->pageTextContains('Subscribe to newsletter');
    $assertSession->pageTextContains('This is the introduction text.');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', 'mail@example.com');
    $assertSession->pageTextNotContains('Newsletters');
    $assertSession->pageTextNotContains('Please select the newsletter lists you want to take an action on.');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Test missing e-mail address (unsubscribe).
    $this->drupalGet('<front>');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->hasField('Your e-mail');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Your e-mail field is required.');

    // Test invalid e-mail address (unsubscribe).
    $this->drupalGet('<front>');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->fillField('Your e-mail', '@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('The email address @example.com is not valid.');

    // Unsubscribe the newsletter.
    $this->drupalGet('<front>');
    $assertSession->pageTextContains('Unsubscribe from newsletter');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->fillField('Your e-mail', 'mail@example.com');
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

    // Subscribe the newsletter.
    $this->drupalGet('<front>');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Subscribe the newsletter while the email is already subscribed.
    $this->drupalGet('<front>');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', 'mail@example.com');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Unsubscribe the newsletter.
    $this->drupalGet('<front>');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->fillField('Your e-mail', 'mail@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');

    // Unsubscribe the newsletter while the email is already unsubscribed.
    $this->drupalGet('<front>');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->fillField('Your e-mail', 'mail@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');
  }

}
