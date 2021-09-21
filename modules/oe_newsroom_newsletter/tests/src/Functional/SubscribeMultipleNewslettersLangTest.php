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

    // Add the language.
    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['manage own newsletter subscription']);
  }

  /**
   * Test a user subscription to multiple newsletters in a different language.
   *
   * @group oe_newsroom_newsletter
   */
  public function testSubscribeMultipleNewslettersLang(): void {
    $this->drupalGet('newsletter/subscribe');
    $this->assertSession()->pageTextContains('Subscribe for newsletter');
    $this->assertSession()->pageTextContains('This is the introduction text.');
    $this->getSession()->getPage()->fillField('Your e-mail', 'mail@example.com');
    $this->assertSession()->pageTextContains('Newsletter lists');
    $this->getSession()->getPage()->checkField('Newsletter collection 1');
    $this->getSession()->getPage()->checkField('Newsletter 2');
    $this->assertSession()->pageTextContains('Please select which newsletter list interests you.');
    $this->getSession()->getPage()->selectFieldOption('Select the language of your received newsletter', 'German');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Thanks for Signing Up to the service: Test Newsletter Service');
  }

}
