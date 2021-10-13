<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter_mock\Plugin\ServiceMock;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\Core\State\State;
use Drupal\http_request_mock\ServiceMockPluginInterface;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Intercepts any HTTP request made to Newsroom.
 *
 * @ServiceMock(
 *   id = "newsroom",
 *   label = @Translation("Newsroom mock"),
 *   weight = 0,
 * )
 */
class NewsroomPlugin extends PluginBase implements ServiceMockPluginInterface, ContainerFactoryPluginInterface {

  /**
   * The key of the state entry that contains the mocked api subscriptions.
   */
  public const STATE_KEY = 'oe_newsroom.mock_api_subscriptions';

  /**
   * The state service.
   *
   * @var \Drupal\Core\State\StateInterface
   */
  protected $state;

  /**
   * {@inheritDoc}
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, State $state) {
    $this->state = $state;
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('state')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function applies(RequestInterface $request, array $options): bool {
    return $request->getUri()->getHost() === 'ec.europa.eu' && strpos($request->getUri()->getPath(), '/newsroom/api/v1/') === 0;
  }

  /**
   * {@inheritdoc}
   */
  public function getResponse(RequestInterface $request, array $options): ResponseInterface {
    switch ($request->getUri()->getPath()) {
      case '/newsroom/api/v1/subscriptions':
        return $this->subscriptions($request);

      case '/newsroom/api/v1/subscribe':
        return $this->subscribe($request);

      case '/newsroom/api/v1/unsubscribe':
        return $this->unsubscribe($request);
    }

    return new Response(404);
  }

  /**
   * Generates a Newsroom subscription array.
   *
   * @param string $universe
   *   Subscription list universe.
   * @param string $email
   *   Subscribed email address.
   * @param string $sv_id
   *   Distribution list ID.
   * @param string $language_code
   *   Language where the subscription was made.
   * @param bool $isNewSubscription
   *   Is it a new subscription?
   *
   * @return array
   *   Generated subscription array, similar to newsrooms one.
   */
  protected function generateSubscriptionArray(string $universe, string $email, string $sv_id, string $language_code, bool $isNewSubscription): array {
    return [
      'responseType' => 'json',
      'email' => $email,
      'firstName' => NULL,
      'lastName' => NULL,
      'organisation' => NULL,
      'country' => NULL,
      'position' => NULL,
      'twitter' => NULL,
      'facebook' => NULL,
      'linkedIn' => NULL,
      'phone' => NULL,
      'organisationShort' => NULL,
      'address' => NULL,
      'address2' => NULL,
      'postCode' => NULL,
      'city' => NULL,
      'department' => NULL,
      'media' => NULL,
      'website' => NULL,
      'role' => NULL,
      'universeId' => '1',
      'universeName' => 'TEST FORUM',
      'universAcronym' => $universe,
      'newsletterId' => $sv_id,
      'newsletterName' => 'Test newsletter distribution list',
      'status' => 'Valid',
      'unsubscriptionLink' => "https://ec.europa.eu/newsroom/$universe/user-subscriptions/unsubscribe/$email/RANDOM_STRING",
      'isNewUser' => NULL,
      'hostBy' => "$universe Newsroom",
      'profileLink' => "https://ec.europa.eu/newsroom/$universe/user-profile/123456789",
      'isNewSubscription' => $isNewSubscription,
      // Be careful, this is originally a translated text!
      'feedbackMessage' => $isNewSubscription ? 'Thanks for Signing Up to the service: Test Newsletter Service' : 'A subscription for this service is already registered for this email address',
      'language' => $language_code,
      'frequency' => 'On demand',
      'defaultLanguage' => '0',
      'pattern' => NULL,
    ];
  }

