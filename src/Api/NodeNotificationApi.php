<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Api;

use Drupal\oe_newsroom\Exception\Api\ApiException;

/**
 * Exposes endpoints related to node notifications.
 */
class NodeNotificationApi {

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
    // The response body in case of success will be a generic message and does
    // not matter.
    $this->apiClient->postJson(
      'node-notification/create',
      $payload,
      $signature_input,
    );
  }

  /**
   * Invokes the '/node-notification/delete' endpoint.
   *
   * This deletes all notifications for a given node.
   *
   * @param string $node_id
   *   The node ID.
   *
   * @return string
   *   Decoded response data.
   *   Typically this is just a generic success message.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The operation failed.
   */
  public function nodeNotificationDelete(string $node_id): string {
    $payload = [
      'item' => $signature_input = [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'node_id' => $node_id,
      ],
    ];
    $return = $this->apiClient->postJson(
      'node-notification/delete',
      $payload,
      $signature_input,
    );
    // The default response body will be:
    // "All unsent node notifications deleted successfully"
    // This is also the case when no notifications exist with that node id.
    if (!is_string($return)) {
      // In this place we don't have access to the request and response objects.
      // Therefore we can only throw a generic ApiException.
      throw new ApiException(sprintf('Expected a string message, found %d.', $return));
    }
    return $return;
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
      'node-notification/get',
      $query,
      $signature_input,
    );
  }

}
