<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom_node\Functional;

use Drupal\Core\Url;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom\Traits\LocalTestValuesTrait;
use Drupal\Tests\oe_newsroom\Traits\NodeSubscriptionVcrTrait;
use Drupal\Tests\oe_newsroom\Traits\VcrTrait;
use Drupal\user\Entity\Role;
use Drupal\user\RoleInterface;

/**
 * Tests the node subscription block visibility and permissions.
 *
 * @group oe_newsroom_node
 */
class NodeSubscribeBlockTest extends BrowserTestBase {

  use LocalTestValuesTrait;
  use NodeSubscriptionVcrTrait;
  use VcrTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom_node_test',
    'oe_newsroom_vcr',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (version_compare(\Drupal::VERSION, '11.3', '<')) {
      $this->markTestSkipped('This test only runs in Drupal >= 11.3.');
    }

    parent::setUp();

    $this->initializeNewsroomAndVcrWithTestValues(write_settings: TRUE);

  }

  /**
   * Tests the subscription block visibility and permissions.
   */
  public function testBlockAccess(): void {
    $assert_session = $this->assertSession();
    $anonymous = Role::load(RoleInterface::ANONYMOUS_ID);

    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'My node']);

    // Without the permission, the block link is not shown.
    $anonymous->revokePermission('subscribe to newsroom node notifications')->save();
    $this->drupalGet($node->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('My node');
    $assert_session->elementNotExists('css', '#block-node-subscribe');

    // With the permission (granted by the test module), the link appears.
    $this->grantPermissions($anonymous, ['subscribe to newsroom node notifications']);
    $this->drupalGet($node->toUrl());
    $assert_session->statusCodeEquals(200);
    $assert_session->pageTextContains('My node');
    $assert_session->elementExists('css', '#block-node-subscribe');
    $link = $assert_session->elementExists('css', '.oe-newsroom-node__subscribe-link');
    $this->assertSame('Subscribe to notifications', $link->getText());
    // The link points to the modal route and uses ajax.
    $this->assertStringContainsString('/newsroom-node/' . $node->id() . '/subscribe', $link->getAttribute('href'));

    // The block is not shown on non-node pages.
    $this->drupalGet('<front>');
    $assert_session->elementNotExists('css', '#block-node-subscribe');
  }

  /**
   * Tests submitting the subscription form without JavaScript.
   */
  public function testFormWithoutJavascript(): void {
    $assert_session = $this->assertSession();
    $subscriber_email = $this->newsroomTestValues->subscriberEmail;

    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'My node']);

    // In recording mode this calls the real Newsroom API; otherwise it
    // replays the fixture named after this test method.
    $this->startVcr(__METHOD__);

    // The form is rendered on its own page at the subscribe route.
    $this->drupalGet('/newsroom-node/' . $node->id() . '/subscribe');
    $assert_session->statusCodeEquals(200);
    $assert_session->fieldExists('email');
    $assert_session->fieldExists('agree_privacy_statement');

    // Submitting without agreeing to the privacy statement shows the error.
    $this->submitForm([
      'email' => $subscriber_email,
    ], 'Subscribe');
    $assert_session->statusMessageContains('You must agree with the privacy statement.', 'error');

    // Submitting with valid values shows the confirmation message.
    $this->submitForm([
      'email' => $subscriber_email,
      'agree_privacy_statement' => 1,
    ], 'Subscribe');
    $assert_session->statusMessageContains('A confirmation email has been sent to your address. Please click the link in the email to confirm your subscription.', 'status');

    // In recording mode this writes the fixture; otherwise it verifies that
    // the tape was consumed without a mismatch.
    $this->endVcr();
  }

  /**
   * Tests that the block is hidden with an empty privacy URL.
   */
  public function testBlockWithEmptyPrivacyUrl(): void {
    $assert_session = $this->assertSession();
    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'My node']);

    $this->config('oe_newsroom_node.settings')
      ->set('privacy_url', '')
      ->save();

    $this->drupalGet($node->toUrl());
    $assert_session->elementNotExists('css', '#block-node-subscribe');
  }

  /**
   * Tests an API failure when submitting without JavaScript.
   */
  public function testFormWithoutJavascriptApiFailure(): void {
    $assert_session = $this->assertSession();
    $subscriber_email = $this->newsroomTestValues->subscriberEmail;

    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'My node']);
    $this->startFailedNodeSubscriptionReplay($node, $subscriber_email);

    $this->drupalGet('/newsroom-node/' . $node->id() . '/subscribe');
    $this->submitForm([
      'email' => $subscriber_email,
      'agree_privacy_statement' => 1,
    ], 'Subscribe');

    $assert_session->statusMessageContains('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.', 'error');
    $assert_session->statusMessageNotContains('A confirmation email has been sent to your address.');

    $this->endFailedNodeSubscriptionReplay();
  }

  /**
   * Tests the confirmation controller redirect and messages.
   */
  public function testConfirmationMessage(): void {
    $assert_session = $this->assertSession();
    $this->grantPermissions(Role::load(RoleInterface::ANONYMOUS_ID), ['subscribe to newsroom node notifications']);

    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'My node']);

    // Without the confirmation result, no message is shown.
    $this->drupalGet($node->toUrl());
    $assert_session->pageTextNotContains('You are now subscribed.');
    $assert_session->pageTextNotContains('Something went wrong while confirming your subscription.');

    // A successful verification redirects to the node page and shows a message.
    $this->drupalGet(Url::fromRoute('oe_newsroom_node.subscribe_verify', ['node' => $node->id()])->setOption('query', ['success' => 1]));
    $assert_session->addressEquals($node->toUrl());
    $assert_session->pageTextContains('You are now subscribed.');

    // A failed verification redirects to the node page and shows an error.
    $this->drupalGet(Url::fromRoute('oe_newsroom_node.subscribe_verify', ['node' => $node->id()])->setOption('query', ['success' => 0]));
    $assert_session->addressEquals($node->toUrl());
    $assert_session->pageTextContains('Something went wrong while confirming your subscription. Please try again.');

    // An unpublished node is not exposed by the confirmation route.
    $unpublished_node = $this->drupalCreateNode([
      'type' => 'page',
      'title' => 'Unpublished node',
      'status' => 0,
    ]);
    $this->drupalGet(Url::fromRoute('oe_newsroom_node.subscribe_verify', ['node' => $unpublished_node->id()]));
    $assert_session->statusCodeEquals(404);

    // A non-existent node is not exposed by the confirmation route.
    $node->delete();
    $this->drupalGet(Url::fromRoute('oe_newsroom_node.subscribe_verify', ['node' => $node->id()]));
    $assert_session->statusCodeEquals(404);
  }

}
