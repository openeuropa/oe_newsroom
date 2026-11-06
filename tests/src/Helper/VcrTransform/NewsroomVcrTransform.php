<?php

namespace Drupal\Tests\oe_newsroom\Helper\VcrTransform;

use Drupal\oe_newsroom_vcr\Helper\CapturingHelper;
use Drupal\Tests\oe_newsroom\Helper\BackwardsCompatibility;
use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Provides transformations specific to Newsroom VCR data.
 *
 * @internal
 */
class NewsroomVcrTransform {

  const REPLACED_TAG_NAME = 'Replaced';

  /**
   * Creates a callback to unpack data from yaml before a replay.
   *
   * @param array{node_service_id: int, app_id: string} $default_values
   *   Newsroom configuration values.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   The resulting transformation.
   */
  public static function fnUnpackRecords(array $default_values): \Closure {
    // Simply unpack specific tag names in the entire hierarchy.
    return Transform::resolveTagsRecursive([
      // The 'Replaced' tag resolves to its value.
      'Replaced' => Transform::identity(),
      // For the 'Default' tag, the value identifies a default value.
      'Default' => function ($value) use ($default_values) {
        Assert::assertIsString($value);
        Assert::assertArrayHasKey($value, $default_values);
        // Normally, empty values should not be exported as default.
        Assert::assertNotEmpty($default_values[$value]);
        return $default_values[$value];
      },
    ]);
  }

  /**
   * Creates a transformation to apply to recorded data before writing to file.
   *
   * @param array{node_service_id: int, app_id: string} $default_values
   *   Newsroom configuration values and additional values.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   The resulting transformation.
   */
  public static function fnPackRecords(array $default_values): \Closure {
    $fn_date = Transform::uniqueDateString('2005-02-15 13:00:00', tag: static::REPLACED_TAG_NAME);
    $transformation = Transform::multiple([
      // Everything that looks like a date is treated as such, in requests and
      // in responses.
      Transform::deepRecursive($fn_date),
      // Further pack requests if they go to Newsroom API.
      self::fnPackNewsroomRequests($default_values),
      self::fnPackNewsroomResponses($default_values),
    ]);
    // Wrap with assertions, to match the documented return type.
    return function (mixed $value) use ($transformation): array {
      BackwardsCompatibility::assertIsList($value);
      $transformed = $transformation($value);
      BackwardsCompatibility::assertIsList($transformed);
      return $transformed;
    };
  }

  /**
   * Creates a transformation to pack Newsroom API requests.
   *
   * @param array{node_service_id: int, app_id: string} $newsroom_settings
   *   Newsroom configuration values.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   A transformation to call on the full recording.
   */
  protected static function fnPackNewsroomRequests(array $newsroom_settings): \Closure {
    $fn_fn_default_key = fn (string $key) => Transform::replace(
      $newsroom_settings[$key],
      new TaggedValue('Default', $key),
    );
    $fn_default_node_service_id = $fn_fn_default_key('node_service_id');
    $fn_default_section_id = $fn_fn_default_key('node_notification_section_id');
    $fn_default_email = $fn_fn_default_key('test_email');
    $fn_signature_key = Transform::uniquePatternSprintf(
      '<signature key %d>',
      '#.#',
      CapturingHelper::CAPTURE_TAG_NAME,
    );
    $fn_newsroom_request_data = Transform::nested([
      'sv_id' => $fn_default_node_service_id,
      'item.sv_id' => $fn_default_node_service_id,
      'subscription.sv_id' => $fn_default_node_service_id,
      'app' => $fn_fn_default_key('app_id'),
      'key' => $fn_signature_key,
      'user_email' => $fn_default_email,
      'subscription.email' => $fn_default_email,
      'item.section_id' => $fn_default_section_id,
    ]);
    $fn_transform_request = Transform::ifTag(
      'NewsroomRequest',
      Transform::assoc([
        'data' => $fn_newsroom_request_data,
        'query' => $fn_newsroom_request_data,
      ]),
    );
    return Transform::eachInArray($fn_transform_request);
  }

