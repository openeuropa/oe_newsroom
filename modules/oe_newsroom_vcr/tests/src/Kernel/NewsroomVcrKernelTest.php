<?php

namespace Drupal\Tests\oe_newsroom_vcr\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\oe_newsroom_vcr\Vcr\VcrStore;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Tests the VCR for kernel tests.
 */
class NewsroomVcrKernelTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'oe_newsroom_vcr',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    if (version_compare(\Drupal::VERSION, '11.3', '<')) {
      $this->markTestSkipped('This test only runs in Drupal >= 11.3');
    }
    parent::setUp();
  }

  /**
   * Tests the VCR.
   */
  public function testVcr(): void {
    $vcr = \Drupal::service(VcrStore::class);
    $vcr->startRecording();
    $original_records = [];
    $vcr->addRecord($original_records[] = new TaggedValue('OneTag', 'xyz'));
    $vcr->addRecord($original_records[] = new TaggedValue('OtherTag', ['array']));
    $recorded_records = $vcr->endRecording();

    $this->assertSame($original_records, $recorded_records);

    $vcr->startReplay($recorded_records);
    $replay_records = [];
    $replay_records[] = $vcr->readNextRecord($position);
    $this->assertSame(0, $position);
    $replay_records[] = $vcr->readNextRecord($position);
    $this->assertSame(1, $position);
    $vcr->endReplay();

    $this->assertSame($original_records, $replay_records);
  }

}
