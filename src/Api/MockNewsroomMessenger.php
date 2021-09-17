<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom\Api;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\State\StateInterface;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This connects to the Newsroom API and makes the requests to it.
 *
 * This class used only for testing purpose.
 *
 * @package Drupal\oe_newsroom\Api
 */
class MockNewsroomMessenger extends NewsroomMessenger {

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
   * Messenger constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuration factory to automatically load configurations.
   * @param \Drupal\Core\Site\Settings $settings
   *   Required for API private key.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client to send requests to the API.
   * @param \Drupal\Core\State\StateInterface $state
   *   The state service.
   */
  public function __construct(ConfigFactoryInterface $configFactory, Settings $settings, ClientInterface $httpClient, StateInterface $state) {
    $this->state = $state;
    parent::__construct($configFactory, $settings, $httpClient);
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('settings'),
      $container->get('http_client'),
      $container->get('state')
    );
  }

  /**
   * Generates a Newsroom subscription array.
   *
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
  protected function generateSubscriptionArray(string $email, string $sv_id, string $language_code, bool $isNewSubscription) {
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
      'universAcronym' => $this->universe,
      'newsletterId' => $sv_id,
      'newsletterName' => 'Test newsletter distribution list',
      'status' => 'Valid',
      'unsubscriptionLink' => "https://ec.europa.eu/newsroom/$this->universe/user-subscriptions/unsubscribe/$email/RANDOM_STRING",
      'isNewUser' => NULL,
      'hostBy' => "$this->universe Newsroom",
      'profileLink' => "https://ec.europa.eu/newsroom/$this->universe/user-profile/123456789",
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
   * {@inheritdoc}
   */
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], string $language = NULL, array $topicExtId = []): ?array {
    $this->subscriptionServiceConfigured();

    $subscriptions = $this->state->get(self::STATE_KEY, []);
    $first_subs = NULL;
    foreach ($svIds as $svId) {
      if ($first_subs === NULL) {
        // Select the first to returned as the normal API class, but the
        // webservice marks all as subscribed, so let's mark it here too.
        if (!empty($subscriptions[$this->universe][$svId][$email])) {
          $first_subs = $this->generateSubscriptionArray($email, $svId, $language, TRUE);
        }
        else {
          $first_subs = $this->generateSubscriptionArray($email, $svId, $language, FALSE);
        }
      }

      $subscriptions[$this->universe][$svId][$email] = TRUE;
    }
    $this->state->set(self::STATE_KEY, $subscriptions);

    return $first_subs;
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe(string $email, array $svIds = []): bool {
    $this->subscriptionServiceConfigured();
    $subscriptions = $this->state->get(self::STATE_KEY, []);

    foreach ($svIds as $svId) {
      // When you try to unsubscribe a user that newsroom does not have at all,
      // you will get an internal error which will converted by our API to this.
      if (!isset($subscriptions[$this->universe][$svId][$email])) {
        throw new BadResponseException($this->t('Invalid response returned by Newsroom API.')->render(), NULL);
      }

      $subscriptions[$this->universe][$svId][$email] = FALSE;
    }
    $this->state->set(self::STATE_KEY, $subscriptions);

    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function isSubscribed(string $email, array $svIds = []): bool {
    $this->subscriptionServiceConfigured();
    $subscriptions = $this->state->get(self::STATE_KEY, []);

    foreach ($svIds as $svId) {
      if ($subscriptions[$this->universe][$svId][$email]) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
