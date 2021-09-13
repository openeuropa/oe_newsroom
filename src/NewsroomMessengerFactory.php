<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\oe_newsroom\Api\NewsroomMessenger;
use Drupal\oe_newsroom\Api\NewsroomMessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Service for creating newsroom messengers.
 *
 * This factory will return the standard messenger by default but allows to
 * override the messenger in settings.php:
 *
 * @code
 * $config['oe_newsroom.messenger']['class'] = 'Drupal\my_module\MyMessenger';
 * @endcode
 */
class NewsroomMessengerFactory implements NewsroomMessengerFactoryInterface {

  /**
   * The service container.
   *
   * @var \Symfony\Component\DependencyInjection\ContainerInterface
   */
  protected $container;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Constructs a new MessengerFactory object.
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The current service container.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The configuration factory.
   */
  public function __construct(ContainerInterface $container, ConfigFactoryInterface $configFactory) {
    $this->container = $container;
    $this->configFactory = $configFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function get(): NewsroomMessengerInterface {
    $subscriber_class = $this->configFactory->get('oe_newsroom.messenger')->get('class') ?? NewsroomMessenger::class;
    return $subscriber_class::create($this->container);
  }

}
