<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Endpoint;

use Drupal\oe_newsroom\Api\ApiClient;
use Drupal\oe_newsroom\Attribute\NewsroomApiExplorer;

/**
 * Exposes endpoints related to node notifications.
 */
#[NewsroomApiExplorer]
class NodeNotificationEndpoints {

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
    // Don't pass date if empty.
    $payload['item'] = array_filter($payload['item'], fn ($value, $key) => match ($key) {
      'createDate' => $value !== '' && $value !== NULL,
      default => TRUE,
    }, ARRAY_FILTER_USE_BOTH);
    // The response body in case of success will be a generic message and does
    // not matter.
    $this->apiClient->post(
      'node-notification/create',
      $payload,
      $signature_input,
      ['notification_title', 'notification_description'],
    );
  }

  /**
   * Invokes the '/node-notification/delete' endpoint.
   *
   * This deletes all notifications for a given node.
   *
   * @param string $node_id
   *   The node ID.
   *   Normally this should be an integer, but technically any string is
   *   accepted.
   * @param bool $delete_topic
   *   TRUE to actually delete the notifications.
   *   FALSE to only delete unsent notifications.
   *
   * @throws \Drupal\oe_newsroom\Exception\Api\NotFoundException
   *   The node service id was not found.
   *   This still indicates a misconfiguration.
   * @throws \Drupal\oe_newsroom\Exception\Api\ApiException
   *   The operation failed.
   */
  public function nodeNotificationDelete(string $node_id, bool $delete_topic): void {
    $payload = [
      'item' => $signature_input = [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'node_id' => $node_id,
      ],
    ];
    if ($delete_topic) {
      $payload['item']['deleteTopic'] = TRUE;
      $signature_input[] = 'deleteTopic';
    }
    // The default response body will be:
    // - "All unsent node notifications deleted successfully",
    //   if 'deleteTopic' is FALSE or not set.
    // - "Node and all associated data deleted successfully"
    //   if 'deleteTopic' is TRUE.
    // There is no distinction if the node already did not exist.
    // Therefore there is no point for this method to return anything.
    $this->apiClient->post(
      'node-notification/delete',
      $payload,
      $signature_input,
      // Do not normalize any of the signature input parts.
      [],
    );
  }

  /**
   * Fetches existing notifications for a node.
   *
   * @param string $node_id
   *   The node ID.
   *   Normally this should be an integer, but technically any string is
   *   accepted.
   *
   * @return list<array>
   *   Decoded response data.
   */
  public function nodeNotificationGet(string $node_id): array {
    $query = [
      ...$signature_input = [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'node_id' => $node_id,
      ],
    ];
    return $this->apiClient
      ->get(
        'node-notification/get',
        $query,
        $signature_input,
      )
      ->map(function (string|array $data): array {
        if (!is_array($data) || !array_is_list($data)) {
          throw new \InvalidArgumentException('Expected a list.');
        }
        foreach ($data as $index => $record) {
          if (!is_array($record)) {
            throw new \InvalidArgumentException("Expected an array at index $index.");
          }
        }
        return $data;
      });
  }

  /**
   * Gets the notification count for a node id.
   *
   * @param string $node_id
   *   The node ID.
   *   Normally this should be an integer, but technically any string is
   *   accepted.
   *
   * @return int
   *   The number of notification records.
   */
  public function nodeNotificationCount(string $node_id): int {
    $query = [
      ...$signature_input = [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'node_id' => $node_id,
      ],
    ];
    return $this->apiClient
      ->get(
        'node-notification/count',
        $query,
        $signature_input,
      )
      ->map(function (string|array $data): int {
        if (
          !is_array($data) ||
          array_keys($data) !== ['count'] ||
          !is_int($data['count'])
        ) {
          throw new \InvalidArgumentException('Expected array{count: int}.');
        }
        return $data['count'];
      });
  }

  /**
   * Checks if a node id is known in the notification service.
   *
   * @param string $node_id
   *   The node ID.
   *   Normally this should be an integer, but technically any string is
   *   accepted.
   *
   * @return bool
   *   TRUE if the notification exists, FALSE if not.
   */
  public function nodeNotificationExists(string $node_id): bool {
    $query = [
      ...$signature_input = [
        'sv_id' => $this->apiClient->getNodeServiceId(),
        'node_id' => $node_id,
      ],
    ];
    return $this->apiClient
      ->get(
        'node-notification/exists',
        $query,
        $signature_input,
      )
      ->map(function (string|array $data): bool {
        if (
          !is_array($data) ||
          array_keys($data) !== ['exists'] ||
          !is_bool($data['exists'])
        ) {
          throw new \InvalidArgumentException('Expected array{exists: bool}.');
        }
        return $data['exists'];
      });
  }

}
