<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Traits;

use Drupal\oe_newsroom_newsletter_mock\Plugin\ServiceMock\NewsroomPlugin;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\ResponseInterface;

/**
 * Contains methods to interact with the mocked client responses.
 */
trait NewsroomClientMockTrait {

  /**
   * Returns the requests that where made to the Newsroom newsletter endpoint.
   *
   * @return array
   *   An array of requests received by the Newsroom service mock plugin.
   */
  protected function getNewsroomClientRequests(): array {
    $state = \Drupal::state();
    $state->resetCache();

    return $state->get(NewsroomPlugin::STATE_KEY_REQUESTS, []);
  }

  /**
   * Deletes all the requests stored in state.
   */
  protected function clearNewsroomClientRequests(): void {
    \Drupal::state()->delete(NewsroomPlugin::STATE_KEY_REQUESTS);
  }

  /**
   * Sets the next response to be returned by the mocked Newsroom endpoint.
   *
   * @param \Psr\Http\Message\ResponseInterface $response
   *   The response object.
   */
  protected function setNextNewsroomClientResponse(ResponseInterface $response): void {
    \Drupal::state()->set(NewsroomPlugin::STATE_KEY_NEXT_RESPONSE, Message::toString($response));
  }

}
