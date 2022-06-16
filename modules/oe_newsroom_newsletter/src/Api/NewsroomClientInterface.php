<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Api;

/**
 * Interface for newsroom client api class.
 *
 * @internal
 */
interface NewsroomClientInterface {

  /**
   * A URL of the API.
   */
  public const API_URL = 'https://ec.europa.eu/newsroom/api/v1';

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
   * @return array
   *   Returns API response as an array.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   Thrown by the Guzzle client.
   * @throws \Drupal\oe_newsroom_newsletter\Exception\InvalidResponseException
   *   Thrown when the response is not valid.
   */
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], string $language = NULL, array $topicExtId = []): array;

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
   *   Thrown by the Guzzle client.
   */
  public function unsubscribe(string $email, array $svIds = []): bool;

}