  /**
   * Creates a transformation to pack Newsroom responses.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   A transformation that applies to the full recorded history.
   */
  protected static function fnPackNewsroomResponses(array $newsroom_settings): \Closure {
    $fn_fn_default_key = fn (string $key) => Transform::replace(
      $newsroom_settings[$key],
      new TaggedValue('Default', $key),
    );
    $fn_fn_unique_int = fn (int $offset) => Transform::uniqueIntegerIncrement($offset, tag: static::REPLACED_TAG_NAME);
    $fn_fn_unique_string = fn (string $replace, string $pattern = '#.#') => Transform::uniquePatternSprintf($replace, $pattern, static::REPLACED_TAG_NAME);
    $fn_notification_id = $fn_fn_unique_int(10000);
    $fn_topic_id = $fn_fn_unique_int(20000);
    $fn_topic_name = $fn_fn_unique_string('Topic name (%d)');
    $fn_service_name = $fn_fn_unique_string('Service name (%d)');
    $fn_default_email = $fn_fn_default_key('test_email');
    $fn_ignore_string = Transform::ifString(Transform::ignore('<ignored>', static::REPLACED_TAG_NAME));
    $fn_item_type_id = $fn_fn_unique_int(30000);
    $fn_item_type_name = $fn_fn_unique_string('Item type name (%d)');
    $fn_universe_id = $fn_fn_unique_int(9000);
    $fn_universe_name = $fn_fn_unique_string('Universe name (%d)');

    $transformations_by_path = [
      '/newsroom/api/v1/node-notification/get' => Transform::assoc([
        'data' => Transform::eachAssocInArray([
          'id' => $fn_notification_id,
          'topics' => Transform::eachAssocInArray([
            'id' => $fn_topic_id,
            'name' => $fn_topic_name,
            'service' => $fn_service_name,
          ]),
        ]),
      ]),
      '/newsroom/api/v1/subscriptions' => Transform::assoc([
        'data' => Transform::eachAssocInArray([
          'email' => $fn_default_email,
          'universeId' => $fn_universe_id,
          'universeName' => $fn_universe_name,
          // The 'univers(e)Acronym' key is misspelled in the response.
          'universAcronym' => $fn_fn_default_key('universe'),
          'hostBy' => $fn_ignore_string,
          'newsletterId' => $fn_fn_default_key('node_service_id'),
          'newsletterName' => $fn_service_name,
          'unsubscriptionLink' => $fn_ignore_string,
          'profileLink' => $fn_ignore_string,
          'pattern' => $fn_ignore_string,
          'subscribedNotificationItemType' => Transform::eachAssocInArray([
            'name' => $fn_item_type_name,
            'id' => $fn_item_type_id,
          ]),
          'subscribedNotificationTopicType' => Transform::eachAssocInArray([
            'id' => $fn_topic_id,
            'groupId' => $fn_ignore_string,
            'groupName' => $fn_ignore_string,
          ]),
        ]),
      ]),
    ];
    return static::fnTransformNewsroomResponsesByPath($transformations_by_path);
  }

  /**
   * Gets a transformation to pack responses, operating on the full VCR list.
   *
   * @param array<string, callable(array, array): array> $transformations_by_path
   *   Transformations by path, called on each response array.
   *   The second parameter is the request array.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   A transformation that applies to the full recorded history.
   */
  protected static function fnTransformNewsroomResponsesByPath(array $transformations_by_path): \Closure {
    return function ($records) use ($transformations_by_path): array {
      if (!array_is_list($records)) {
        return $records;
      }
      foreach ($records as $delta => $record) {
        if (!$record instanceof TaggedValue || $record->getTag() !== 'Response') {
          continue;
        }
        $response = $record->getValue();
        $previous = $records[$delta - 1] ?? NULL;
        Assert::assertInstanceOf(TaggedValue::class, $previous);
        if ($previous->getTag() !== 'NewsroomRequest') {
          continue;
        }
        $request = $previous->getValue();
        $path = $request['path'] ?? NULL;
        Assert::assertIsString($path);
        $transformation = $transformations_by_path[$path] ?? NULL;
        if ($transformation === NULL) {
          continue;
        }
        $transformed_response = $transformation($response, $request);
        $records[$delta] = new TaggedValue('Response', $transformed_response);
      }
      return $records;
    };
  }

}
