<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Url;
use Drupal\language\Entity\ConfigurableLanguage;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\NewsroomClientMockTrait;
use Drupal\Tests\oe_newsroom_newsletter\Traits\OeNewsroomNewsletterTrait;
use Drupal\user\Entity\Role;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the subscribe block.
 */
class SubscribeBlockTest extends BrowserTestBase {

  /**
   * The CSS selector for the subscribe block.
   */
  protected const BLOCK_CSS_SELECTOR = 'div.oe-newsroom-newsletter-subscribe-form';

  use NewsroomClientMockTrait;
  use OeNewsroomNewsletterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'language',
    'oe_newsroom_newsletter_mock',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests the block configuration form.
   */
  public function testConfigurationForm(): void {
    $this->drupalLogin($this->createUser(['administer blocks']));
    $this->drupalGet('/admin/structure/block/add/oe_newsroom_newsletter_subscription_block/stark', [
      'query' => ['region' => 'sidebar_first'],
    ]);

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    // There's only one language in the system, so the related fields are hidden
    // by default.
    $expected_languages = [
      'en' => 'English',
    ];
    $this->assertEquals($expected_languages, $this->getOptions('Newsletter languages'));
    $this->assertEquals($expected_languages, $this->getOptions('Default newsletter language'));

    // Check the required fields.
    $page->pressButton('Save block');
    $assert_session->pageTextContains('Sv IDs field is required.');
    $assert_session->pageTextContains('Name of the distribution list field is required.');

    // Maximum 5 Sv ID/Name pairs should be present in the page.
    $sv_id_fields = $page->findAll('named_exact', ['field', 'Sv IDs']);
    $this->assertCount(5, $sv_id_fields);
    $dist_name_fields = $page->findAll('named_exact', [
      'field',
      'Name of the distribution list',
    ]);
    $this->assertCount(5, $dist_name_fields);

    $sv_id_fields[0]->setValue('00001');
    $dist_name_fields[0]->setValue('First distribution list');
    $page->pressButton('Save block');
    $assert_session->pageTextContains('The block configuration has been saved');

    // Add some languages to the system.
    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
    // Reload the page.
    $this->drupalGet('/admin/structure/block/manage/newslettersubscriptionblock');

    $expected_languages['it'] = 'Italian';
    $expected_languages['de'] = 'German';
    $this->assertEqualsCanonicalizing($expected_languages, $this->getOptions('Newsletter languages'));
    $this->assertEqualsCanonicalizing($expected_languages, $this->getOptions('Default newsletter language'));

    // Test that the default newsletter chosen language must be selected in the
    // allowed languages field too.
    $page->selectFieldOption('Newsletter languages', 'English');
    $page->selectFieldOption('Default newsletter language', 'Italian');
    $page->pressButton('Save block');
    $assert_session->pageTextContains('The default language should be part of the possible newsletter languages.');

    // Reload the page to deselect the values.
    $this->drupalGet('/admin/structure/block/manage/newslettersubscriptionblock');
    // Selecting a default language is allowed when no languages are selected.
    $page->selectFieldOption('Default newsletter language', 'Italian');
    $page->pressButton('Save block');
    $assert_session->pageTextContains('The block configuration has been saved');

    $this->drupalGet('/admin/structure/block/manage/newslettersubscriptionblock');
    $assert_session->fieldExists('Introduction text')->setValue('Introduction example text.');
    $assert_session->fieldExists('Successful subscription message')->setValue('Subscribed successfully.');
    $page->selectFieldOption('Newsletter languages', 'English');
    $page->selectFieldOption('Newsletter languages', 'Italian', TRUE);
    $page->findAll('named_exact', ['field', 'Sv IDs'])[1]->setValue('00002');
    $page->findAll('named_exact', ['field', 'Name of the distribution list'])[1]->setValue('Second distribution list');
    $page->pressButton('Save block');
    $assert_session->pageTextContains('The block configuration has been saved');

    /** @var \Drupal\block\Entity\Block $block */
    $block = \Drupal::entityTypeManager()->getStorage('block')->load('newslettersubscriptionblock');
    $settings = $block->get('settings');
    $this->assertEquals([
      'en',
      'it',
    ], $settings['newsletters_language']);
    $this->assertEquals('Introduction example text.', $settings['intro_text']);
    $this->assertEquals('Subscribed successfully.', $settings['successful_subscription_message']);
    $this->assertEquals([
      [
        'sv_id' => '00001',
        'name' => 'First distribution list',
      ],
      [
        'sv_id' => '00002',
        'name' => 'Second distribution list',
      ],
    ], $settings['distribution_lists']);
  }

