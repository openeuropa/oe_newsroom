<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Traits;

use Drupal\Component\Serialization\Yaml;
use Drupal\oe_newsroom_vcr\Vcr\CaptureStore;
use Drupal\oe_newsroom_vcr\Vcr\VcrMode;
use Drupal\oe_newsroom_vcr\Vcr\VcrStore;
use Drupal\Tests\oe_newsroom\Helper\BackwardsCompatibility;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Contains methods to control the VCR.
 */
trait VcrTrait {

  /**
   * The name of the VCR tape.
   *
   * It also serves as an indicator that the VCR has started.
   */
  protected ?string $vcrName = NULL;

  /**
   * A callback to process VCR data after reading from yaml.
   *
   * @var (\Closure(mixed): mixed)|null
   */
  protected ?\Closure $vcrUnpack = NULL;

  /**
   * A callback to process VCR data before writing to yaml.
   *
   * @var (\Closure(mixed): mixed)|null
   */
  protected ?\Closure $vcrPack = NULL;

  /**
   * Starts the VCR session.
   *
   * @param string $name
   *   The name of the VCR tape.
   *   This determines the file to read from or write to, and should be unique
   *   to the current test.
   */
  protected function startVcr(string $name): void {
    $this->assertVcrCaptured([]);
    $this->assertTrue(\Drupal::moduleHandler()->moduleExists('oe_newsroom_vcr'));
    $this->vcrName = $name;
    if ($this->isRecording() == '2') {
      \Drupal::service(VcrStore::class)->startRecording();
    }
    elseif ($this->isRecording()) {
      \Drupal::service(VcrStore::class)->startRecording();
    }
    else {
      $vcr_file = $this->getVcrFile($name);
      $this->assertFileIsReadable($vcr_file);
      $yaml = file_get_contents($vcr_file);
      $records = Yaml::decode($yaml);
      BackwardsCompatibility::assertIsList($records);
      if ($this->vcrUnpack !== NULL) {
        $records = ($this->vcrUnpack)($records);
        BackwardsCompatibility::assertIsList($records);
      }
      \Drupal::service(VcrStore::class)->startReplay($records);
    }
  }

  /**
   * Ends the VCR session, and writes to the VCR file if in recording mode.
   */
  protected function endVcr(array $expected_captured_if_replay = []): void {
    $this->assertNotNull($this->vcrName);
    // Make sure this method cannot be called twice.
    $this->vcrName = NULL;
    if ($this->isRecording()) {
      $this->assertVcrCaptured([]);
      $records = \Drupal::service(VcrStore::class)->endRecording();
      BackwardsCompatibility::assertIsList($records);
      if ($this->vcrPack !== NULL) {
        $records = ($this->vcrPack)($records);
        BackwardsCompatibility::assertIsList($records);
      }
      $this->assertVcrCaptured($expected_captured_if_replay);
      $this->resetVcrCaptured();
      $vcr_file = $this->getVcrFile($this->vcrName);
      $this->assertDirectoryIsWritable(dirname($vcr_file));
      $yaml = Yaml::encode($records);
      file_put_contents($vcr_file, $yaml);
    }
    else {
      \Drupal::service(VcrStore::class)->endReplay();
    }
  }

  /**
   * Posts or asserts a comment in the VCR.
   *
   * @param string $comment
   *   The comment text.
   */
  protected function vcrComment(string $comment): void {
    $this->assertNotNull($this->vcrName);
    $vcr_store = \Drupal::service(VcrStore::class);
    $record = new TaggedValue('Comment', $comment);
    if ($vcr_store->getMode() === VcrMode::Recording) {
      $vcr_store->addRecord($record);
    }
    elseif ($vcr_store->getMode() === VcrMode::Replay) {
      $this->assertEquals($record, $vcr_store->readNextRecord($position), "Comment at position $position does not match.");
    }
  }

  /**
   * Gets the path to the VCR file.
   *
   * @param string $name
   *   The name of the VCR tape.
   *
   * @return string
   *   The path to the VCR file.
   */
  protected function getVcrFile(string $name): string {
    $name = preg_replace('/[^a-zA-Z0-9]+/', '.', $name);
    return dirname(__DIR__, 2) . '/fixtures/vcr/' . $name . '.yml';
  }

  /**
   * Resets captured values.
   */
  protected function resetVcrCaptured(): void {
    \Drupal::service(CaptureStore::class)->reset();
  }

  /**
   * Asserts captured values.
   *
   * @param array $expected
   *   Expected captured values.
   */
  protected function assertVcrCaptured(array $expected): void {
    $actual = \Drupal::service(CaptureStore::class)->getCapturedValues();
    $this->assertSame($expected, $actual);
  }

  /**
   * Determines if the test is in recording mode.
   *
   * Note that the test starts in recording mode before the VCR is in recording
   * mode.
   *
   * @return bool
   *   TRUE if the test is in recording mode.
   *   FALSE if the test is in replay mode.
   */
  protected function isRecording(): bool {
    return (bool) getenv('UPDATE_TESTS');
  }

}
