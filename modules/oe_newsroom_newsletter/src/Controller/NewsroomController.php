<?php

namespace Drupal\oe_newsroom_newsletter\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\oe_newsroom\Api\NewsroomMessengerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Newsroom controller.
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
  public function __construct(NewsroomMessengerInterface $newsroomMessenger) {
    $this->newsroomMessenger = $newsroomMessenger;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('oe_newsroom.messenger')
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
