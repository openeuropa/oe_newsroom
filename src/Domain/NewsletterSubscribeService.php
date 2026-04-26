<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Domain;

use Drupal\oe_newsroom\Api\NewsletterSubscriptionApi;
use Drupal\oe_newsroom\Exception\Api\ApiException;
use Drupal\oe_newsroom\Exception\Domain\OperationError;
use Drupal\oe_newsroom\Value\NewsletterSubscribeResult;

/**
 * Provides functionality for newsletter subscriptions.
 */
class NewsletterSubscribeService {

  public function __construct(
    protected readonly NewsletterSubscriptionApi $apiClient,
  ) {}

  /**
   * Subscribes an email to newsletters.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param list<string> $distribution_list_ids
   *   Newsletter distribution list ids.
   * @param string|null $language
   *   Language for the subscription.
   *
   * @return \Drupal\oe_newsroom\Value\NewsletterSubscribeResult
   *   Value object describing the result of the operation.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationFailure
   *   Failed to subscribe.
   */
  public function subscribe(string $email, array $distribution_list_ids, ?string $language = NULL): NewsletterSubscribeResult {
    try {
      $data = $this->apiClient->subscribe($email, $distribution_list_ids, [], $language);
    }
    catch (ApiException $e) {
      throw new OperationError('Failed to subscribe', previous: $e);
    }
    return NewsletterSubscribeResult::fromResponseData($data);
  }

  /**
   * Unsubscribes from newsletters.
   *
   * @param string $email
   *   The email to unsubscribe.
   * @param list<string> $distribution_list_ids
   *   Distribution list ids to unsubscribe from.
   * @param string|null $language
   *   The language to unsubscribe, or NULL to unsubscribe across languages.
   *
   * @throws \Drupal\oe_newsroom\Exception\Domain\OperationFailure
   *   Failed to unsubscribe.
   *
   * @todo Distinguish results.
   */
  public function unsubscribe(string $email, array $distribution_list_ids, string $language = NULL): void {
    foreach ($distribution_list_ids as $distribution_list_id) {
      try {
        $this->apiClient->unsubscribe($email, $distribution_list_id);
      }
      catch (ApiException $e) {
        throw new OperationError('Failed to unsubscribe', previous: $e);
      }
    }
  }

}
