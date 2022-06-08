<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Functional;

use Behat\Mink\Element\NodeElement;
use Drupal\oe_newsroom_newsletter_mock\Plugin\ServiceMock\NewsroomPlugin;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom_newsletter\Traits\NewsroomClientMockTrait;
use Drupal\Tests\oe_newsroom_newsletter\Traits\NewsroomNewsletterTrait;
use Drupal\user\Entity\Role;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the unsubscribe block.
 */
class UnsubscribeBlockTest extends BrowserTestBase {

  /**
   * The CSS selector for the unsubscribe block.
   */
  protected const BLOCK_CSS_SELECTOR = 'div.oe-newsroom-newsletter-unsubscribe-form';

  use NewsroomClientMockTrait;
  use NewsroomNewsletterTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
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
    $this->drupalGet('/admin/structure/block/add/oe_newsroom_newsletter_unsubscription_block/stark', [
      'query' => ['region' => 'sidebar_first'],
    ]);

    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
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

    // Edit the block.
    $this->drupalGet('/admin/structure/block/manage/newsletterunsubscriptionblock');
    // Set partial values for the third and fourth Sv ID/Name pairs.
    // The second one is left blank to verify that the correct deltas are
    // marked by the validation errors.
    $sv_id_fields[3]->setValue('5555');
    $dist_name_fields[4]->setValue('Another distribution list');
    $page->pressButton('Save block');
    $assert_session->pageTextContains('Both sv IDs and name are required.');
    // The elements of the two pairs have been marked with the validation error.
    $has_error_fn = fn (NodeElement $element) => $element->hasClass('error');
    $this->assertTrue($has_error_fn($sv_id_fields[3]));
    $this->assertTrue($has_error_fn($dist_name_fields[3]));
    $this->assertTrue($has_error_fn($sv_id_fields[4]));
    $this->assertTrue($has_error_fn($dist_name_fields[4]));
    // Check that only these 2 pairs have been marked with a violation.
    $this->assertCount(2, array_filter($sv_id_fields, $has_error_fn));
    $this->assertCount(2, array_filter($dist_name_fields, $has_error_fn));

    // Set more than 5 total sv IDs across all pairs.
    $sv_id_fields[3]->setValue('5555, 12000');
    $dist_name_fields[3]->setValue('Two newsletters');
    $sv_id_fields[4]->setValue('5555,777,888,9999');
    $page->pressButton('Save block');
    $assert_session->pageTextContains('Too many sv IDs specified between all distribution lists. Maximum 5 allowed, 6 found.');

    $sv_id_fields[4]->setValue('5555,777,888');
    $page->pressButton('Save block');
    $assert_session->pageTextContains('The block configuration has been saved');

