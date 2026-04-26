<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Domain;

use Drupal\oe_newsroom\Endpoint\NewsletterSubscriptionEndpoints;
use Drupal\oe_newsroom\Exception\Api\ApiException;
use Drupal\oe_newsroom\Exception\Api\NotFoundException;
use Drupal\oe_newsroom\Exception\Domain\OperationDenied;
use Drupal\oe_newsroom\Exception\Domain\OperationError;
use Drupal\oe_newsroom\Value\NewsletterSubscribeResult;

/**
 * Provides functionality for newsletter subscriptions.
 */
class NewsletterSubscribeService {

  public function __construct(
    protected readonly NewsletterSubscriptionEndpoints $apiClient,
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
    catch (NotFoundException $e) {
      // @todo Detect if the newsletter id was not found, or something else was not found.
      // @todo Handle the case where some subscriptions are successful, but others are not.
      throw new OperationDenied('Some of the newsletters were not found.', previous: $e);
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
   * @todo Detect "already unsubscribed" case.
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