  /**
   * Tests the access to the block.
   *
   * This test covers also the checks done during build phase and that correct
   * cache metadata information is passed.
   */
  public function testBlockAccess(): void {
    $this->setApiPrivateKey();
    $this->placeNewsletterSubscriptionBlock();
    $this->drupalGet('<front>');

    // Without permissions nor configuration, the block is not accessible.
    $assert_session = $this->assertSession();
    $assert_session->elementNotExists('css', self::BLOCK_CSS_SELECTOR);

    // No access to the block if the current user doesn't have permission.
    $this->configureNewsroom();
    $this->configureNewsletter();
    $this->drupalGet('<front>');
    $assert_session->elementNotExists('css', self::BLOCK_CSS_SELECTOR);

    $this->drupalLogin($this->createUser(['subscribe to newsroom newsletters']));
    $this->drupalGet('<front>');
    $assert_session->elementExists('css', self::BLOCK_CSS_SELECTOR);

    // The newsletter privacy field is required, or the block won't be rendered.
    $this->configureNewsletter('');
    $this->drupalGet('<front>');
    $assert_session->elementNotExists('css', self::BLOCK_CSS_SELECTOR);

    $this->configureNewsletter();
    $this->drupalGet('<front>');
    $assert_session->elementExists('css', self::BLOCK_CSS_SELECTOR);

    // The Newsroom client must be fully configured, or the block won't render.
    $this->configureNewsroom(['app_id' => '']);
    $this->drupalGet('<front>');
    $assert_session->elementNotExists('css', self::BLOCK_CSS_SELECTOR);

    $this->configureNewsroom();
    $this->drupalGet('<front>');
    $assert_session->elementExists('css', self::BLOCK_CSS_SELECTOR);

    // The block won't be rendered if no distribution lists are configured.
    $block = \Drupal::entityTypeManager()->getStorage('block')->load('subscribe');
    $settings = $block->get('settings');
    $settings['distribution_lists'] = [];
    $block->set('settings', $settings)->save();
    $this->drupalGet('<front>');
    $assert_session->elementNotExists('css', self::BLOCK_CSS_SELECTOR);
  }

