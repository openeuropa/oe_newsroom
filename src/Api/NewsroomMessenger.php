<?php

namespace Drupal\oe_newsroom\Api;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Messenger\MessengerInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_newsroom\OeNewsroom;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;

/**
 * This connects to the Newsroom API and makes the requests to it.
 *
 * @package Drupal\oe_newsroom\Api
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class NewsroomMessenger implements NewsroomMessengerInterface {

  use StringTranslationTrait;

  /**
   * Private key for communication.
   *
   * @var string
   */
  protected $privateKey;

  /**
   * Hash generation method.
   *
   * @var string
   */
  protected $hashMethod;

  /**
   * Api waits for normalized data in hash or not.
   *
   * @var bool
   */
  protected $normalized;

  /**
   * Universe Acronym which is usually the site's name acronym.
   *
   * @var string
   */
  protected $universe;

  /**
   * App short name.
   *
   * @var string
   */
  protected $app;

  /**
   * Api endpoint for topics.
   *
   * @var string
   */
  protected $topicUrl;

  /**
   * Api endpoint for getting subscriber data.
   *
   * @var string
   */
  protected $subscriptionDataUrl;

  /**
   * Api endpoint to subscribe somebody.
   *
   * @var string
   */
  protected $subscriptionSubscribeUrl;

  /**
   * Api endpoint to unsubscribe somebody.
   *
   * @var string
   */
  protected $subscriptionUnsubscribeUrl;

  /**
   * True if we have all the information which is needed to use the Api.
   *
   * @var bool
   */
  protected $newsroomApiUsable;

  /**
   * Type of the response what we waiting for.
   *
   * @var string
   */
  protected $responseType = 'json';

  /**
   * Http client to send http messages.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Messenger service.
   *
   * @var \Drupal\Core\Messenger\MessengerInterface
   */
  protected $messenger;

  /**
   * Messenger constructor.
   */
  public function __construct(ConfigFactoryInterface $configFactory, Settings $settings, ClientInterface $httpClient, MessengerInterface $messenger) {
    $config = $configFactory->get(OeNewsroom::OE_NEWSLETTER_CONFIG_VAR_NAME);

    $this->privateKey = $settings::get('newsroom_api_private_key');
    $this->hashMethod = $config->get('hash_method');
    $this->normalized = $config->get('normalized');
    $this->universe = $config->get('universe');
    $this->app = $config->get('app');
    $this->httpClient = $httpClient;
    $this->messenger = $messenger;

    // These fields should be filled up and has no default value, without it
    // it's not possible to communicate with newsroom.
    $this->newsroomApiUsable = !empty($this->privateKey) && !empty($this->universe) && !empty($this->app);

    // Api endpoints.
    $this->topicUrl = "https://ec.europa.eu/newsroom/api/v1/topic";
    $this->subscriptionDataUrl = "https://ec.europa.eu/newsroom/api/v1/subscriptions";
    $this->subscriptionSubscribeUrl = "https://ec.europa.eu/newsroom/api/v1/subscribe";
    $this->subscriptionUnsubscribeUrl = "https://ec.europa.eu/newsroom/api/v1/unsubscribe";
  }

  /**
   * {@inheritDoc}
   */
  public function subscriptionServiceConfigured(): bool {
    if (!$this->newsroomApiUsable) {
      $this->messenger->addError($this->t('The subscription service is not configured at the moment. Please try again later.'));
    }

    return $this->newsroomApiUsable;
  }

  /**
   * Generates a key from the e-mail and from the private key.
   *
   * @param string $email
   *   Subscriber e-mail.
   *
   * @return string
   *   Generated communication key.
   */
  protected function generateKey(string $email): string {
    if ($this->normalized) {
      return hash($this->hashMethod, mb_strtolower($email) . $this->privateKey);
    }

    return hash($this->hashMethod, $email . $this->privateKey);
  }

  /**
   * {@inheritDoc}
   */
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], string $language = NULL, array $topicExtId = []) : ?array {
    if (!$this->subscriptionServiceConfigured()) {
      return NULL;
    }

    // Prepare the post.
    $input = [
      'key' => $this->generateKey($email),
      'subscription' => [
        'universeAcronym' => $this->universe,
        'topicExtWebsite' => $this->app,
        'sv_id' => implode(',', $svIds),
        'email' => $this->normalized ? mb_strtolower($email) : $email,
        'language' => $language,
      ],
    ];

    if (!empty($relatedSvIds)) {
      $input['subscription']['relatedSv_Id'] = implode(',', $relatedSvIds);
    }
    if (!empty($topicExtId)) {
      $input['subscription']['topicExtId'] = implode(',', $topicExtId);
    }

    // Send the request.
    $request = $this->httpClient->request('POST', $this->subscriptionSubscribeUrl, ['json' => $input]);
    if ($request->getStatusCode() === 200) {
      $body = $request->getBody()->getContents();
      $data = Json::decode($body);

      $response = NULL;
      foreach ($data as $subscription_item) {
        // This will fetch only the first item found.
        if (in_array($subscription_item['newsletterId'], $svIds, FALSE)) {
          $response = $subscription_item;
          break;
        }
      }
      if (isset($response)) {
        return $response;
      }

      $this->messenger->addError($this->t('Response decoding failed.'));
    }

    $this->messenger->addError($this->t('The subscription service is not available at the moment. Please try again later.'));
    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function unsubscribe(string $email, array $svIds = []): ?bool {
    if (!$this->subscriptionServiceConfigured()) {
      return NULL;
    }

    $options = [
      'query' => [
        'user_email' => $this->normalized ? mb_strtolower($email) : $email,
        'key' => $this->generateKey($email),
        'app' => $this->app,
        'sv_id' => implode(',', $svIds),
      ],
    ];
    try {
      // Send the request.
      $response = $this->httpClient->get($this->subscriptionUnsubscribeUrl, $options);

      // If the unsubscription was success the API returns HTTP code 200.
      // And a text message in the HTTP message body that we don't need now.
      return $response->getStatusCode() === 200;
    }
    catch (ClientException $e) {
      throw new BadResponseException('Invalid response returned by Newsroom API.', $e->getRequest(), $e->getResponse());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isSubscribed(string $email, array $svIds = []): ?bool {
    if (!$this->subscriptionServiceConfigured()) {
      return NULL;
    }

    $options = [
      'query' => [
        'user_email' => $this->normalized ? mb_strtolower($email) : $email,
        'key' => $this->generateKey($email),
        'app' => $this->app,
        'universe_acronym' => $this->universe,
        'sv_id' => $svIds,
      ],
    ];

    try {
      $response = $this->httpClient->get($this->subscriptionDataUrl, $options);
      if ($response->getStatusCode() === 200) {
        $subscriptions = Json::decode((string) $response->getBody()->getContents());
        return !($subscriptions === NULL || count($subscriptions) === 0);
      }
    }
    catch (ClientException $e) {
      throw new BadResponseException('Invalid response returned by Newsroom API.', $e->getRequest(), $e->getResponse());
    }

    return NULL;
  }

}
