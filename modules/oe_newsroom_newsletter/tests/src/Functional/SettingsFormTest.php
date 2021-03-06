<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Behat\Mink\Exception\ResponseTextException;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\oe_newsroom_newsletter\NewsroomNewsletter;
use Drupal\Tests\BrowserTestBase;

/**
 * Tests the newsletter configuration form.
 *
 * @group oe_newsroom_newsletter
 */
class SettingsFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom_newsletter',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Test the Newsroom settings form.
   */
  public function testSettingsForm(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();

    // Anonymous doesn't have access to the page.
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->assertSession()->statusCodeEquals(403);

    // User without permission doesn't have access to the page.
    $this->drupalLogin($this->createUser());
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->assertSession()->statusCodeEquals(403);

    // Users with the parent module administer permission shouldn't have access.
    $this->drupalLogin($this->createUser(['administer newsroom configuration']));
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->createUser(['administer newsroom newsletter configuration']));
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');

    $page->pressButton('Save configuration');
    $assert_session->pageTextContains('Privacy URL field is required.');

    $privacy_field = $assert_session->fieldExists('Privacy URL');

    // Test the validation on the privacy URL field.
    $privacy_field->setValue('/<front>');
    $page->pressButton('Save configuration');
    $assert_session->pageTextContains('The path /<front> is invalid.');

    $privacy_field->setValue('privacy-page');
    $page->pressButton('Save configuration');
    $assert_session->pageTextContains('The specified target is invalid. Manually entered paths should start with one of the following characters: / ? #');

    $privacy_field->setValue('invalid://not-a-valid-protocol');
    $page->pressButton('Save configuration');
    $assert_session->pageTextContains('The path invalid://not-a-valid-protocol is invalid.');

    // Test a few strings to see that they are accepted as valid urls.
    $valid = [
      '<front>',
      '/privacy-page',
      'https://www.example.com/privacy',
      'https://www.example.com/privacy_[lang_code]',
    ];
    foreach ($valid as $uri) {
      $privacy_field->setValue($uri);
      $page->pressButton('Save configuration');
      try {
        $assert_session->pageTextContains('The configuration options have been saved.');
      }
      catch (ResponseTextException $exception) {
        // Rethrow the exception stating which URI was not valid.
        throw new \Exception(sprintf('Failed asserting that "%s" is a valid URI.', $uri), 0, $exception);
      }
    }

    // Validate the saved value.
    $config = $this->config(NewsroomNewsletter::CONFIG_NAME);
    $this->assertSame('https://www.example.com/privacy_[lang_code]', $config->get('privacy_uri'));
  }

  /**
   * Tests that the privacy URL is translatable.
   */
  public function testSettingsTranslation(): void {
    // To not pollute the other test method, we enable these modules only in
    // this scenario.
    \Drupal::service('module_installer')->install([
      'block',
      'config_translation',
    ]);

    $this->drupalPlaceBlock('local_tasks_block');
    $language = ConfigurableLanguage::createFromLangcode('it');
    $language->save();

    $this->drupalLogin($this->createUser([
      'administer newsroom newsletter configuration',
      'translate configuration',
    ]));
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->clickLink('Translate newsroom newsletter settings form');
    $this->assertSession()->addressEquals('/admin/config/system/newsroom-settings/newsletter/translate');

    $assert_session = $this->assertSession();
    $assert_session->elementExists('xpath', '//table/tbody/tr[./td[1][.="English (original)"]]');
    $it_row = $assert_session->elementExists('xpath', '//table/tbody/tr[./td[1][.="Italian"]]');
    $it_row->clickLink('Add');
    // Checking that the field is present in the page is enough. The
    // configuration translation capability comes from core, we just care that
    // our field is marked as translatable.
    $assert_session->fieldExists('Privacy URL');
  }

}
