<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom_api_explorer\FunctionalJavascript;

use Drupal\Component\Serialization\Yaml;
use Drupal\FunctionalJavascriptTests\WebDriverTestBase;
use Drupal\Tests\oe_newsroom\Traits\StatusMessageTrait;
use Drupal\Tests\oe_newsroom\Traits\WebAssertionTrait;

/**
 * Tests the API Explorer form.
 */
class NewsroomApiExplorerTest extends WebDriverTestBase {

  use StatusMessageTrait;
  use WebAssertionTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom_api_explorer',
    'oe_newsroom_newsletter',
    'oe_newsroom_newsletter_mock',
  ];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  public function setUp(): void {
    if (version_compare(\Drupal::VERSION, '11.3.0', '<')) {
      $this->markTestSkipped('This test only runs with Drupal 11.3 and higher.');
    }
    parent::setUp();
  }

  /**
   * Tests the API Explorer page/form.
   */
  public function testApiExplorer(): void {
    // Anonymous user has no access to the API explorer.
    $this->drupalGet('/admin/config/system/newsroom-settings/api-explorer');
    $this->assertPageTitle('Access denied');

    // A user with powerful but unrelated permissions still has no access.
    $this->drupalLogin($this->createUser([
      'administer site configuration',
      'administer newsroom configuration',
    ]));
    $this->drupalGet('/admin/config/system/newsroom-settings/api-explorer');
    $this->assertPageTitle('Access denied');

    // A user with the correct permission can access the page.
    $this->drupalLogin($this->createUser([
      'use newsroom api explorer',
    ]));
    $this->drupalGet('/admin/config/system/newsroom-settings/api-explorer');
    $this->assertPageTitle('Newsroom API Explorer');

    $assert_session = $this->assertSession();

    // Try the 'isConfigured' method without the checkbox.
    $this->selectEndpoint('NewsroomClient::isConfigured');
    $assert_session->pageTextContains('Arguments for isConfigured()');
    $checkbox_text = 'I understand that submitting this form might cause emails being sent to innocent recipients.';
    $assert_session->checkboxNotChecked($checkbox_text);
    $this->submitWithAjax();
    $this->assertStatusMessages(['error' => ["$checkbox_text field is required."]]);

    // Try the 'isConfigured' method with the checkbox.
    $assert_session->elementExists('named', ['checkbox', $checkbox_text])->check();
    $assert_session->buttonExists('Submit')->press();
    $assert_session->assertWaitOnAjaxRequest();
    $this->assertStatusMessages(
      [
        'status' => [
          <<<EOT
<h3>Submission successful (<em class="placeholder">@time</em>).</h3>
<br>
<h4>[return]</h4>
<pre>false</pre>
<h4>[endpoint_name]</h4>
<pre>'Drupal\oe_newsroom_newsletter\Api\NewsroomClient::isConfigured'</pre>
<h4>[arguments]</h4>
<pre>{  }</pre>
EOT,
        ],
      ],
      [
        '@time' => '\d+:\d+:\d+',
      ],
    );
    // The checkbox is still checked.
    $assert_session->checkboxChecked($checkbox_text);

    $this->selectEndpoint('NewsroomClient::subscribe');
    $assert_session->pageTextContains('Arguments for subscribe()');
    $assert_session->fieldExists('email')->setValue('testuser@example.com');
    $assert_session->fieldExists('svIds')->setValue('1111,2222');
    $this->submitWithAjax();

    $matches = $this->assertStatusMessages(
      [
        'status' => [
          <<<EOT
<h3>Submission successful (<em class="placeholder">@time</em>).</h3>
<br>
<h4>[return]</h4>
<pre>@return</pre>
<h4>[endpoint_name]</h4>
<pre>'Drupal\oe_newsroom_newsletter\Api\NewsroomClient::subscribe'</pre>
<h4>[arguments]</h4>
<pre>email: testuser@example.com
svIds:
  - '1111'
  - '2222'
relatedSvIds: {  }
language: null
topicExtId: {  }
</pre>
<h4>[request]</h4>
<pre>@request</pre>
<h4>[response]</h4>
<pre>@response</pre>
EOT,
        ],
      ],
      [
        '@time' => '\d+:\d+:\d+',
        '@return' => '([^<]+)',
        '@request' => '([^<]+)',
        '@response' => '([^<]+)',
      ],
    );
    $return_data = Yaml::decode($matches['status'][0][1]);
    $request_data = Yaml::decode($matches['status'][0][2]);
    $response_data = Yaml::decode($matches['status'][0][3]);
    $this->assertSame('On demand', $return_data['frequency']);
    $this->assertSame('testuser@example.com', $request_data['data']['subscription']['email']);
    $this->assertSame('1111,2222', $request_data['data']['subscription']['sv_id']);
    $this->assertSame('testuser@example.com', $response_data['data'][0]['email']);
    $this->assertSame('1111', $response_data['data'][0]['newsletterId']);
    $this->assertSame('testuser@example.com', $response_data['data'][1]['email']);
    $this->assertSame('2222', $response_data['data'][1]['newsletterId']);
  }

  /**
   * Selects an endpoint and waits for ajax.
   *
   * @param string $name
   *   The endpoint name.
   */
  protected function selectEndpoint(string $name): void {
    $this->assertSession()->selectExists('Endpoint')->selectOption($name);
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

  /**
   * Submits and waits for ajax.
   */
  protected function submitWithAjax(): void {
    $this->assertSession()->buttonExists('Submit')->press();
    $this->assertSession()->assertWaitOnAjaxRequest();
  }

}
