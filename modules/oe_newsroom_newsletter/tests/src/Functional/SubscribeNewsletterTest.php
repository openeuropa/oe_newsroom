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
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['manage own newsletter subscription']);
  }

  /**
   * Test the subscription form if a user can subscribe to a newsletter.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeNewsletter(): void {
    // Try to subscribe the newsletter with default configuration.
    $this->drupalGet('newsletter/subscribe');
    $this->assertSession()->pageTextContains('Subscription form can by only used after privacy url is set.');
    $this->assertSession()->pageTextContains('Subscribe for newsletter');
    $this->assertSession()->pageTextNotContains('This is the introduction text.');
    $this->assertSession()->pageTextNotContains('Your e-mail');
    $this->assertSession()->pageTextNotContains('Newsletter lists');
    $this->assertSession()->pageTextNotContains('Please select which newsletter list interests you.');
    $this->assertSession()->hiddenFieldNotExists('distribution_list');
    $this->assertSession()->hiddenFieldNotExists('newsletters_language');
    $this->assertSession()->pageTextNotContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->assertSession()->buttonNotExists('Subscribe');

    // Try to unsubscribe the newsletter with default configuration.
    $this->drupalGet('newsletter/unsubscribe');
    $this->assertSession()->pageTextContains('Unsubscribe from newsletter');
    $this->assertSession()->pageTextNotContains('This is the introduction text.');
    $this->assertSession()->pageTextNotContains('Newsletter lists');
    $this->assertSession()->pageTextNotContains('Please select which newsletter list interests you.');
    $this->assertSession()->hiddenFieldValueEquals('distribution_list', '');
    $this->assertSession()->pageTextNotContains('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('The subscription service is not configured at the moment. Please try again later.');

    // Try to subscribe the newsletter after setting newsletter configuration.
    $this->configureNewsletter();
    $this->drupalGet('newsletter/subscribe');
    $this->assertSession()->pageTextNotContains('Subscription form can by only used after privacy url is set.');
    $this->assertSession()->pageTextContains('This is the introduction text.');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->assertSession()->pageTextNotContains('Newsletter lists');
    $this->assertSession()->pageTextNotContains('Please select which newsletter list interests you.');
    $this->assertSession()->hiddenFieldValueEquals('distribution_list', '123');
    $this->assertSession()->hiddenFieldValueEquals('newsletters_language', 'en');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('The subscription service is not configured at the moment. Please try again later.');

    // Tests after setting the newsroom configuration.
    $this->configureNewsroom();

    // Subscribe the newsletter.
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Unsubscribe the newsletter.
    $this->drupalGet('newsletter/unsubscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    // @todo Is it ok to have an empty distribution_list?
    $this->assertSession()->hiddenFieldValueEquals('distribution_list', '');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');
  }

  /**
   * Test the subscription form if a user can subscribe to a newsletter twice.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeNewsletterTwice(): void {
    $this->configureNewsletter();
    $this->configureNewsroom();

    // Subscribe the newsletter.
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Subscribe the newsletter while the email is already subscribed.
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('A subscription for this service is already registered for this email address');

    // Unsubscribe the newsletter.
    $this->drupalGet('newsletter/unsubscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');

    // Unsubscribe the newsletter while the email is already unsubscribed.
    $this->drupalGet('newsletter/unsubscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    // Currently, this is the correct behaviour because of the API.
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');
  }

}
