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
   * @param string|null $topicId
   *   Topic ids, comma separated.
   * @param string|null $topicExtId
   *   Topic ids, comma separated.
   * @param string|null $language
   *   Specify the language of the subscription (for all services).
   *
   * @return array|null
   *   Returns api resposne as an array.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails or if the response is not proper.
   */
  public function subscribe(string $email, string $topicId = NULL, string $topicExtId = NULL, string $language = NULL): ?array;

  /**
   * Unsubscribe an email from newsletter.
   *
   * @param string $email
   *   Subscriber e-mail address.
   *
   * @return bool
   *   True in case unsubscribe correctly, false otherwise.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails or if the response is not proper.
   */
  public function unsubscribe(string $email): ?bool;

  /**
   * Sets the message with the correct type.
   *
   * @param array $subscription
   *   Subscription response.
   */
  public function subscriptionMessage(array $subscription): void;

  /**
   * Checks whether an email is subscribed or not.
   *
   * @param string $email
   *   Subscriber e-mail address.
   *
   * @return bool
   *   True if the user is not subscribed, false if subscribed and null if an
   *   error accord in the data process.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails.
   */
  public function isSubscribed(string $email): ?bool;

  /**
   * Requests an URL to update/edit the already subscribed user subscription.
   *
   * @param string $email
   *   Subscriber e-mail address.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails.
   */
  public function requestEditSubscription(string $email): void;

  /**
   * Requests an URL to unsubscribe the already subscribed user.
   *
   * @param string $email
   *   Subscriber e-mail address.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails.
   */
  public function requestLoginForUnsubscription(string $email): void;

}
