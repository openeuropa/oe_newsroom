<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Value;

/**
 * Represents the outcome of a newsletter subscribe operation.
 *
 * This may not include the complete response data, only the parts that we need.
 */
class NewsletterSubscribeResult {

  /**
   * Constructor.
   *
   * @param bool $isNew
   *   TRUE if this is a new subscription.
   *   FALSE if already subscribed.
   * @param string $feedbackMessage
   *   A feedback message to show to a user, if no custom message was
   *   configured.
   */
  public function __construct(
    public readonly bool $isNew,
    public readonly string $feedbackMessage,
  ) {}

  /**
   * Creates a new instance from response data.
   *
   * @param array $data
   *   Response data, parsed from json.
   *
   * @return static
   *   New instance.
   */
  public static function fromResponseData(array $data): static {
    // @todo Implement a good validation mechanism.
    $fail = fn () => throw new \RuntimeException('Bad data.');
    return new static(
      $data['isNewSubscription'] ?? FALSE,
      $data['feedbackMessage'] ?? $fail(),
    );
  }

}
