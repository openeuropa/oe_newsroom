<?php

declare(strict_types=1);

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
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], ?string $language = NULL, array $topicExtId = []): array;

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

  /**
   * Gets mail subscriptions.
   *
   * @param string $email
   *   The email to obtain the subscriptions from.
   *
   * @return array
   *   The subscriptions list.
   */
  public function subscriptions(
    string $email,
  );

  /**
   * Create node notification.
   *
   * @param string $section_id
   *   The section.
   * @param string $notification_title
   *   The notfication title.
   * @param string $notification_description
   *   The notfication description.
   * @param string $notification_url
   *   The notfication URL.
   * @param string $node_id
   *   The node ID.
   * @param string $node_title
   *   The node title.
   * @param string $create_date
   *   The creation date.
   */
  public function nodeNotificationCreate(
    string $section_id,
    string $notification_title,
    string $notification_description,
    string $notification_url,
    string $node_id,
    string $node_title,
    // Is this required and what is the expected format?
    string $create_date,
  ): void;

  /**
   * Delete node notification.
   *
   * @param string $node_id
   *   The node ID.
   */
  public function nodeNotificationDelete(string $node_id): void;

  /**
   * Subscribe an e-mail to a node notification.
   *
   * @todo For the moment keep this as a separate method since method signature
   * is quite different. Can we join both?
   *
   * @param string $node_id
   *   The node ID.
   * @param string $email
   *   The user email.
   * @param int $frequency
   *   The subscription frequency.
   * @param bool $nomail
   *   If the subscription require confirmation.
   *
   * @return array
   *   The node subscription details.
   */
  public function nodeNotificationSubscribe(
    string $node_id,
    string $email,
    int $frequency,
    bool $nomail = TRUE,
  ): array;

  /**
   * Unsubscribe from a node notification.
   *
   * @param string $node_id
   *   The node ID.
   * @param string $email
   *   The user email.
   * @param bool $request_authentication
   *   If the user is requested to authenticate.
   * @param string|null $redirect_to
   *   The URL to redirect after the unsubcription.
   */
  public function nodeNotificationUnsubscribe(
    string $node_id,
    string $email,
    bool $request_authentication = FALSE,
    ?string $redirect_to = '',
  ): void;

  /**
   * Check if a node is present in Newsroom.
   *
   * @param string $node_id
   *   The node ID.
   */
  public function nodeNotificationGet(string $node_id): array;

}
