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
    'node',
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
    $this->configureNewsletter();
    $this->createNewsletterPages(TRUE);
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsletter',
      'unsubscribe from newsletter',
    ]);
    $this->user = $this->createUser([
      'administer blocks',
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
    $assertSession = $this->assertSession();
    $session = $this->getSession();
    $page = $session->getPage();

    $this->drupalGet('admin/config/system/newsroom-settings/newsletter/translate');
    $assertSession->pageTextContains('In order to translate configuration, the website must have at least two languages.');
    $assertSession->pageTextContains('English (original)');
    $assertSession->pageTextNotContains('German');
    $page->hasLink('Edit');
    $this->drupalLogout();

    // Add the language.
    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();

    // Test if both languages are displayed on subscribe form.
    $this->drupalGet($this->subscribePath);
    $assertSession->optionExists('Select the language of your received newsletter', 'English');
    $assertSession->optionExists('Select the language of your received newsletter', 'German');

    // Set only German language on subscribe form.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/structure/block/manage/subscribetonewsletter');
    $page->selectFieldOption('Select the selectable languages for newsletter', 'German');
    $page->selectFieldOption('Select the default language for newsletter', 'German');
    $page->pressButton('Save block');
    $assertSession->pageTextContains('The block configuration has been saved.');
    $this->drupalLogout();

    // Test if German language is set on subscribe form.
    $this->drupalGet($this->subscribePath);
    $assertSession->pageTextNotContains('Select the language of your received newsletter');
    $assertSession->hiddenFieldValueEquals('newsletters_language', 'de');

    // Test if both languages are available in the newsletter settings.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter/translate');
    $assertSession->pageTextNotContains('In order to translate configuration, the website must have at least two languages.');
    $assertSession->pageTextContains('English (original)');
    $assertSession->pageTextContains('German');

    // Not modified data will not be saved as the new translation.
    $page->clickLink('Add');
    $page->pressButton('Save translation');
    $assertSession->pageTextContains('German translation was not added. To add a translation, you must modify the configuration.');

    // Set German translations in the newsletter settings.
    $page->clickLink('Add');
    $page->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-intro-text', 'This is the introduction text. DE');
    $page->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-privacy-uri', '/de/privacy-uri');
    $page->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-success-subscription-text', 'Success. Your email address have been subscribed to the newsletter. DE');
    $page->fillField('edit-translation-config-names-oe-newsroom-newslettersettings-already-registered-text', 'Failure. Your email address was already subscribed to the newsletter. DE');
    $page->pressButton('Save translation');
    $assertSession->pageTextContains('Successfully saved German translation.');

    // Set German translations to subscribe block settings.
    $this->drupalGet('admin/structure/block/manage/subscribetonewsletter/translate');
    $page->clickLink('Add');
    $page->fillField('edit-translation-config-names-blockblocksubscribetonewsletter-settings-distribution-list-0-name', 'Newsletter collection 1 DE (subscribe)');
    $page->fillField('edit-translation-config-names-blockblocksubscribetonewsletter-settings-distribution-list-1-name', 'Newsletter 2 DE (subscribe)');
    $page->pressButton('Save translation');

    // Set German translations to unsubscribe block settings.
    $this->drupalGet('admin/structure/block/manage/unsubscribefromnewsletter/translate');
    $page->clickLink('Add');
    $page->fillField('edit-translation-config-names-blockblockunsubscribefromnewsletter-settings-distribution-list-0-name', 'Newsletter collection 1 DE (unsubscribe)');
    $page->fillField('edit-translation-config-names-blockblockunsubscribefromnewsletter-settings-distribution-list-1-name', 'Newsletter 2 DE (unsubscribe)');
    $page->pressButton('Save translation');
    $this->drupalLogout();

    // Test German translations on subscribe form.
    $this->drupalGet('de/' . $this->subscribePath);
    $assertSession->pageTextContains('This is the introduction text. DE');
    $page->fillField('Your e-mail', 'de@example.com');
    $page->checkField('Newsletter collection 1 DE (subscribe)');
    $page->checkField('Newsletter 2 DE (subscribe)');
    $page->hasLink('/de/privacy-uri');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Success. Your email address have been subscribed to the newsletter. DE');
    $this->drupalGet('de/' . $this->subscribePath);
    $page->fillField('Your e-mail', 'de@example.com');
    $page->checkField('Newsletter collection 1 DE (subscribe)');
    $page->checkField('Newsletter 2 DE (subscribe)');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $page->pressButton('Subscribe');
    $assertSession->pageTextContains('Failure. Your email address was already subscribed to the newsletter. DE');

    // Test German translations on unsubscribe form.
    $this->drupalGet('de/' . $this->unsubscribePath);
    $page->fillField('Your e-mail', 'de@example.com');
    $page->checkField('Newsletter collection 1 DE (unsubscribe)');
    $page->checkField('Newsletter 2 DE (unsubscribe)');
    $page->pressButton('Unsubscribe');
    $assertSession->pageTextContains('Successfully unsubscribed!');
  }

}
