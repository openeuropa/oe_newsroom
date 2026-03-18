<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Value;

class NewsletterSubscribeResult {

  public function __construct(
    public readonly bool $isNew,
    public readonly string $feedbackMessage,
  ) {}

  public static function fromResponseData(array $data): static {
    // @todo Implement a good validation mechanism.
    $fail = fn () => throw new \RuntimeException('Bad data.');
    return new static(
      $data['isNewSubscription'] ?? $fail(),
      $data['feedbackMessage'] ?? $fail(),
    );
  }

}
