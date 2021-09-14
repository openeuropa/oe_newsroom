<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom\Api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\oe_newsroom\OeNewsroom;
use GuzzleHttp\ClientInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This connects to the Newsroom API and makes the requests to it using config.
 *
 * This class using the configuration page to configure itself automatically.
 *
 * @package Drupal\oe_newsroom\Api
 */
class NewsroomMessenger extends NewsroomMessengerBase {

  /**
   * Messenger constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuration factory to automatically load configurations.
   * @param \Drupal\Core\Site\Settings $settings
   *   Required for API private key.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client to send requests to the API.
   */
  public function __construct(ConfigFactoryInterface $configFactory, Settings $settings, ClientInterface $httpClient) {
    $config = $configFactory->get(OeNewsroom::OE_NEWSLETTER_CONFIG_VAR_NAME);

    $this->privateKey = $settings::get('newsroom_api_private_key');
    $this->hashMethod = $config->get('hash_method');
    $this->normalized = $config->get('normalized');
    $this->universe = $config->get('universe');
    $this->app = $config->get('app');

    parent::__construct($httpClient);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('settings'),
      $container->get('http_client')
    );
  }

}
