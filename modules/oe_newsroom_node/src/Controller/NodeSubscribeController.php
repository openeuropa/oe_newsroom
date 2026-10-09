<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_node\Controller;

use Drupal\Core\DependencyInjection\AutowireTrait;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Routing\UrlGeneratorInterface;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\node\NodeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Handles node subscription confirmation redirects.
 */
class NodeSubscribeController implements ContainerInjectionInterface {

  use AutowireTrait;

  public function __construct(
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly MessengerInterface $messenger,
    protected readonly TranslationInterface $translation,
    protected readonly UrlGeneratorInterface $urlGenerator,
    protected readonly RequestStack $requestStack,
  ) {}

  /**
   * Handles the result of a subscription confirmation.
   *
   * @param int $node
   *   The node ID.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the node page.
   */
  public function verify(int $node): RedirectResponse {
    $node_entity = $this->entityTypeManager->getStorage('node')->load($node);
    if (!$node_entity instanceof NodeInterface || !$node_entity->access('view')) {
      throw new NotFoundHttpException();
    }

    if ((string) $this->requestStack->getCurrentRequest()->query->get('success') === '1') {
      $this->messenger->addMessage($this->translation->translate('You are now subscribed.'));
    }
    elseif ((string) $this->requestStack->getCurrentRequest()->query->get('success') === '0') {
      $this->messenger->addMessage($this->translation->translate('Something went wrong while confirming your subscription. Please try again.'), 'error');
    }

    return new RedirectResponse($this->urlGenerator->generateFromRoute('entity.node.canonical', ['node' => $node_entity->id()]));
  }

}
