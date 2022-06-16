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
class NewsletterConfigurationLanguageTest extends BrowserTestBase {

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
    $this->placeNewsletterSubscriptionBlock([], TRUE);
    $this->placeNewsletterUnsubscriptionBlock([], TRUE);
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsroom newsletters',
      'unsubscribe from newsroom newsletters',
    ]);
    $this->user = $this->createUser([
      'administer blocks',
      'administer newsroom newsletter configuration',
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

    // Add the language.
    $language = ConfigurableLanguage::createFromLangcode('de');
    $language->save();
    $this->drupalLogout();

    // @todo Find better way.
    drupal_flush_all_caches();

    // Test if both languages are displayed on subscribe form.
    $this->drupalGet('<front>');
    $assertSession->optionExists('Select the language in which you want to receive the newsletters', 'English');
    $assertSession->optionExists('Select the language in which you want to receive the newsletters', 'German');

    // Test if both languages are available in the newsletter settings.
    $this->drupalLogin($this->user);
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter/translate');
    $assertSession->pageTextNotContains('In order to translate configuration, the website must have at least two languages.');
    $assertSession->pageTextContains('English (original)');
    $assertSession->pageTextContains('German');

    // Set German translations in the newsletter settings.
    $page->clickLink('Add');
    $page->fillField('Privacy URL', '/de/privacy-uri');
    $page->pressButton('Save translation');
    $assertSession->pageTextContains('Successfully saved German translation.');

    // Set German translations to subscribe block settings.
    $this->drupalGet('admin/structure/block/manage/subscribe/translate');
    $page->clickLink('Add');
    $page->fillField('Introduction text', 'This is the introduction text. DE');
    $page->fillField('Successful subscription message', 'Success. Your email address have been subscribed to the newsletter. DE');
    $page->fillField('edit-translation-config-names-blockblocksubscribe-settings-distribution-lists-0-name', 'Newsletter collection DE (subscribe)');
    $page->fillField('edit-translation-config-names-blockblocksubscribe-settings-distribution-lists-1-name', 'Newsletter 2 DE (subscribe)');
    $page->pressButton('Save translation');

    // Set German translations to unsubscribe block settings.
    $this->drupalGet('admin/structure/block/manage/unsubscribe/translate');
    $page->clickLink('Add');
    $page->fillField('edit-translation-config-names-blockblockunsubscribe-settings-distribution-lists-0-name', 'Newsletter collection DE (unsubscribe)');
    $page->fillField('edit-translation-config-names-blockblockunsubscribe-settings-distribution-lists-1-name', 'Newsletter 2 DE (unsubscribe)');
    $page->pressButton('Save translation');
    $this->drupalLogout();
  }

}
