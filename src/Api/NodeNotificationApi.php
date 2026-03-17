<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Api;

use Drupal\oe_newsroom\Exception\Api\ApiException;

/**
 * Exposes endpoints related to node notifications.
 */
class NodeNotificationApi {

  /**
   * Constructor.
   *
   * @param \Drupal\oe_newsroom\Api\ApiClient $apiClient
   *   The API client service, a wrapper around the http client.
   */
  public function __construct(
    protected readonly ApiClient $apiClient,
  ) {}

  /**
   * Create node notification.
   *
   * @param int $section_id
   *   The section id.
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
    int $section_id,
    string $notification_title,
    string $notification_description,
    string $notification_url,
    string $node_id,
    string $node_title,
    // @todo Convert to DateTimeInterface.
    string $create_date = '',
  ): void {
    $payload = [
      'item' => [
        ...$signature_input = [
          'sv_id' => $this->apiClient->getNodeServiceId(),
          'section_id' => (string) $section_id,
          'notification_title' => $notification_title,
          'notification_description' => $notification_description,
          'notification_URL' => $notification_url,
          'node_id' => $node_id,
        ],
        'node_title' => $node_title,
        // @todo Is this really mandatory?
        // The format should be like '2024-12-23T13:45:00.000Z'.
        'createDate' => $create_date,
      ],
    ];
    $return = $this->apiClient->postJson(
      'node-notification/create',
      $payload,
      $signature_input,
    );
    if ($return === 'Node notification created successfully') {
      return;
    }
    // @todo Distinguish different return values.
    throw new ApiException(sprintf('Unexpected value %s returned from create notification request.', var_export($return, TRUE)));
  }

  /**
   * Delete node notification.
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return array
   *   Decoded response data.
   */
  public function nodeNotificationDelete(string $node_id): array {
    $payload = [
      'item' => $signature_input = [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'node_id' => $node_id,
      ],
    ];
    return $this->apiClient->postJson(
      'node-notification/delete',
      $payload,
      $signature_input,
    );
  }

  /**
   * Fetches existing notifications for a node.
   *
   * @param int $node_id
   *   The node ID.
   *
   * @return array
   *   Decoded response data.
   */
  public function nodeNotificationGet(int $node_id): array {
    $query = [
      ...$signature_input = [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'node_id' => $node_id,
      ],
    ];
    return $this->apiClient->fetchJson(
      'node-notifications/get',
      $query,
      $signature_input,
    );
  }

}
