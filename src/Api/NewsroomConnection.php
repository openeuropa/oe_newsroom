<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom\Api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\oe_newsroom\Newsroom;

/**
 * Contains configuration for the connection to newsroom.
 *
 * @todo How should this behave if newsroom connection is not configured?
 */
class NewsroomConnection {

  /**
   * A URL of the API.
   *
   * @todo Convert this to a setting.
   */
  public const API_URL = 'https://ec.europa.eu/newsroom/api/v1';

  /**
   * Constructor.
   *
   * @param string $url
   *   The Newsroom server URL.
   * @param string $privateKey
   *   A private key associated with the app id and universe.
   * @param string $hashMethod
   *   The hash method.
   * @param bool $normalised
   *   TRUE, if email addresses should be normalized to lowercase.
   * @param string $universe
   *   The universe alias.
   * @param string $appId
   *   The application id.
   * @param string $nodeServiceId
   *   The service id for node notifications.
   */
  public function __construct(
    public readonly string $url,
    // @todo Should this be part of the server connection object?
    #[\SensitiveParameter]
    public readonly string $privateKey,
    public readonly string $hashMethod,
    public readonly bool $normalised,
    public readonly string $universe,
    public readonly string $appId,
    public readonly string $nodeServiceId,
  ) {}

  /**
   * Creates a new instance.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The Drupal config factory.
   * @param \Drupal\Core\Site\Settings $settings
   *   The Drupal settings.
   *
   * @return static
   *   New instance.
   *
   * @todo When the client and this class are moved to a separate package, this
   *   method needs to remain in Drupal as a factory.
   */
  public static function create(
    ConfigFactoryInterface $configFactory,
    Settings $settings,
  ) {
    $config = $configFactory->get(Newsroom::CONFIG_NAME);
    return new static(
      self::API_URL,
      $settings->get('oe_newsroom')['newsroom_api_key'] ?? '',
      $config->get('hash_method'),
      $config->get('normalised'),
      $config->get('universe'),
      $config->get('app_id'),
      $config->get('node_service_id'),
    );
  }

  /**
   * Checks if the class is functional.
   *
   * @return bool
   *   True if the class is functional.
   */
  public function isConfigured(): bool {
    // These fields should be filled up and have no default value. Without them,
    // it's not possible to communicate with Newsroom.
    return $this->privateKey !== '' && $this->universe !== '' && $this->appId !== '';
  }

}
