<?php

namespace Drupal\oe_newsroom\Api;

/**
 * Interface for newsroom messenger api class.
 *
 * @package Drupal\oe_newsroom\Api
 */
interface NewsroomMessengerInterface {

  /**
   * Precheck for API usability.
   *
   * @return bool
   *   Returns true if every mandatory data is set to be able to use newsroom.
   */
  public function subscriptionServiceConfigured(): bool;

  /**
   * Subscribe a user to a newsletter.
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
   *   Topic ids, comma separated, only used for notifications.
   *
   * @return array|null
   *   Returns api resposne as an array.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails or if the response is not proper.
   */
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], string $language = NULL, array $topicExtId = []): ?array;

  /**
   * Unsubscribe an email from newsletter.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param array $svIds
   *   An array of distribution list IDs. The user will get notification when
   *   they are subscribing for these list(s).
   *
   * @return bool
   *   True in case unsubscribe correctly, false otherwise.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails or if the response is not proper.
   */
  public function unsubscribe(string $email, array $svIds = []): ?bool;

  /**
   * Checks whether an email is subscribed or not.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param array $svIds
   *   An array of distribution list IDs.
   *
   * @return bool
   *   True if the user is not subscribed, false if subscribed and null if an
   *   error accord in the data process.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails.
   */
  public function isSubscribed(string $email, array $svIds = []): ?bool;

}
