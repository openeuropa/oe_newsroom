<?php

namespace Drupal\Tests\oe_newsroom\Stabilization;

use Drupal\oe_newsroom_vcr\Capture\CapturingHelper;
use Drupal\Tests\oe_newsroom\Helper\BackwardsCompatibility;
use Drupal\Tests\oe_newsroom\Value\NewsroomTestValues;
use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Tag\TaggedValue;

/**
 * Provides stabilization of Newsroom VCR data.
 *
 * @internal
 */
class NewsroomVcrStabilization {

  const STABILIZED_TAG_NAME = 'Stabilized';

  /**
   * Creates a callback to unpack data from yaml before a replay.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   The resulting transformation.
   */
  public static function fnUnpackRecords(): \Closure {
    // Simply unpack specific tag names in the entire hierarchy.
    return Transform::deepRecursive(function (mixed $value): mixed {
      if ($value instanceof TaggedValue) {
        if (
          $value->getTag() === self::STABILIZED_TAG_NAME ||
          str_starts_with($value->getTag(), self::STABILIZED_TAG_NAME . '.')
        ) {
          return $value->getValue();
        }
      }
      return $value;
    });
  }

  /**
   * Creates a transformation to apply to recorded data before writing to file.
   *
   * @param \Drupal\Tests\oe_newsroom\Value\NewsroomTestValues $local_values
   *   Newsroom test values from `test-values.php`.
   * @param \Drupal\Tests\oe_newsroom\Value\NewsroomTestValues $virtual_values
   *   Newsroom test values from `test-values.example.php`.
   * @param callable(string, mixed): void $collect_captured
   *   A callback to collect captured values.
   *   The first parameter is the capture name, the second the actual value.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   The resulting transformation that will be applied to the complete list.
   */
  public static function fnPackRecords(
    NewsroomTestValues $local_values,
    NewsroomTestValues $virtual_values,
    callable $collect_captured,
  ): \Closure {
    $fn_date = Transform::uniqueDateString('2005-02-15 13:00:00', tag: static::STABILIZED_TAG_NAME . '.date');
    $local_values_array = $local_values->getVcrStabilizationDefaults();
    $virtual_values_array = $virtual_values->getVcrStabilizationDefaults();
    $fn_fn_default_key = fn (string $key): \Closure => Transform::lookupReplace(
      (array) $local_values_array[$key],
      (array) $virtual_values_array[$key],
      fn (mixed $value, string|int $delta) => new TaggedValue(
        self::STABILIZED_TAG_NAME . '.default.' . $key . ($delta !== 0 ? '.' . $delta : ''),
        $value,
      ),
    );

    $transformation = Transform::multiple([
      // Everything that looks like a date is treated as such, in requests and
      // in responses.
      Transform::deepRecursive($fn_date),
      // Further pack requests if they go to Newsroom API.
      self::fnPackNewsroomRequests($fn_fn_default_key, $collect_captured),
      self::fnPackNewsroomResponses($fn_fn_default_key),
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
   * @param \Closure(string): (\Closure(mixed): mixed) $fn_fn_default_key
   *   A callback to create a lookup function for default values.
   * @param callable(string, mixed): void $collect_captured
   *   A callback to collect captured values.
   *   The first parameter is the capture name, the second the actual value.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   A transformation to call on the full recording.
   */
  protected static function fnPackNewsroomRequests(\Closure $fn_fn_default_key, callable $collect_captured): \Closure {
    $fn_default_node_service_id = $fn_fn_default_key('service_id');
    $fn_default_section_id = $fn_fn_default_key('node_notification_section_id');
    $fn_default_email = $fn_fn_default_key('email');
    $fn_newsroom_request_data = Transform::nested([
      'sv_id' => $fn_default_node_service_id,
      'item.sv_id' => $fn_default_node_service_id,
      'subscription.sv_id' => $fn_default_node_service_id,
      'app' => $fn_fn_default_key('app_id'),
      'key' => self::fnCaptureString(
        '<signature key %d>',
        $collect_captured,
      ),
      'user_email' => $fn_default_email,
      'subscription.email' => $fn_default_email,
      'item.section_id' => $fn_default_section_id,
      // This parameter exists in '/auth/login'.
      'token' => self::fnCaptureString(
        '<login token %d>',
        $collect_captured,
      ),
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
   * @param \Closure(string): (\Closure(mixed): mixed) $fn_fn_default_key
   *   A callback to create a lookup function for default values.
   *
   * @return \Closure(list<TaggedValue>): list<TaggedValue>
   *   A transformation that applies to the full recorded history.
   */
  protected static function fnPackNewsroomResponses(\Closure $fn_fn_default_key): \Closure {
    $fn_fn_unique_int = fn (int $offset, string $label) => Transform::uniqueIntegerIncrement($offset, tag: static::STABILIZED_TAG_NAME . '.' . $label);
    $fn_fn_unique_string = fn (string $replace, string $pattern = '#.#') => Transform::uniquePatternSprintf($replace, $pattern, Transform::tag(static::STABILIZED_TAG_NAME));
    $fn_notification_id = $fn_fn_unique_int(10000, 'notification_id');
    $fn_topic_id = $fn_fn_unique_int(20000, 'topic_id');
    $fn_topic_name = $fn_fn_default_key('topic_name');
    $fn_service_name = $fn_fn_unique_string('Service name (%d)');
    $fn_default_email = $fn_fn_default_key('email');
    $fn_ignore_string = Transform::ifString(Transform::ignore('<ignored>', static::STABILIZED_TAG_NAME));
    $fn_item_type_id = $fn_fn_unique_int(30000, 'item_type_id');
    $fn_item_type_name = $fn_fn_unique_string('Item type name (%d)');
    $fn_universe_id = $fn_fn_unique_int(9000, 'universe_id');
    $fn_universe_name = $fn_fn_unique_string('Universe name (%d)');
    $fn_user_id = $fn_fn_unique_int(70000, 'user_id');

    $transformations_by_path = [
      '/newsroom/api/v1/node-notification/get' => Transform::assoc([
        'data' => Transform::multiple([
          // The order of records in the response can be random.
          // Order by id, to stabilize the recording.
          Transform::orderListByColumn('id'),
          Transform::eachAssocInArray([
            'id' => $fn_notification_id,
            'topics' => Transform::eachAssocInArray([
              'id' => $fn_topic_id,
              'service' => $fn_service_name,
              'name' => $fn_topic_name,
            ]),
          ]),
        ]),
      ]),
      '/newsroom/api/v1/subscriptions' => $fn_stabilize_subscriptions = Transform::assoc([
        'data' => Transform::eachAssocInArray([
          'email' => $fn_default_email,
          'universeId' => $fn_universe_id,
          'universeName' => $fn_universe_name,
          // The 'univers(e)Acronym' key is misspelled in the response.
          'universAcronym' => $fn_fn_default_key('universe'),
          'hostBy' => $fn_ignore_string,
          'newsletterId' => $fn_fn_default_key('service_id'),
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
      '/newsroom/api/v1/subscribe' => $fn_stabilize_subscriptions,
      '/newsroom/api/v1/auth/token' => Transform::assoc([
        'data' => Transform::assoc([
          // Generate a string that is 16 characters long.
          // Use '%03d' syntax for number padding like '007'.
          'token' => Transform::uniquePatternSprintf(
            'tokenABCu%04dxYz',
            '#^[a-zA-Z0-9]{16}$#',
            Transform::tag(static::STABILIZED_TAG_NAME),
          ),
          // For now the tests do not care how far the expiration date is in the
          // future. Simply generate a stabilized date string with the same date
          // format as the original value, and use microseconds to make the
          // values distinct.
          // All other dates in other responses have the simpler date format,
          // which is being stabilized globally across the recording.
          'expiration_date' => Transform::uniquePatternSprintf(
            '2099-05-05T01:01:01.%06dZ',
            // Use '%03d' syntax for number padding like '007'.
            '#^\d\d\d\d-\d\d-\d\dT\d\d:\d\d:\d\d\.\d{6}Z$#',
            Transform::tag(static::STABILIZED_TAG_NAME),
          ),
        ]),
      ]),
      '/newsroom/api/v1/auth/login' => Transform::assoc([
        'data' => Transform::assoc([
          'user_email' => $fn_default_email,
          'user_id' => $fn_user_id,
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

  /**
   * Gets a callback to replace a string with `!Capture <placeholder>`.
   *
   * @param string $template
   *   A sprintf() template for the replacement. Must contain '%d', which is
   *   filled with an incrementing index per distinct value.
   * @param callable(string, mixed): void $collect_captured
   *   A callback to collect captured values.
   *   The first parameter is the capture name, the second the actual value.
   * @param string $pattern
   *   A regular expression a value must match to be replaced.
   *
   * @return \Closure(mixed): mixed
   *   The resulting transformation.
   */
  protected static function fnCaptureString(
    string $template,
    callable $collect_captured,
    string $pattern = '#.#',
  ): \Closure {
    return Transform::uniquePatternSprintf(
      $template,
      '#.#',
      function (string $replacement, string $value) use ($collect_captured) {
        $collect_captured($replacement, $value);
        return new TaggedValue(
          CapturingHelper::CAPTURE_TAG_NAME,
          $replacement,
        );
      },
    );
  }

}
