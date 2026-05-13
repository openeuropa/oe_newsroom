<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Endpoint;

use Drupal\oe_newsroom\Api\ApiClient;

/**
 * Service to access the Newsroom static mail API.
 */
class StaticMailEndpoints {

  public function __construct(
    protected readonly ApiClient $apiClient,
  ) {}

  /**
   * Tells Newsroom to sends a static email.
   *
   * @param list<non-empty-string> $emails
   *   List of email addresses.
   * @param int $model
   *   Model or ID of the internal item.
   * @param string $language
   *   Language preference.
   * @param int|null $newsletter_id
   *   Optional newsletter id.
   */
  public function staticMail(
    array $emails,
    int $model,
    string $language,
    ?int $newsletter_id = NULL,
  ): void {
    // The response on success will just say 'Email sent.'.
    $this->apiClient->postJson(
      'auth/mailing/send-static',
      [
        'emails' => $emails,
        ...($newsletter_id !== NULL) ? [
          'newsletter_id' => $newsletter_id,
        ] : [],
        'model' => $model,
        'language' => $language,
      ],
      [
        $model,
        $language,
        ...$emails,
      ],
    );
  }

}
