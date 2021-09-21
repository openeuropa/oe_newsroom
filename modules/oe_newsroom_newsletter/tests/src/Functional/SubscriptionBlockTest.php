<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\OeNewsroomNewsletterTrait;
use Drupal\user\Entity\Role;

/**
 * Test the subscription through the newsletter block.
 *
 * @group oe_newsroom_newsletter
 */
class SubscriptionBlockTest extends BrowserTestBase {

  use OeNewsroomNewsletterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
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
    $this->configureNewsletter();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['manage own newsletter subscription']);
  }

  /**
   * Test the subscription block if a user can subscribe to a newsletter.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscriptionBlock(): void {
    $block_settings = [
      'label' => 'Newsletter Subscription Block',
      'region' => 'content',
    ];
    $this->drupalPlaceBlock('oe_newsroom_newsletter_subscription_block', $block_settings);
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Newsletter Subscription Block');
    $this->assertSession()->pageTextContains('This is the introduction text.');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');
  }

  /**
   * Test the unsubscription block if a user can unsubscribe from a newsletter.
   *
   * @group oe_newsroom_newsletter
   */
  public function testUnsubscriptionBlock(): void {
    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $block_settings = [
      'label' => 'Newsletter Unsubscription Block',
      'region' => 'content',
    ];
    $this->drupalPlaceBlock('oe_newsroom_newsletter_unsubscription_block', $block_settings);
    $this->drupalGet('<front>');
    $this->assertSession()->pageTextContains('Newsletter Unsubscription Block');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');
  }

}
