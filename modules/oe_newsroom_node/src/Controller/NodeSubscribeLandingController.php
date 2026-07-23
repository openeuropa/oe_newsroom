<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Link;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\Core\Url;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Controller for the landing url of the node subscribe hard opt-in email.
 */
class NodeSubscribeLandingController implements ContainerInjectionInterface {

  use AutowireTrait;
  use StringTranslationTrait;

  public function __construct(
    protected readonly MessengerInterface $messenger,
    TranslationInterface $translation,
  ) {
    $this->stringTranslation = $translation;
  }

  /**
   * Performs a redirect to a node page with a message.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node from the url.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   The redirect response.
   */
  public function landing(NodeInterface $node): Response {
    // The message will be shown in the page after the redirect.
    $this->messenger->addStatus($this->t(
      '<strong>Your email has been confirmed!</strong>
<p>You are now subscribed to this page, you can @manage_your_subscriptions.</p>',
      [
        '@manage_your_subscriptions' => Link::fromTextAndUrl(
          $this->t('manage and review your subscriptions'),
          Url::fromRoute('oe_newsroom_management.request_access'),
        )->toString(),
      ],
    ));
    $node_url = $node->toUrl('canonical', ['absolute' => TRUE]);
    return new RedirectResponse($node_url->toString(), 302);
  }

}
