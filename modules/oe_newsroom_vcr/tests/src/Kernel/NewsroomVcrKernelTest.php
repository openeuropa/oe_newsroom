<?php

namespace Drupal\Tests\oe_newsroom_vcr\Kernel;

use Drupal\Core\KeyValueStore\KeyValueFactoryInterface;
use Drupal\Core\KeyValueStore\MemoryStorage;
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
   * Tests the VCR.
   */
  public function testVcr(): void {
    // The VCR uses key value store, which behaves differently in kernel tests.
    $this->assertInstanceOf(MemoryStorage::class, \Drupal::service(KeyValueFactoryInterface::class));

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
