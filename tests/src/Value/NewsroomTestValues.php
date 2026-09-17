<?php

declare(strict_types=1);

namespace Drupal\Tests\oe_newsroom\Value;

/**
 * Contains values to use in PhpUnit tests.
 *
 * This should be instantiated in a `test-values(.example).php` file.
 */
class NewsroomTestValues {

  /**
   * Creates a new instance.
   *
   * @param string $privateKey
   *   A private key associated with the app id and universe.
   * @param string $hashMethod
   *   The hash method.
   * @param bool $normalised
   *   TRUE, if email addresses should be normalized to lowercase.
   * @param string $universe
   *   The universe alias.
   * @param string $appId
   *   The application id.
   * @param int $nodeNotificationServiceId
   *   The service id for node notifications.
   * @param int $nodeNotificationSectionId
   *   The section id for node notifications.
   * @param string $nodeNotificationTopicName
   *   The service name for node notifications.
   * @param string $subscriberEmail
   *   The email address used for tests.
   *   In the local version, this should be an address where the developer can
   *   actually receive emails.
   */
  public function __construct(
    #[\SensitiveParameter]
    public readonly string $privateKey,
    public readonly string $hashMethod,
    public readonly bool $normalised,
    public readonly string $universe,
    public readonly string $appId,
    public readonly int $nodeNotificationServiceId,
    public readonly int $nodeNotificationSectionId,
    public readonly string $nodeNotificationTopicName,
    public readonly string $subscriberEmail,
  ) {
    if ($subscriberEmail === mb_strtolower($subscriberEmail)) {
      throw new \InvalidArgumentException('Please use some uppercase characters in the test email address, to test normalization.');
    }
  }

  /**
   * Gets default values to stabilize VCR recordings.
   *
   * @return array
   *   Default values for the VCR.
   */
  public function getVcrStabilizationDefaults(): array {
    return [
      'hash_method' => $this->hashMethod,
      'normalised' => $this->normalised,
      'universe' => $this->universe,
      'app_id' => $this->appId,
      // Some integer ids appear as string in the requests and responses.
      'service_id' => [
        'node_notification.int' => $this->nodeNotificationServiceId,
        'node_notification' => (string) $this->nodeNotificationServiceId,
      ],
      'node_notification_section_id' => [
        // The array key will appear as meta information in the recording,
        // appended to the '!Stabilized' yaml tag.
        // If the key is 0, it will be omitted.
        0 => (string) $this->nodeNotificationSectionId,
        'int' => $this->nodeNotificationSectionId,
      ],
      'topic_name' => [
        $this->nodeNotificationTopicName,
      ],
      'email' => [
        0 => $this->subscriberEmail,
        'mb_strtolower' => mb_strtolower($this->subscriberEmail),
      ],
    ];
  }

  /**
   * Gets a settings array to write to 'oe_newsroom.settings'.
   *
   * @return array
   *   The values for 'oe_newsroom.settings'.
   */
  public function getNewsroomModuleSettings(): array {
    return [
      'hash_method' => $this->hashMethod,
      'normalised' => $this->normalised,
      'universe' => $this->universe,
      'app_id' => $this->appId,
      'node_service_id' => $this->nodeNotificationServiceId,
    ];
  }

}
