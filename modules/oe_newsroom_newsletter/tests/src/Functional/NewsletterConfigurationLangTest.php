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
    'oe_newsroom_newsletter_mock',
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

    $this->setApiPrivateKey();
    $this->configureNewsroom();
    $this->createNewsletterPages();
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsletter',
      'unsubscribe from newsletter',
    ]);
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
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-distribution-list-0-name', 'Newsletter collection 1 DE');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-distribution-list-1-name', 'Newsletter 2 DE');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-intro-text', 'This is the introduction text. DE');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-privacy-uri', '/de/privacy-uri');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-success-subscription-text', 'Success. Your email address have been subscribed to the newsletter. DE');
    $this->getSession()->getPage()->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-already-registered-text', 'Failure. Your email address was already subscribed to the newsletter. DE');
    $this->getSession()->getPage()->pressButton('Save translation');
    $this->assertSession()->pageTextContains('Successfully saved German translation.');
    $this->drupalLogout();

    // Test if both languages are displayed on subscribe form.
    $this->drupalGet($this->subscribePath);
    $this->assertSession()->optionExists('Select the language of your received newsletter', 'English');
    $this->assertSession()->optionExists('Select the language of your received newsletter', 'German');

    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->getSession()->getPage()->selectFieldOption('Select the selectable languages for newsletter', 'German');
    $this->getSession()->getPage()->selectFieldOption('Select the default language for newsletter', 'German');
    $this->getSession()->getPage()->pressButton('Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');
    $this->drupalLogout();

    // @todo Fix form cache.
    drupal_flush_all_caches();

    // Test if German language is set on subscribe form.
    $this->drupalGet($this->subscribePath);
    $this->assertSession()->pageTextNotContains('Select the language of your received newsletter');
    $this->assertSession()->hiddenFieldValueEquals('newsletters_language', 'de');

    // Test German translations on subscribe form.
    $this->drupalGet('de/' . $this->subscribePath);
    $this->assertSession()->pageTextContains('This is the introduction text. DE');
    $this->getSession()->getPage()->checkField('Newsletter collection 1 DE');
    $this->getSession()->getPage()->checkField('Newsletter 2 DE');
    $this->getSession()->getPage()->hasLink('/de/privacy-uri');
    $this->getSession()->getPage()->fillField('Your e-mail', 'de@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Success. Your email address have been subscribed to the newsletter. DE');
    $this->drupalGet('de/' . $this->subscribePath);
    $this->getSession()->getPage()->checkField('Newsletter collection 1 DE');
    $this->getSession()->getPage()->checkField('Newsletter 2 DE');
    $this->getSession()->getPage()->hasLink('/de/privacy-uri');
    $this->getSession()->getPage()->fillField('Your e-mail', 'de@example.com');
    $this->getSession()->getPage()->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $this->getSession()->getPage()->pressButton('Subscribe');
    $this->assertSession()->pageTextContains('Failure. Your email address was already subscribed to the newsletter. DE');

  }

}