    /** @var \Drupal\block\Entity\Block $block */
    $block = \Drupal::entityTypeManager()->getStorage('block')->load('newsletterunsubscriptionblock');
    $settings = $block->get('settings');
    $this->assertEquals([
      [
        'sv_id' => '00001',
        'name' => 'First distribution list',
      ],
      [
        'sv_id' => '5555, 12000',
        'name' => 'Two newsletters',
      ],
      [
        'sv_id' => '5555,777,888',
        'name' => 'Another distribution list',
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
    $this->placeNewsletterUnsubscriptionBlock();
    $this->drupalGet('<front>');

    // Without permissions nor configuration, the block is not accessible.
    $assert_session = $this->assertSession();
    $assert_session->elementNotExists('css', self::BLOCK_CSS_SELECTOR);

    // No access to the block if the current user doesn't have permission.
    $this->configureNewsroom();
    $this->drupalGet('<front>');
    $assert_session->elementNotExists('css', self::BLOCK_CSS_SELECTOR);

    $this->drupalLogin($this->createUser(['unsubscribe from newsroom newsletters']));
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
    $block = \Drupal::entityTypeManager()->getStorage('block')->load('unsubscribe');
    $settings = $block->get('settings');
    $settings['distribution_lists'] = [];
    $block->set('settings', $settings)->save();
    $this->drupalGet('<front>');
    $assert_session->elementNotExists('css', self::BLOCK_CSS_SELECTOR);
  }

  /**
   * Tests the form.
   */
  public function testUnsubscribeForm(): void {
    \Drupal::state()->set(NewsroomPlugin::STAKE_KEY_VALIDATE_UNSUBSCRIPTIONS, FALSE);

    $this->setApiPrivateKey();
    $this->configureNewsroom();
    $this->placeNewsletterUnsubscriptionBlock();

    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'unsubscribe from newsroom newsletters',
    ]);

    $this->drupalGet('<front>');
    $assert_session = $this->assertSession();
    $block_wrapper = $assert_session->elementExists('css', self::BLOCK_CSS_SELECTOR);
    $this->assertEquals('', $assert_session->fieldExists('Your e-mail')->getValue());

    // Only one distribution list is configured, so the newsletter selection is
    // not rendered.
    $assert_session->fieldNotExists('Newsletters', $block_wrapper);

    // Check that the e-mail field is of the correct type.
    $page = $this->getSession()->getPage();
    $page->fillField('Your e-mail', 'wrongemail');
    $block_wrapper->pressButton('Unsubscribe');
    $assert_session->pageTextContains('The email address wrongemail is not valid.');

    // No request should have reached the (mocked) Newsroom service.
    $this->assertEmpty($this->getNewsroomClientRequests());

    $page->fillField('Your e-mail', 'test@example.com');
    $block_wrapper->pressButton('Unsubscribe');
    $assert_session->pageTextContains('Successfully unsubscribed!');

    // The form was successfully submitted, so one request to Newsroom service
    // should have been made.
    $this->assertCount(1, $this->getNewsroomClientRequests());
    $this->assertLastUnsubscribeRequest('111', 'test@example.com');

    $user = $this->createUser(['unsubscribe from newsroom newsletters']);
    $this->drupalLogin($user);
    $this->drupalGet('<front>');
    // The e-mail field is prefilled with the current user e-mail.
    $this->assertEquals($user->getEmail(), $assert_session->fieldExists('Your e-mail')->getValue());

    $assert_session->fieldNotExists('Newsletters', $block_wrapper);
    // Replace the pre-filled user e-mail with a custom one. This allows to
    // assert that the one sent through the form is always used.
    $page->fillField('Your e-mail', 'anothertest@example.com');
    $block_wrapper->pressButton('Unsubscribe');
    $assert_session->pageTextContains('Successfully unsubscribed!');

    // Assert that 1 new request have been made, with the expected data.
    $this->assertCount(2, $this->getNewsroomClientRequests());
    $this->assertLastUnsubscribeRequest('111', 'anothertest@example.com');

    // Simulate a 404 from the Newsroom service.
    $this->setNextNewsroomClientResponse(new Response(404));
    $block_wrapper->pressButton('Unsubscribe');
    // Test that the block shows a courtesy message when errors occur.
    $assert_session->pageTextContains('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.');
    $this->assertCount(3, $this->getNewsroomClientRequests());

    // Simulate another response which is not a 200.
    $this->setNextNewsroomClientResponse(new Response(500, []));
    $this->drupalGet('<front>');
    $block_wrapper->pressButton('Unsubscribe');
    $assert_session->pageTextContains('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.');
    $this->assertCount(4, $this->getNewsroomClientRequests());

    // Clean the stored requests to ease the assertions later.
    $this->clearNewsroomClientRequests();

    // Add more distribution lists to the block configuration.
    $block = \Drupal::entityTypeManager()->getStorage('block')->load('unsubscribe');
    $settings = $block->get('settings');
    $second_list = $this->randomMachineName();
    $settings['distribution_lists'][] = [
      'sv_id' => '01011,2222',
      'name' => $second_list,
    ];
    $block->set('settings', $settings)->save();

    $this->drupalGet('<front>');
    $assert_session->elementExists('named_exact', ['fieldset', 'Newsletters'], $block_wrapper);
    $assert_session->checkboxNotChecked('Newsletter 1', $block_wrapper);
    $assert_session->checkboxNotChecked($second_list, $block_wrapper);

    // Verify that at least one distribution list must be selected.
    $block_wrapper->pressButton('Unsubscribe');
    $assert_session->pageTextContains('Newsletters field is required.');

    // Select the distribution list that points to two sv IDs.
    $page->checkField($second_list);
    $block_wrapper->pressButton('Unsubscribe');
    $assert_session->pageTextContains('Successfully unsubscribed!');
    $requests = $this->getNewsroomClientRequests();
    // A separate request for each sv ID has been made.
    $this->assertCount(2, $requests);
    $this->assertUnsubscribeRequest('01011', strtolower($user->getEmail()), $requests[0]);
    $this->assertUnsubscribeRequest('2222', strtolower($user->getEmail()), $requests[1]);

    // Select now both the distribution lists.
    $this->clearNewsroomClientRequests();
    $page->checkField('Newsletter 1');
    $page->checkField($second_list);
    $block_wrapper->pressButton('Unsubscribe');
    $assert_session->pageTextContains('Successfully unsubscribed!');
    $requests = $this->getNewsroomClientRequests();
    $this->assertCount(3, $requests);
    $this->assertUnsubscribeRequest('111', strtolower($user->getEmail()), $requests[0]);
    $this->assertUnsubscribeRequest('01011', strtolower($user->getEmail()), $requests[1]);
    $this->assertUnsubscribeRequest('2222', strtolower($user->getEmail()), $requests[2]);
  }

  /**
   * Asserts values passed in the last request to the unsubscribe endpoint.
   *
   * We are in a browser test so we cannot mock the Newsroom client to assert
   * the parameters it is being invoked with. We instead check the request
   * that is sent to the mocked Newsroom newsletter endpoint.
   *
   * @param string $sv_id
   *   The expected sv ID.
   * @param string $email
   *   The expected email.
   */
  protected function assertLastUnsubscribeRequest(string $sv_id, string $email): void {
    $requests = $this->getNewsroomClientRequests();
    $this->assertUnsubscribeRequest($sv_id, $email, array_pop($requests));
  }

  /**
   * Asserts values to a unsubscribe endpoint request.
   *
   * @param string $sv_id
   *   The expected sv ID.
   * @param string $email
   *   The expected email.
   * @param string $request_string
   *   A string representation of a request.
   */
  protected function assertUnsubscribeRequest(string $sv_id, string $email, string $request_string): void {
    $request = Message::parseRequest($request_string);
    parse_str($request->getUri()->getQuery(), $query);

    $this->assertEquals($sv_id, $query['sv_id']);
    $this->assertEquals($email, $query['user_email']);
  }

}
