<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\oe_newsroom_newsletter_mock\Plugin\ServiceMock\NewsroomPlugin;
use Drupal\Tests\oe_newsroom\NewsroomTestTrait;
use Drupal\Tests\oe_newsroom_newsletter\Traits\NewsroomClientMockTrait;
use Drupal\Tests\oe_newsroom_newsletter\Traits\NewsroomNewsletterTestTrait;
use Drupal\user\Entity\Role;
use GuzzleHttp\Psr7\Response;

/**
 * Tests the AJAX integration of the subscribe and unsubscribe forms.
 */
class BlockFormsAjaxTest extends WebDriverTestBase {

  use NewsroomClientMockTrait;
  use NewsroomNewsletterTestTrait;
  use NewsroomTestTrait;

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
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->setApiPrivateKey();
    $this->configureNewsroom();
    $this->configureNewsletter();
  }

  /**
   * Tests the AJAX integration of the subscribe form.
   */
  public function testSubscribeFormAjax(): void {
    $this->placeNewsletterSubscriptionBlock(['id' => 'subscribe_one']);
    $this->placeNewsletterSubscriptionBlock(['id' => 'subscribe_two']);
    $this->drupalLogin($this->createUser(['subscribe to newsroom newsletters']));
    $this->drupalget('<front>');

    // Press the submit button in the first block. The validation error messages
    // should rendered.
    $assert_session = $this->assertSession();
    $block_one = $assert_session->elementExists('css', '#block-subscribe-one');
    $block_one->pressButton('Subscribe');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '#block-subscribe-one', 'You must agree with the privacy statement.');
    // The other block wasn't impacted. This is to cover that the ID generated
    // in the form is unique.
    $assert_session->elementTextNotContains('css', '#block-subscribe-two', 'You must agree with the privacy statement.');

    $block_one->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $block_one->pressButton('Subscribe');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '#block-subscribe-one', 'Thanks for signing up to the service: Test Newsletter Service');
    // The whole form has been removed.
    $assert_session->elementNotExists('css', 'form', $block_one);

    // Simulate that the next response contains an error.
    $this->setNextNewsroomClientResponse(new Response(500));

    $block_two = $assert_session->elementExists('css', '#block-subscribe-two');
    $block_two->checkField('By checking this box, I confirm that I want to register for this service, and I agree with the privacy statement');
    $block_two->pressButton('Subscribe');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '#block-subscribe-two', 'An error occurred while processing your request, please try again later. If the error persists, contact the site owner.');
    // The whole form has been removed.
    $assert_session->elementNotExists('css', 'form', $block_two);
  }

  /**
   * Tests the AJAX integration of the unsubscribe form.
   */
  public function testUnsubscribeFormAjax(): void {
    \Drupal::state()->set(NewsroomPlugin::STAKE_KEY_VALIDATE_UNSUBSCRIPTIONS, FALSE);

    $this->placeNewsletterUnsubscriptionBlock(['id' => 'unsubscribe_one']);
    $this->placeNewsletterUnsubscriptionBlock(['id' => 'unsubscribe_two']);
    $this->grantPermissions(Role::load(Role::ANONYMOUS_ID), [
      'unsubscribe from newsroom newsletters',
    ]);
    $this->drupalget('<front>');

    // Press the submit button in the first block. The validation error messages
    // should rendered.
    $assert_session = $this->assertSession();
    $block_one = $assert_session->elementExists('css', '#block-unsubscribe-one');
    $block_one->pressButton('Unsubscribe');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '#block-unsubscribe-one', 'Your e-mail field is required.');
    // The other block wasn't impacted. This is to cover that the ID generated
    // in the form is unique.
    $assert_session->elementTextNotContains('css', '#block-unsubscribe-two', 'Your e-mail field is required.');

    $block_one->fillField('Your e-mail', 'test@example.com');
    $block_one->pressButton('Unsubscribe');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '#block-unsubscribe-one', 'Successfully unsubscribed!');
    // The whole form has been removed.
    $assert_session->elementNotExists('css', 'form', $block_one);

    // Simulate that the next response contains an error.
    $this->setNextNewsroomClientResponse(new Response(500));

    $block_two = $assert_session->elementExists('css', '#block-unsubscribe-two');
    $block_two->fillField('Your e-mail', 'test@example.com');
    $block_two->pressButton('Unsubscribe');
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->elementTextContains('css', '#block-unsubscribe-two', 'An error occurred while processing your request, please try again later. If the error persists, contact the site owner.');
    // The whole form has been removed.
    $assert_session->elementNotExists('css', 'form', $block_two);
  }

}
