<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Behat\Mink\Exception\ResponseTextException;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;
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
    $user = $this->createUser();
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->assertSession()->statusCodeEquals(403);

    // Users with the parent module administer permission shouldn't have access.
    $user = $this->createUser(['administer newsroom configuration']);
    $this->drupalLogin($user);
    $this->drupalGet('admin/config/system/newsroom-settings/newsletter');
    $this->assertSession()->statusCodeEquals(403);

    $user = $this->createUser(['administer newsroom newsletter configuration']);
    $this->drupalLogin($user);
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
    $config = $this->config(OeNewsroomNewsletter::CONFIG_NAME);
    $this->assertSame('https://www.example.com/privacy_[lang_code]', $config->get('privacy_uri'));
  }

}
