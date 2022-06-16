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
    $this->placeNewsletterSubscriptionBlock([], TRUE);
    $this->placeNewsletterUnsubscriptionBlock([], TRUE);
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsroom newsletters',
      'unsubscribe from newsroom newsletters',
    ]);
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

    // Subscribe to one of the multiple Newsletters.
    $this->drupalGet('<front>');
    $assertSession->pageTextContains('Subscribe to newsletter');
    $assertSession->pageTextContains('This is the introduction text.');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', 'mail@example.com');
    $assertSession->pageTextContains('Newsletters');
    $assertSession->pageTextContains('Please select the newsletter lists you want to take an action on.');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Newsletters field is required.');
    $subscribe_block->checkField('Newsletter 1');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Unsubscribe from one of the newsletters.
    $this->drupalGet('<front>');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->fillField('Your e-mail', 'mail@example.com');
    $assertSession->pageTextContains('Newsletters');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Newsletters field is required.');
    $unsubscribe_block->checkField('Newsletter 1');
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

    // Subscribe the Newsletter collection.
    $this->drupalGet('<front>');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', 'mail@example.com');
    $subscribe_block->checkField('Newsletter collection');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Subscribe two newsletters.
    $this->drupalGet('<front>');
    $subscribe_block = $assertSession->elementExists('css', '#block-subscribe');
    $subscribe_block->fillField('Your e-mail', 'mail@example.com');
    $subscribe_block->checkField('Newsletter 1');
    $subscribe_block->checkField('Newsletter collection');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Unsubscribe the newsletters.
    $this->drupalGet('<front>');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->fillField('Your e-mail', 'mail@example.com');
    $unsubscribe_block->checkField('Newsletter 1');
    $unsubscribe_block->checkField('Newsletter collection');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');

    // Unsubscribe the newsletter while the email is already unsubscribed.
    $this->drupalGet('<front>');
    $unsubscribe_block = $assertSession->elementExists('css', '#block-unsubscribe');
    $unsubscribe_block->checkField('Newsletter 1');
    $unsubscribe_block->fillField('Your e-mail', 'mail@example.com');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');
  }

}
