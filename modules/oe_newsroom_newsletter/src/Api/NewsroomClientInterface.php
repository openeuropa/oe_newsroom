<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Api;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Interface for newsroom client api class.
 *
 * @internal
 */
interface NewsroomClientInterface extends ContainerInjectionInterface {

  /**
   * Subscribe an email to the newsletters.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param array $svIds
   *   An array of distribution list IDs. The user will get notification when
   *   they are subscribing for these list(s).
   * @param array $relatedSvIds
   *   An array of distribution list IDs. The user will NOT get notification
   *   when they are subscribing for these list(s).
   * @param string|null $language
   *   Specify the language of the subscription (for all services).
   * @param array $topicExtId
   *   An array of Topic IDs, only used for notifications.
   *
   * @return array|null
   *   Returns api response as an array.
   *
   * @throws \GuzzleHttp\Exception\ServerException
   *   If the HTTP requests fails or if the response is not proper.
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If response is not proper we throw this exception.
   * @throws \Drupal\oe_newsroom\Exception\InvalidApiConfiguration
   *   If the API is not configured then this Exception is thrown.
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   */
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], string $language = NULL, array $topicExtId = []): ?array;

  /**
   * Unsubscribe an email from the newsletters.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param array $svIds
   *   An array of distribution list IDs. The user will get notification when
   *   they are unsubscribing from these list(s).
   *
   * @return bool
   *   True in case unsubscribe correctly, false otherwise. In case if there's
   *   multiple distribution list provided, all must succeed to be returned
   *   true.
   *
   * @throws \GuzzleHttp\Exception\ServerException
   *   If the HTTP requests fails or if the response is not proper.
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If response is not proper we throw this exception.
   * @throws \Drupal\oe_newsroom\Exception\InvalidApiConfiguration
   *   If the API is not configured then this Exception is thrown.
   */
  public function unsubscribe(string $email, array $svIds = []): bool;

}
