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

    $this->enableMock();
    $this->configureNewsroom();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['manage own newsletter subscription']);
    $this->user = $this->createUser([
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
    // Configure newsletter.
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->assertSession()->elementAttributeContains('css', 'textarea#edit-intro-text', 'required', 'required');
    $this->assertSession()->elementAttributeContains('css', 'input#edit-privacy-uri', 'required', 'required');
    $this->getSession()->getPage()->fillField('edit-distribution-list-0-sv-id', '678');
    $this->getSession()->getPage()->fillField('edit-distribution-list-0-name', 'Example newsletter 1');
    $this->getSession()->getPage()->fillField('Introduction text', 'Example introduction.');
    $this->getSession()->getPage()->fillField('Privacy uri', '/privacy-uri');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    // Test with missing configuration translation module.
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->assertSession()->pageTextNotContains('Translate newsroom newsletter settings form');

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
    $this->drupalLogin($this->user);
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

    // Test successful unsubscription doesn't show the field.
    $this->drupalGet('newsletter/unsubscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');
    $this->assertSession()->pageTextNotContains('Your e-mail');

    // Display custom success message.
    $this->drupalGet('newsletter/subscribe');
    $this->assertSession()->pageTextNotContains('Newsletter lists');
    $this->assertSession()->pageTextNotContains('Please select which newsletter list interests you.');
    $this->assertSession()->hiddenFieldValueEquals('distribution_list', '678');
    $this->assertSession()->hiddenFieldValueEquals('newsletters_language', 'en');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->assertWaitOnAjaxRequest();
    $this->assertSession()->pageTextContains('Success. Your email address have been subscribed to the newsletter.');

    // Configure multiple newsletters.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->getSession()->getPage()->fillField('edit-distribution-list-1-sv-id', '456');
    $this->getSession()->getPage()->fillField('edit-distribution-list-1-name', 'Example newsletter 2');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->drupalLogout();

    $this->drupalGet('newsletter/subscribe');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail2@example.com');
    $this->assertSession()->pageTextContains('Newsletter lists');
    $this->assertSession()->pageTextContains('Please select which newsletter list interests you.');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->checkField('Newsletter collection 1');
    $this->getSession()->getPage()->checkField('Newsletter 2');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');

    // Unsubscribe the newsletters.
    $this->drupalGet('newsletter/unsubscribe');
    $this->assertSession()->pageTextContains('Unsubscribe from newsletter');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail2@example.com');
    $this->assertSession()->pageTextContains('Newsletter lists');
    $this->getSession()->getPage()->checkField('Newsletter collection 1');
    $this->getSession()->getPage()->checkField('Newsletter 2');
    $this->getSession()->getPage()->pressButton('Unsubscribe');
    $this->assertSession()->pageTextContains('Successfully unsubscribed!');
  }

}
