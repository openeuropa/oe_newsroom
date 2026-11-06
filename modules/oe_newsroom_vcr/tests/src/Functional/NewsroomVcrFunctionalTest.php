<?php

namespace Drupal\Tests\oe_newsroom_vcr\Functional;

use Drupal\Core\Extension\ModuleInstallerInterface;
use Drupal\oe_newsroom_vcr\Vcr\VcrStore;
use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\oe_newsroom\Helper\BackwardsCompatibility;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Tests the VCR for functional tests.
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
    $this->drupalGet('oe-newsroom-vcr-test/page');
    $this->assertSame(
      <<<'EOT'
animals:
  - cat
  - penguin

EOT,
      $this->assertSession()
        ->elementExists('css', 'pre#test-content')
        ->getHtml(),
    );

    $records = $vcr->endRecording();
    BackwardsCompatibility::assertIsList($records);
    $this->assertCount(2, $records);
    $request = $this->assertTaggedValue('Request', $records[0]);
    $this->assertSame('/build/oe-newsroom-vcr-test/api', $request['path']);
    $response = $this->assertTaggedValue('Response', $records[1]);
    $this->assertSame('{"animals":["cat","penguin"]}', $response['body']);

    $response['body'] = '{"animals":["cat","penguin","badger"]}';
    $records[1] = new TaggedValue('Response', $response);

    $vcr->startReplay($records);
    $this->drupalGet('oe-newsroom-vcr-test/page');
    $this->assertSame(<<<'EOT'
animals:
  - cat
  - penguin
  - badger

EOT, $this->assertSession()->elementExists('css', 'pre#test-content')->getHtml());
    $vcr->endReplay();
  }

  /**
   * Asserts an instance of TaggedValue, and gets the value.
   *
   * @param string $tag
   *   Expected tag.
   * @param mixed $actual
   *   The actual value.
   *
   * @return mixed
   *   The value from TaggedValue::getValue().
   */
  protected function assertTaggedValue(string $tag, mixed $actual): mixed {
    $this->assertInstanceOf(TaggedValue::class, $actual);
    $this->assertSame($tag, $actual->getTag());
    return $actual->getValue();
  }

}
