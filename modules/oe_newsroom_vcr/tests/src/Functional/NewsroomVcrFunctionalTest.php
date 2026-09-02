<?php

namespace Drupal\Tests\oe_newsroom_vcr\Functional;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\oe_newsroom_vcr\Vcr\VcrMode;
use Drupal\oe_newsroom_vcr\Vcr\VcrStore;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom\Helper\BackwardsCompatibility;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Tests the VCR for functional tests.
 *
 * Unlike in the kernel test, the VCR mechanism happens in a separate php
 * process. The recorded data is stored in the database.
 *
 * @see \Drupal\oe_newsroom_vcr_test\Controller\VcrTestController
 */
class NewsroomVcrFunctionalTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom_vcr',
    'oe_newsroom_vcr_test',
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
    // For some reason, the page is cached when it really should not be.
    \Drupal::service(ModuleInstallerInterface::class)->uninstall(['page_cache']);
  }

  /**
   * Tests the VCR.
   */
  public function testVcr(): void {
    $vcr = \Drupal::service(VcrStore::class);
    $vcr->startRecording();
    // Visit a test page.
    /* @see \Drupal\oe_newsroom_vcr_test\Controller\VcrTestController::page() */
    // The controller of that page will perform an outgoing http request, and
    // the page will contain the response data from that request.
    $this->drupalGet('oe-newsroom-vcr-test/page');
    // In recording mode, the page contains the unmodified data as returned from
    // the API.
    $this->assertSame(
      <<<'YML'
animals:
  - cat
  - penguin

YML,
      $this->assertSession()
        ->elementExists('css', 'pre#test-content')
        ->getHtml(),
    );

    $records = $vcr->endRecording();
    // The recorded data contains the request and response.
    BackwardsCompatibility::assertIsList($records);
    $this->assertCount(2, $records);
    $request = $this->assertAndUnpackTaggedValue('Request', $records[0]);
    $this->assertSame('/build/oe-newsroom-vcr-test/api', $request['path']);
    $response = $this->assertAndUnpackTaggedValue('Response', $records[1]);
    $this->assertSame('{"animals":["cat","penguin"]}', $response['body']);

    // The request and response contain plenty of data that most tests would
    // want to ignore. Additional logic is needed to stabilize and simplify that
    // data before writing to a test fixture file.
    $this->assertSame(
      ['scheme', 'host', 'port', 'path'],
      array_keys($request),
    );
    $this->assertSame(
      ['status', 'headers', 'body'],
      array_keys($response),
    );

    // Manipulate the recorded response.
    $response['body'] = '{"animals":["cat","penguin","badger"]}';
    $records[1] = new TaggedValue('Response', $response);

    // Switch the VCR to replay mode, with the manipulated recording.
    $vcr->startReplay($records);
    $this->drupalGet('oe-newsroom-vcr-test/page');
    // The page now contains the manipulated data.
    $this->assertSame(
      <<<'EOT'
animals:
  - cat
  - penguin
  - badger

EOT,
      $this->assertSession()->elementExists('css', 'pre#test-content')->getHtml(),
    );
    $vcr->endReplay();

    // Manipulate the recorded query, so it no longer matches the actual query.
    $request['query'] = ['x' => 'y'];
    $records[0] = new TaggedValue('Request', $request);
    // Start the replay with the manipulated recording, visit the page again.
    $vcr->startReplay($records);
    $this->drupalGet('oe-newsroom-vcr-test/page');

    // After the page visit, the VCR is marked as poisoned.
    $this->assertSame(VcrMode::Poisoned, $vcr->getMode());
    // A failure has been recorded.
    $failure = $vcr->getFailure();
    $this->assertSame(
      <<<EOT
Request does not match recording at position 0.
expected:
  scheme: http
  host: web
  port: 8080
  path: /build/oe-newsroom-vcr-test/api
  query:
    x: 'y'
actual:
  scheme: http
  host: web
  port: 8080
  path: /build/oe-newsroom-vcr-test/api

EOT,
      $failure,
    );

    // The same failure is thrown as exception on ->assertNoFailure().
    try {
      $vcr->assertNoFailure();
      $this->fail('Expected exception was not thrown.');
    }
    catch (\RuntimeException $e) {
      $this->assertSame(
        "A failure occured in the VCR:\n$failure",
        $e->getMessage(),
      );
    }
  }

  /**
   * Asserts the tag on a TaggedValue instance, and gets the value.
   *
   * @param string $tag
   *   Expected tag.
   * @param mixed $actual
   *   The actual value.
   *
   * @phpstan-assert \Symfony\Component\Yaml\Tag\TaggedValue $actual
   *
   * @return mixed
   *   The value from TaggedValue::getValue().
   */
  protected function assertAndUnpackTaggedValue(string $tag, mixed $actual): mixed {
    $this->assertInstanceOf(TaggedValue::class, $actual);
    $this->assertSame($tag, $actual->getTag());
    return $actual->getValue();
  }

}
