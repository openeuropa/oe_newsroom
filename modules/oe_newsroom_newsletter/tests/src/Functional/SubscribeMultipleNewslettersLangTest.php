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
class SubscribeMultipleNewslettersLangTest extends BrowserTestBase {

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

    // Add the language.
    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsletter',
      'unsubscribe from newsletter',
    ]);
    $this->createNewsletterPages(TRUE);
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

    $this->drupalGet($this->subscribePath);
    $assertSession->pageTextContains('Subscribe to newsletter');
    $assertSession->pageTextContains('This is the introduction text.');
    $page->fillField('Your e-mail', 'mail@example.com');
    $assertSession->pageTextContains('Newsletter lists');
    $page->checkField('Newsletter collection 1');
    $page->checkField('Newsletter 2');
    $assertSession->pageTextContains('Please select which newsletter list interests you.');
    $page->selectFieldOption('Select the language of your received newsletter', 'German');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');
  }

}
