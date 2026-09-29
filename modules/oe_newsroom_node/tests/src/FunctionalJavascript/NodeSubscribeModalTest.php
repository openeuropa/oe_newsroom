<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom_node\FunctionalJavascript;

use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_newsroom\Traits\LocalTestValuesTrait;
use Drupal\Tests\oe_newsroom\Traits\NodeSubscriptionVcrTrait;
use Drupal\Tests\oe_newsroom\Traits\VcrTrait;

/**
 * Tests the node subscribe modal happy path.
 *
 * @group oe_newsroom_node
 */
class NodeSubscribeModalTest extends WebDriverTestBase {

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

    $this->initializeNewsroomAndVcrWithTestValues();

    // WebDriver requests run in a separate Drupal process, so write the
    // private key to the generated settings.php as well.
    $settings['settings']['oe_newsroom']['newsroom_api_key'] = (object) [
      'value' => $this->newsroomTestValues->privateKey,
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Tests the node subscribe modal.
   */
  public function testNodeSubscribeModal(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $subscriber_email = $this->newsroomTestValues->subscriberEmail;

    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'My node']);

    // In recording mode this calls the real Newsroom API; otherwise it
    // replays the fixture named after this test method.
    $this->startVcr(__METHOD__);

    $this->drupalGet($node->toUrl());

    // The subscribe link is shown. Clicking it opens the form in a modal.
    $link = $assert_session->elementExists('css', '.oe-newsroom-node__subscribe-link');
    $link->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '.ui-dialog');

    // jQuery UI moves the submit button into the dialog button pane, so the
    // submit button must be pressed there.
    $submit = $assert_session->elementExists('css', '.ui-dialog-buttonpane button');

    // Disable the browser HTML5 validation so the empty required "email" field
    // reaches the server and Drupal's required-field message is returned.
    $this->getSession()->executeScript("document.querySelector('.ui-dialog form').setAttribute('novalidate', 'novalidate');");

    // Submitting the empty form returns both required-field errors at once:
    // the e-mail is required and the privacy statement must be agreed to.
    $submit->press();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->statusMessageContains('Your e-mail field is required.', 'error');
    $assert_session->statusMessageContains('You must agree with the privacy statement.', 'error');

    // Filling only the e-mail still shows the privacy error. The button pane is
    // recreated on the ajax rebuild, so look it up again.
    $page->fillField('email', $subscriber_email);
    $assert_session->elementExists('css', '.ui-dialog-buttonpane button')->press();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->statusMessageContains('You must agree with the privacy statement.', 'error');
    $assert_session->statusMessageNotContains('Your e-mail field is required.');

    // Agree and submit. The button pane is recreated on the ajax rebuild.
    $page->checkField('agree_privacy_statement');
    $assert_session->elementExists('css', '.ui-dialog-buttonpane button')->press();
    $assert_session->assertWaitOnAjaxRequest();

    // The modal is closed and a confirmation message is shown. No error is
    // shown, so the service response was handled as a success.
    $assert_session->assertNoElementAfterWait('css', '.ui-dialog');
    $assert_session->statusMessageContains('A confirmation email has been sent to your address. Please click the link in the email to confirm your subscription.', 'status');
    $assert_session->statusMessageNotContains('An error occurred while processing your request');

    $this->endVcr();
  }

  /**
   * Tests that an API failure keeps the modal open with an error message.
   */
  public function testNodeSubscribeModalApiFailure(): void {
    $assert_session = $this->assertSession();
    $page = $this->getSession()->getPage();
    $subscriber_email = $this->newsroomTestValues->subscriberEmail;

    $node = $this->drupalCreateNode(['type' => 'page', 'title' => 'My node']);
    $this->startFailedNodeSubscriptionReplay($node, $subscriber_email);
    $this->drupalGet($node->toUrl());

    $assert_session->elementExists('css', '.oe-newsroom-node__subscribe-link')->click();
    $assert_session->assertWaitOnAjaxRequest();
    $assert_session->waitForElementVisible('css', '.ui-dialog');

    // Fill in valid values and submit.
    $page->fillField('email', $subscriber_email);
    $page->checkField('agree_privacy_statement');
    $assert_session->elementExists('css', '.ui-dialog-buttonpane button')->press();
    $assert_session->assertWaitOnAjaxRequest();

    // The modal stays open and the error message is shown inside it.
    $assert_session->elementExists('css', '.ui-dialog');
    $assert_session->statusMessageContains('An error occurred while processing your request, please try again later. If the error persists, contact the site owner.', 'error');
    $assert_session->statusMessageNotContains('A confirmation email has been sent to your address.');

    $this->endFailedNodeSubscriptionReplay();
  }

}
