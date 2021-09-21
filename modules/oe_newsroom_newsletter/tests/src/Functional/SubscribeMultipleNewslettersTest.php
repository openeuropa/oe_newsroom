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

    $this->setApiPrivateKey();
    $this->enableMock();
    $this->configureNewsroom();
    $this->configureMultipleNewsletters();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['manage own newsletter subscription']);
  }

  /**
   * Test if a user can subscribe to multiple newsletters.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeMultipleNewsletters(): void {
    // Subscribe multiple Newsletters.
    $this->drupalGet('newsletter/subscribe');
    $this->assertSession()->pageTextContains('Subscribe for newsletter');
    $this->assertSession()->pageTextContains('This is the introduction text.');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->assertSession()->pageTextContains('Newsletter lists');
    $this->assertSession()->pageTextContains('Please select which newsletter list interests you.');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Newsletter lists field is required.');
    $this->getSession()->getPage()->checkField('Newsletter collection 1');
    $this->getSession()->getPage()->checkField('Newsletter 2');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Unsubscribe the newsletters.
    $this->drupalGet('newsletter/unsubscribe');
    $this->assertSession()->pageTextContains('Unsubscribe from newsletter');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->assertSession()->pageTextContains('Newsletter lists');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('Newsletter lists field is required.');
    $this->getSession()->getPage()->checkField('Newsletter collection 1');
    $this->getSession()->getPage()->checkField('Newsletter 2');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');
  }

  /**
   * Test if a user can resubscribe to multiple newsletters.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeMultipleNewslettersTwice(): void {
    // Subscribe the Newsletter collection 1.
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('Newsletter collection 1');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Subscribe two newsletters.
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('Newsletter collection 1');
    $this->getSession()->getPage()->checkField('Newsletter 2');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    // Currently, this is the correct behaviour.
    // For more information see: the NewsroomMessengerBase::subscribe.
    $this->assertSession()->pageTextContains('A subscription for this service is already registered for this email address');

    // Unsubscribe the newsletters.
    $this->drupalGet('newsletter/unsubscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('Newsletter collection 1');
    $this->getSession()->getPage()->checkField('Newsletter 2');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');

    // Unsubscribe the newsletter while the email is already unsubscribed.
    $this->drupalGet('newsletter/unsubscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('Newsletter 2');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    // Currently, this is correct behaviour because of the API.
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');
  }

}
