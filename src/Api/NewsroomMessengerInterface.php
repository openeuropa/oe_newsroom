<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom\Api;

use Drupal\Core\DependencyInjection\ContainerInjectionInterface;

/**
 * Interface for newsroom messenger api class.
 *
 * @package Drupal\oe_newsroom\Api
 */
interface NewsroomMessengerInterface extends ContainerInjectionInterface {

  /**
   * Precheck for API usability.
   *
   * @param bool $throw_error
   *   If it's true it will throw an InvalidApiConfiguration if there's a
   *   problem. Otherwise it will return true/false.
   *
   * @return bool
   *   Returns true if every mandatory data is set to be able to use newsroom.
   *
   * @throws \Drupal\oe_newsroom\Exception\InvalidApiConfiguration
   *   Thrown if $throw_error = true and there's a problem.
   */
  public function subscriptionServiceConfigured(bool $throw_error = TRUE): bool;

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
   *   Topic IDs, comma separated, only used for notifications.
   *
   * @return array|null
   *   Returns api response as an array.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails or if the response is not proper.
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If response is not proper we throw this exception.
   * @throws \Drupal\oe_newsroom\Exception\InvalidApiConfiguration
   *   If the API is not configured then this function is being called.
   */
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], string $language = NULL, array $topicExtId = []): ?array;

  /**
   * Unsubscribe an email from a newsletter.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param array $svIds
   *   An array of distribution list IDs. The user will get notification when
   *   they are unsubscribing from these list(s).
   *
   * @return bool
   *   True in case unsubscribe correctly, false otherwise.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails or if the response is not proper.
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If response is not proper we throw this exception.
   * @throws \Drupal\oe_newsroom\Exception\InvalidApiConfiguration
   *   If the API is not configured then this function is being called.
   */
  public function unsubscribe(string $email, array $svIds = []): bool;

  /**
   * Checks whether an email is subscribed or not.
   *
   * @param string $email
   *   Subscriber e-mail address.
   * @param array $svIds
   *   An array of distribution list IDs.
   *
   * @return bool
   *   False if the user is not subscribed, true if subscribed.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   *   If the HTTP requests fails or if the response is not proper.
   * @throws \GuzzleHttp\Exception\BadResponseException
   *   If response is not proper we throw this exception.
   * @throws \Drupal\oe_newsroom\Exception\InvalidApiConfiguration
   *   If the API is not configured then this function is being called.
   */
  public function isSubscribed(string $email, array $svIds = []): bool;

}
