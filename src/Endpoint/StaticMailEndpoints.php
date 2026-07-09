<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Endpoint;

use Drupal\oe_newsroom\Api\ApiClient;
use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;

/**
 * Service to access the Newsroom static mail API.
 */
#[NewsroomApiExplorer]
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
    $this->apiClient->post(
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
        // Provide a string key only for the 'emails' part, which is targeted
        // in the next parameter.
        // phpcs:ignore Squiz.Arrays.ArrayDeclaration.KeySpecified
        'emails' => implode($emails),
      ],
      // Only the 'emails' part of the auth key input needs to be normalized.
      ['emails'],
    );
  }

}
