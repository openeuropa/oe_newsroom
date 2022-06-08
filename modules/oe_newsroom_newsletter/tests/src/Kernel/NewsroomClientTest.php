<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Kernel;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Site\Settings;
use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_newsroom_newsletter\Api\NewsroomClient;
use Drupal\oe_newsroom_newsletter_mock\Plugin\ServiceMock\NewsroomPlugin;
use Drupal\Tests\oe_newsroom\NewsroomTestTrait;
use Drupal\Tests\oe_newsroom_newsletter\Traits\NewsroomClientMockTrait;
use Drupal\Tests\oe_newsroom_newsletter\Traits\NewsroomNewsletterTrait;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;

/**
 * Tests the Newsroom newsletter client.
 */
class NewsroomClientTest extends KernelTestBase {

  use NewsroomClientMockTrait;
  use NewsroomNewsletterTrait;
  use NewsroomTestTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'http_request_mock',
    'oe_newsroom',
    'oe_newsroom_newsletter',
    'oe_newsroom_newsletter_mock',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp() {
    parent::setUp();

    $this->installConfig([
      'oe_newsroom',
      'oe_newsroom_newsletter',
    ]);

    $settings = Settings::getAll();
    $settings['oe_newsroom']['newsroom_api_key'] = 'phpunit-test-private-key';
    new Settings($settings);
    \Drupal::state()->set(NewsroomPlugin::STAKE_KEY_VALIDATE_UNSUBSCRIPTIONS, FALSE);
  }

  /**
   * Tests the client configuration.
   */
  public function testClientConfiguration(): void {
    $universe = $this->randomMachineName();
    $app_id = $this->randomMachineName();

    $this->configureNewsroom([
      'universe' => $universe,
      'app_id' => $app_id,
      'hash_method' => 'md5',
      'normalised' => FALSE,
    ]);

    $client = NewsroomClient::create($this->container);
    $client->subscribe('Test@example.com', ['1111', '2222'], [], 'it');
    $this->assertEquals([
      'key' => 'a9402011c5d7620615d4d1d95568e9f6',
      'subscription' => [
        'universeAcronym' => $universe,
        'topicExtWebsite' => $app_id,
        'sv_id' => '1111,2222',
        'email' => 'Test@example.com',
        'language' => 'it',
      ],
    ], $this->getRequestBody($this->assertSingleRequestExecuted()));

    $client->unsubscribe('Fake@example.com', [1111]);
    $this->assertEquals([
      'app' => $app_id,
      'key' => '3b2c798673412fd1ba8c8cd6c163ed3b',
      'sv_id' => '1111',
      'user_email' => 'Fake@example.com',
    ], $this->getRequestQueryString($this->assertSingleRequestExecuted()));

    // Test the normalised option set to TRUE.
    $this->configureNewsroom([
      'universe' => $universe,
      'app_id' => $app_id,
      'hash_method' => 'md5',
      'normalised' => TRUE,
    ]);
    $client = NewsroomClient::create($this->container);
    $client->subscribe('Test@example.com', ['1111', '2222'], [], 'it');
    $this->assertEquals([
      'key' => '2283cafb8c33c5d2be3c1db74199513c',
      'subscription' => [
        'universeAcronym' => $universe,
        'topicExtWebsite' => $app_id,
        'sv_id' => '1111,2222',
        'email' => 'test@example.com',
        'language' => 'it',
      ],
    ], $this->getRequestBody($this->assertSingleRequestExecuted()));

    $client->unsubscribe('Fake@example.com', ['22222']);
    $this->assertEquals([
      'app' => $app_id,
      'key' => '92ffac87d2640766b9a75df5efffbc8f',
      'sv_id' => '22222',
      'user_email' => 'fake@example.com',
    ], $this->getRequestQueryString($this->assertSingleRequestExecuted()));

    // Test the sha256 method.
    $this->configureNewsroom([
      'universe' => $universe,
      'app_id' => $app_id,
      'hash_method' => 'sha256',
      'normalised' => TRUE,
    ]);
    $client = NewsroomClient::create($this->container);
    $client->subscribe('Test@example.com', ['1111'], [], 'de');
    $this->assertEquals([
      'key' => 'fe8d226fd629a8bc62305e702fa2af147ad6603838d556053eed2ac8b3c920f7',
      'subscription' => [
        'universeAcronym' => $universe,
        'topicExtWebsite' => $app_id,
        'sv_id' => '1111',
        'email' => 'test@example.com',
        'language' => 'de',
      ],
    ], $this->getRequestBody($this->assertSingleRequestExecuted()));

    $client->unsubscribe('Fake@example.com', [1111]);
    $this->assertEquals([
      'app' => $app_id,
      'key' => '364adf954e870cf0eef2781fe6244643d67cc466b16c2b37276c257d9121bae2',
      'sv_id' => '1111',
      'user_email' => 'fake@example.com',
    ], $this->getRequestQueryString($this->assertSingleRequestExecuted()));
  }

  /**
   * Asserts that a single request is found in the queue.
   *
   * @return \Psr\Http\Message\RequestInterface
   *   The request itself.
   */
  protected function assertSingleRequestExecuted(): RequestInterface {
    $requests = $this->getNewsroomClientRequests();
    $this->assertcount(1, $requests);
    $this->clearNewsroomClientRequests();

    return Message::parseRequest(array_pop($requests));
  }

  /**
   * Returns the decoded JSON found in a request body.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return array
   *   The body as an associative array.
   */
  protected function getRequestBody(RequestInterface $request): array {
    $body = (string) $request->getBody();
    $this->assertNotEmpty($body);
    $data = Json::decode($body);
    $this->assertIsArray($data);

    return $data;
  }

  /**
   * Returns the query string of a request as associative array.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   The request.
   *
   * @return array
   *   The parsed and sorted query string as associative array.
   */
  protected function getRequestQueryString(RequestInterface $request): array {
    parse_str($request->getUri()->getQuery(), $result);
    ksort($result);

    return $result;
  }

}
