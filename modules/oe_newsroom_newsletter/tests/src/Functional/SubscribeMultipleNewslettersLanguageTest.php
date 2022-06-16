<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\OeNewsroomNewsletterTrait;
use Drupal\user\Entity\Role;

/**
 * Test the subscription to multiple newsletters in a different language.
 *
 * @group oe_newsroom_newsletter
 */
class SubscribeMultipleNewslettersLanguageTest extends BrowserTestBase {

  use OeNewsroomNewsletterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'config_translation',
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

    // Add the language.
    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsroom newsletters',
      'unsubscribe from newsroom newsletters',
    ]);
  }

  /**
   * Test a user subscription to multiple newsletters in a different language.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeMultipleNewslettersLang(): void {
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    $this->drupalGet('<front>');
    $assertSession->pageTextContains('Subscribe to newsletter');
    $assertSession->pageTextContains('This is the introduction text.');
    $page->fillField('Your e-mail', 'mail@example.com');
    $assertSession->pageTextContains('Newsletters');
    $page->checkField('Newsletter 1');
    $page->checkField('Newsletter collection');
    $assertSession->pageTextContains('Please select the newsletter lists you want to take an action on.');
    $page->selectFieldOption('Select the language in which you want to receive the newsletters', 'German');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Vielen Dank für Ihre Anmeldung zum Service: Test Newsletter Service');
  }

}