  /**
   * Tests the form.
   */
  public function testSubscribeForm(): void {
    $this->setApiPrivateKey();
    $this->configureNewsroom();
    $this->configureNewsletter();
    $this->placeNewsletterSubscriptionBlock();

    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsroom newsletters',
    ]);

    $this->drupalGet('<front>');
    $assert_session = $this->assertSession();
    $block_wrapper = $assert_session->elementExists('css', self::BLOCK_CSS_SELECTOR);
    $this->assertStringContainsString('This is the introduction text.', $block_wrapper->getText());
    $assert_session->checkboxNotChecked('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement', $block_wrapper);
    $privacy_link = $block_wrapper->find('named_exact', [
      'link',
      'privacy statement',
    ]);
    $this->assertNotEmpty($privacy_link);
    $expected_url = Url::fromUserInput('/privacy-url')->toString();
    $this->assertEquals($expected_url, $privacy_link->getAttribute('href'));
    $this->assertEquals('', $assert_session->fieldExists('Your e-mail')->getValue());
    $assert_session->elementNotExists('named', [
      'select',
      'Select the language in which you want to receive the newsletters',
    ], $block_wrapper);

    // Assert the required fields.
    $block_wrapper->pressButton('Subscribe');
    $assert_session->pageTextContains('Your e-mail field is required.');
    $assert_session->pageTextContains('You must agree with the privacy statement.');

    $page = $this->getSession()->getPage();
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');

    // Check that the e-mail field is of the correct type.
    $page->fillField('Your e-mail', 'wrongemail');
    $block_wrapper->pressButton('Subscribe');
    $assert_session->pageTextContains('The email address wrongemail is not valid.');

    // No request should have reached the (mocked) Newsroom service.
    $this->assertEmpty($this->getNewsroomClientRequests());

    $page->fillField('Your e-mail', 'test@example.com');
    $block_wrapper->pressButton('Subscribe');
    // Since the block has no success message configured, the one contained in
    // the (mocked) Newsroom response is returned.
    $assert_session->pageTextContains('Thanks for signing up to the service: Test Newsletter Service');

    // The form was successfully submitted, so one request to Newsroom service
    // should have been made.
    $this->assertCount(1, $this->getNewsroomClientRequests());
    $this->assertLastSubscribeRequest('111', 'test@example.com', 'en');

    $user = $this->createUser(['subscribe to newsroom newsletters']);
    $this->drupalLogin($user);
    $this->drupalGet('<front>');
    // The e-mail field is prefilled with the current user e-mail.
    $this->assertEquals($user->getEmail(), $assert_session->fieldExists('Your e-mail')->getValue());

    // Customise some strings with markup.
    $block = \Drupal::entityTypeManager()->getStorage('block')->load('subscribe');
    $block->set('settings', [
      'intro_text' => 'Introduction text with <em>some</em> markup <script type="text/javascript">malicious</script>',
      'successful_subscription_message' => 'You have successfully <em>subscribed</em> to the <script type="text/javascript">newsletter!</script>',
    ] + $block->get('settings'))->save();

    $this->drupalGet('<front>');
    // Check that introduction text and success messages are properly escaped.
    $this->assertStringContainsString('Introduction text with <em>some</em> markup <script type="text/javascript">malicious</script>', $block_wrapper->getText());
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    // Replace the pre-filled user e-mail with a custom one. This allows to
    // assert that the one sent through the form is always used.
    $page->fillField('Your e-mail', 'anothertest@example.com');
    $block_wrapper->pressButton('Subscribe');
    $assert_session->pageTextContains('You have successfully <em>subscribed</em> to the <script type="text/javascript">newsletter!</script>');

    // Assert that 1 new request have been made, with the expected data.
    $this->assertCount(2, $this->getNewsroomClientRequests());
    $this->assertLastSubscribeRequest('111', 'anothertest@example.com', 'en');

    // Simulate a 404 from the Newsroom service.
    $this->setNextNewsroomClientResponse(new Response(404));
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $block_wrapper->pressButton('Subscribe');
    // Test that the block shows a courtesy message when errors occur.
    $assert_session->pageTextContains('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.');
    $this->assertCount(3, $this->getNewsroomClientRequests());

    // Simulate a broken response.
    $this->setNextNewsroomClientResponse(new Response(200, [], '[]'));
    $this->drupalGet('<front>');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $block_wrapper->pressButton('Subscribe');
    $assert_session->pageTextContains('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.');
    $this->assertCount(4, $this->getNewsroomClientRequests());

    // Add more distribution lists to the block configuration.
    $settings = $block->get('settings');
    $second_list = $this->randomMachineName();
    $settings['distribution_lists'][] = [
      'sv_id' => '01011,2222',
      'name' => $second_list,
    ];
    // Simplify the success message for later assertions.
    $settings['successful_subscription_message'] = 'You have been subscribed successfully.';
    $block->set('settings', $settings)->save();

    $this->drupalGet('<front>');
    $assert_session->elementExists('named_exact', ['fieldset', 'Newsletters'], $block_wrapper);
    $assert_session->checkboxNotChecked('Newsletter 1', $block_wrapper);
    $assert_session->checkboxNotChecked($second_list, $block_wrapper);

    // Verify that at least one distribution list must be selected.
    $block_wrapper->pressButton('Subscribe');
    $assert_session->pageTextContains('Newsletters field is required.');

    // Select the distribution list that points to two sv IDs.
    $page->checkField($second_list);
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $block_wrapper->pressButton('Subscribe');
    $assert_session->pageTextContains('You have been subscribed successfully.');
    $this->assertCount(5, $this->getNewsroomClientRequests());
    $this->assertLastSubscribeRequest('01011,2222', strtolower($user->getEmail()), 'en');

    // Select now both the distribution lists.
    $page->checkField('Newsletter 1');
    $page->checkField($second_list);
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $block_wrapper->pressButton('Subscribe');
    $assert_session->pageTextContains('You have been subscribed successfully.');
    // Only one request has been made, containing all the sv IDs of each
    // distribution list.
    $this->assertCount(6, $this->getNewsroomClientRequests());
    $this->assertLastSubscribeRequest('111,01011,2222', strtolower($user->getEmail()), 'en');
  }

  /**
   * Test the form when multiple system languages are available.
   */
  public function testSubscribeFormLanguages(): void {
    $this->setApiPrivateKey();
    $this->configureNewsroom();
    $this->configureNewsletter();

    // Add some languages to the system.
    ConfigurableLanguage::createFromLangcode('it')->save();
    ConfigurableLanguage::createFromLangcode('de')->save();
    ConfigurableLanguage::createFromLangcode('fr')->save();

    $this->placeNewsletterSubscriptionBlock([], TRUE);
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'subscribe to newsroom newsletters',
    ]);

    $this->drupalGet('<front>');
    $assert_session = $this->assertSession();
    $block_wrapper = $assert_session->elementExists('css', self::BLOCK_CSS_SELECTOR);
    $language_select = $assert_session->selectExists('Select the language in which you want to receive the newsletters', $block_wrapper);
    // Since no languages are specified in the block, all languages are
    // selectable.
    $this->assertEquals([
      'en' => 'English',
      'it' => 'Italian',
      'de' => 'German',
      'fr' => 'French',
    ], $this->getOptions($language_select));
    // Since the anonymous user has no preferred language, the default site
    // language is pre-selected.
    $this->assertEquals('en', $language_select->getValue());

    $block = \Drupal::entityTypeManager()->getStorage('block')->load('subscribe');
    $settings = $block->get('settings');
    // Set a default newsletter language. This value is used only when the
    // current site language or the user preferred language are not available.
    $settings['newsletters_language_default'] = 'it';
    $block->set('settings', $settings)->save();
    $this->drupalGet('<front>');
    $this->assertEquals('en', $language_select->getValue());

    // Limit the allowed choice of languages.
    $settings['newsletters_language'] = ['it', 'de', 'fr'];
    $block->set('settings', $settings)->save();
    $this->drupalGet('<front>');
    $this->assertEquals([
      'it' => 'Italian',
      'de' => 'German',
      'fr' => 'French',
    ], $this->getOptions($language_select));
    // The default language configured in the block is now the default option,
    // as English is not a valid choice anymore.
    $this->assertEquals('it', $language_select->getValue());

    // Create a user with German as preferred language.
    $user = $this->createUser(['subscribe to newsroom newsletters'], NULL, FALSE, [
      'preferred_langcode' => 'de',
    ]);
    $this->drupalLogin($user);
    $this->drupalGet('<front>');
    // The user preferred language is the default selected option.
    $this->assertEquals('de', $language_select->getValue());
    // The languages configured in the block are still available.
    $this->assertEquals([
      'it' => 'Italian',
      'de' => 'German',
      'fr' => 'French',
    ], $this->getOptions($language_select));

    $page = $this->getSession()->getPage();
    $block_wrapper->checkField('Newsletter collection');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $block_wrapper->pressButton('Subscribe');
    // The message is presented in the chosen user language if returned from the
    // API.
    $assert_session->pageTextContains('Vielen Dank für Ihre Anmeldung zum Service: Test Newsletter Service');
    $this->assertCount(1, $this->getNewsroomClientRequests());
    $this->assertLastSubscribeRequest('222,333', strtolower($user->getEmail()), 'de');

    // Choose French as subscription language.
    $this->drupalGet('<front>');
    $block_wrapper->checkField('Newsletter 1');
    $page->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $block_wrapper->selectFieldOption('Select the language in which you want to receive the newsletters', 'French');
    $block_wrapper->pressButton('Subscribe');
    // The mocked endpoint response doesn't contain a message for French, so it
    // falls back to a default message.
    $assert_session->pageTextContains('You have been successfully subscribed.');
    $this->assertCount(2, $this->getNewsroomClientRequests());
    $this->assertLastSubscribeRequest('111', strtolower($user->getEmail()), 'fr');

    $this->configureNewsletter('https://www.example.com/page_[lang_code]');
    ConfigurableLanguage::createFromLangcode('pt-pt')->save();
    $this->drupalGet('/pt-pt');
    $privacy_link = $this->getSession()->getPage()->find('named_exact', [
      'link',
      'privacy statement',
    ]);
    $this->assertNotEmpty($privacy_link);
    $this->assertEquals('https://www.example.com/page_pt', $privacy_link->getAttribute('href'));
  }

  /**
   * Asserts values passed in the last request to the newsletter endpoint.
   *
   * We are in a browser test so we cannot mock the Newsroom client to assert
   * the parameters it is being invoked with. We instead check the request
   * that is sent to the mocked Newsroom newsletter endpoint.
   *
   * @param string $sv_id
   *   The expected sv ID.
   * @param string $email
   *   The expected email.
   * @param string $language
   *   The expected language.
   */
  protected function assertLastSubscribeRequest(string $sv_id, string $email, string $language): void {
    $requests = $this->getNewsroomClientRequests();
    $request = Message::parseRequest(array_pop($requests));
    $body = Json::decode((string) $request->getBody());

    $this->assertEquals($sv_id, $body['subscription']['sv_id']);
    $this->assertEquals($email, $body['subscription']['email']);
    $this->assertEquals($language, $body['subscription']['language']);
  }

}
