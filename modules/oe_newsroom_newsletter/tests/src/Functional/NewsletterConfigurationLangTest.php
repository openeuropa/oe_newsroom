<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\OeNewsroomNewsletterTrait;
use Drupal\user\Entity\Role;

/**
 * Test the Newsletter configuration.
 *
 * @group oe_newsroom_newsletter
 */
class NewsletterConfigurationLangTest extends BrowserTestBase {

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
    $this->configureNewsletter();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), ['manage own newsletter subscription']);
    $this->user = $this->createUser([
      'manage newsroom newsletter settings',
      'translate configuration',
    ]);
    $this->drupalLogin($this->user);
  }

  /**
   * Test the newsletter language settings.
   *
   * @group oe_newsroom_newsletter
   */
  public function testNewsletterLangSettings(): void {
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter/translate');
    $this->assertSession()->pageTextContains('In order to translate configuration, the website must have at least two languages.');
    $this->assertSession()->pageTextContains('English (original)');
    $this->assertSession()->pageTextNotContains('German');
    $this->getSession()->getPage()->hasLink('Edit');

    // Add the language.
    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    $this->drupalGet('admin/config/system/newsroom-settings/newsletter/translate');
    $this->assertSession()->pageTextNotContains('In order to translate configuration, the website must have at least two languages.');
    $this->assertSession()->pageTextContains('English (original)');
    $this->assertSession()->pageTextContains('German');

    // Not modified data will not be saved as the new translation.
    $this->getSession()->getPage()->clickLink('Add');
    $this->getSession()->getPage()->pressButton('Save translation');
    $this->assertSession()->pageTextContains('German translation was not added. To add a translation, you must modify the configuration.');

    // Add German translation.
    $this->getSession()->getPage()->clickLink('Add');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-distribution-list-0-name', 'distro1 DE');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-intro-text', 'This is the introduction text. DE');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-privacy-uri', '/privacy-uri_de');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-success-subscription-text', 'Success. Your email address have been subscribed to the newsletter. DE');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-already-registered-text', 'Failure. Your email address was already subscribed to the newsletter. DE');
    $this->getSession()->getPage()->pressButton('Save translation');
    $this->assertSession()->pageTextContains('Successfully saved German translation.');
    $this->drupalLogout();

    // Check if both languages are displayed on subscribe form.
    $this->drupalGet('newsletter/subscribe');
    $this->assertSession()->optionExists('Select the language of your received newsletter', 'English');
    $this->assertSession()->optionExists('Select the language of your received newsletter', 'German');
  }

}
