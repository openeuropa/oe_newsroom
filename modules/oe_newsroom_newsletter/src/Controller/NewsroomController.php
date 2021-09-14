<?php

namespace Drupal\oe_newsroom_newsletter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\oe_newsroom\NewsroomMessengerFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Newsroom controller.
 *
 * This class was originally used to handle direct url unsubscription.
 * Currently, not used and it waits for it's repurpose.
 */
class NewsroomController extends ControllerBase {

  /**
   * API for newsroom calls.
   *
   * @var \Drupal\oe_newsroom\Api\NewsroomMessengerInterface
   */
  protected $newsroomMessenger;

  /**
   * {@inheritDoc}
   */
  public function __construct(NewsroomMessengerFactoryInterface $newsroomMessengerFactory) {
    $this->newsroomMessenger = $newsroomMessengerFactory->get();
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_newsroom.messenger_factory')
    );
  }

  /**
   * Unsubscribe an email from newsroom.
   *
   * @param string $email
   *   Email address.
   *
   * @return array
   *   Output nothing.
   *
   * @throws \GuzzleHttp\Exception\GuzzleException
   */
  public function unsubscribe(string $email) {
    if ($email === '') {
      return [];
    }

    if (!$this->newsroomMessenger->isSubscribed($email)) {
      $this->messenger()->addError($this->t('Your subscription has not been found in our system. If you still receive the newsletter, please @contact_us.',
        ['@contact_us' => Link::fromTextAndUrl($this->t('contact us'), Url::fromRoute('eci_forum_core.feedback'))->toString()]));
    }
    elseif ($this->newsroomMessenger->unsubscribe($email)) {
      $this->messenger()->addStatus($this->t("Your subscription has been successfully treated. You will not receive the European Citizens' Initiative newsletter again."));
    }
    return [];
  }

}