  /**
   * Fetching subscription(s).
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   Http request. If sv_id is provided, it will act as a filter. This
   *   function assumes that the provided sv_id is part of the same universe as
   *   it was queried (unmatching universe and sv_ids will generate an exception
   *   in the real API).
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Http response.
   */
  protected function subscriptions(RequestInterface $request): ResponseInterface {
    $data = Json::decode($request->getBody()->getContents());
    $universe = $data['subscription']['universeAcronym'];
    $email = $data['subscription']['email'];
    $svIds = explode(',', $data['subscription']['sv_id']);

    $subscriptions = $this->state->get(self::STATE_KEY, []);
    $current_subs = [];
    // If sv_id is present, then it used as a filter.
    if (count($svIds) > 0) {
      foreach ($svIds as $svId) {
        if (isset($subscriptions[$universe][$svId][$email]) && $subscriptions[$universe][$svId][$email]['subscribed']) {
          $current_subs[] = $this->generateSubscriptionArray($universe, $email, $svId, $subscriptions[$universe][$svId][$email]['language'], FALSE);
        }
      }
    }
    // If not present, then display all results.
    else {
      foreach ($subscriptions[$universe] as $svId => $subscription) {
        if (isset($subscription[$email]) && $subscription[$email]['subscribed']) {
          $current_subs[] = $this->generateSubscriptionArray($universe, $email, $svId, $subscription[$email]['language'], FALSE);
        }
      }
    }

    return new Response(200, [], Json::encode($current_subs));
  }

  /**
   * Generates a subscription.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   Http request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Http response.
   */
  protected function subscribe(RequestInterface $request): ResponseInterface {
    $data = Json::decode($request->getBody()->getContents());
    $universe = $data['subscription']['universeAcronym'];
    $email = $data['subscription']['email'];
    $svIds = explode(',', $data['subscription']['sv_id']);
    $relatedSvIds = isset($data['subscription']['relatedSv_Id']) ? explode(',', $data['subscription']['relatedSv_Id']) : [];
    $language = $data['subscription']['language'] ?? 'en';
    $topicExtId = isset($data['subscription']['topicExtId']) ? explode(',', $data['subscription']['topicExtId']) : [];

    $subscriptions = $this->state->get(self::STATE_KEY, []);
    $current_subs = [];
    foreach (array_merge($svIds, $relatedSvIds) as $svId) {
      // Select the first to returned as the normal API class, but the
      // webservice marks all as subscribed, so let's mark it here too.
      if (empty($subscriptions[$universe][$svId][$email]['subscribed'])) {
        $current_subs[] = $this->generateSubscriptionArray($universe, $email, (string) $svId, $language, TRUE);
      }
      else {
        $current_subs[] = $this->generateSubscriptionArray($universe, $email, (string) $svId, $language, FALSE);
      }

      $subscriptions[$universe][$svId][$email] = [
        'subscribed' => TRUE,
        'language' => $language,
        'topicExtId' => $topicExtId,
      ];
    }
    $this->state->set(self::STATE_KEY, $subscriptions);

    return new Response(200, [], Json::encode($current_subs));
  }

  /**
   * Generates an unsubscription.
   *
   * @param \Psr\Http\Message\RequestInterface $request
   *   Http request.
   *
   * @return \Psr\Http\Message\ResponseInterface
   *   Http response.
   */
  protected function unsubscribe(RequestInterface $request): ResponseInterface {
    parse_str($request->getUri()->getQuery(), $parameters);

    $universe = $parameters['app'];
    $email = $parameters['user_email'];
    // The API does not support multiple sv_ids in a single call.
    $svId = $parameters['sv_id'];

    $subscriptions = $this->state->get(self::STATE_KEY, []);

    // When you try to unsubscribe a user that newsroom does not have at all,
    // you will get an internal error which will converted by our API to this.
    if (!isset($subscriptions[$universe][$svId][$email])) {
      return new Response(404, [], 'Not found');
    }

    // When the user e-mail exists in the e-mail it will return the same message
    // regardless if it's subscribed or not previously.
    $subscriptions[$universe][$svId][$email] = [
      'subscribed' => FALSE,
      'language' => NULL,
      'topicExtId' => NULL,
    ];

    $this->state->set(self::STATE_KEY, $subscriptions);

    return new Response(200, [], 'User unsubscribed!');
  }

}
