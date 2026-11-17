<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Handles node subscription confirmation redirects.
 */
class NodeSubscribeController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected readonly MessengerInterface $messenger,
    protected readonly TranslationInterface $translation,
    protected readonly UrlGeneratorInterface $urlGenerator,
    protected readonly RequestStack $requestStack,
  ) {}

  /**
   * Handles the result of a subscription confirmation.
   *
   * @param \Drupal\node\NodeInterface $node
   *   The node entity.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the node page.
   */
  public function verify(NodeInterface $node): RedirectResponse {
    if ((string) $this->requestStack->getCurrentRequest()->query->get('success') === '1') {
      $this->messenger->addMessage($this->translation->translate('You are now subscribed.'));
    }
    elseif ((string) $this->requestStack->getCurrentRequest()->query->get('success') === '0') {
      $this->messenger->addMessage($this->translation->translate('Something went wrong while confirming your subscription. Please try again.'), 'error');
    }

    return new RedirectResponse($this->urlGenerator->generateFromRoute('entity.node.canonical', ['node' => $node->id()]));
  }

}
