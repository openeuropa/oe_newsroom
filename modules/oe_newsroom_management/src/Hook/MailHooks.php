<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_management\Hook;

use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Link;
use Drupal\Core\Mail\MailFormatHelper;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\oe_newsroom_management\TokenManager;

/**
 * Contains hooks to prepare outgoing emails.
 */
class MailHooks {

  use StringTranslationTrait;

  public function __construct(
    protected readonly TokenManager $tokenManager,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Implements hook_mail().
   */
  #[Hook('mail')]
  public function mail(string $key, array &$message, array $params): void {
    switch ($key) {
      case 'request_access_mail':
        $email = $params['email'];
        $hash = $this->tokenManager->get($email);
        $site_url = Url::fromRoute('<front>', [], ['absolute' => TRUE])->toString();
        $variables = [
          '@site_url' => $site_url,
          '@subscriptions_page_link' => Link::createFromRoute(
            $this->t('Access my subscriptions page'),
            'oe_newsroom_management.node_subscriptions_management_anonymous',
            [
              'email' => $email,
              'token' => $hash,
            ],
            [
              'absolute' => TRUE,
            ]
          )->toString(),
        ];

        $text = $this->t("You are receiving this e-mail because you requested access to your subscriptions page on @site_url. \r\n Click the following link to access your subscriptions page: @subscriptions_page_link \r\n If you didn't request access to your subscriptions page or you're not sure why you received this e-mail, you can delete it.", $variables);
        $message['subject'] .= $this->t('Access your subscriptions page on @site_url', ['@site_url' => $site_url]);
        $message['body'][] = MailFormatHelper::htmlToText($text);

        break;
    }
  }

}
