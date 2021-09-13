<?php

namespace Drupal\oe_newsroom\Api;

use Drupal\Component\Serialization\Json;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_newsroom\Exception\InvalidApiConfiguration;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\ClientException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * This connects to the Newsroom API and makes the requests to it no conf.
 *
 * This class needs to be manually configured. It's a good choice when you need
 * to have a different setting then the configuration has.
 *
 * @package Drupal\oe_newsroom\Api
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class NewsroomMessengerBase implements NewsroomMessengerInterface {

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
   * Http client to send http messages.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Messenger constructor.
   *
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client to send requests to the API.
   */
  public function __construct(ClientInterface $httpClient) {
    $this->httpClient = $httpClient;

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
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('http_client')
    );
  }

  /**
   * Set configuration for the current instance.
   *
   * @param string $privateKey
   *   Private key for newsroom. Corresponds to the universe and application
   *   name.
   * @param string $hashMethod
   *   Hash method, must be md5 or sha256.
   * @param bool $normalized
   *   Are the email addresses must be normalised?
   * @param string $universe
   *   Universe.
   * @param string $app
   *   Application, must be part of the universe.
   *
   * @throws \InvalidArgumentException
   *   In case if one of the parameters doesn't have the correct requirement
   *   fulfilled.
   */
  public function setConfiguration(string $privateKey, string $hashMethod, bool $normalized, string $universe, string $app) {
    $hash_method = mb_strtolower($hashMethod);
    if (empty($privateKey)) {
      throw new \InvalidArgumentException($this->t("Private key can't be empty."));
    }
    if (empty($hash_method)) {
      throw new \InvalidArgumentException($this->t("Hash method can't be empty."));
    }
    elseif ($hash_method !== 'md5' && $hash_method !== 'sha256') {
      throw new \InvalidArgumentException($this->t("Hash method must be md5 or sha256."));
    }
    if (empty($universe)) {
      throw new \InvalidArgumentException($this->t("Universe can't be empty."));
    }
    if (empty($app)) {
      throw new \InvalidArgumentException($this->t("App can't be empty."));
    }

    $this->privateKey = $privateKey;
    $this->hashMethod = $hashMethod;
    $this->normalized = $normalized;
    $this->universe = $universe;
    $this->app = $app;
  }

  /**
   * Current class configuration.
   *
   * @codingStandardsIgnoreStart
   * @return array{hashMethod: string, normalized: bool, universe: string, app: string}
   *   Hash method, normalized, universe and app.
   * @codingStandardsIgnoreEnd
   */
  public function getConfiguration() {
    return [
      'hashMethod' => $this->hashMethod,
      'normalized' => $this->normalized,
      'universe' => $this->universe,
      'app' => $this->app,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public function subscriptionServiceConfigured(bool $throw_error = TRUE): bool {
    if (!$throw_error) {
      return $this->newsroomApiUsable;
    }

    if (!$this->newsroomApiUsable) {
      throw new InvalidApiConfiguration($this->t('The subscription service is not configured at the moment. Please try again later.'));
    }

    return TRUE;
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
    $this->subscriptionServiceConfigured();

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

    try {
      // Send the request.
      $request = $this->httpClient->request('POST', $this->subscriptionSubscribeUrl, ['json' => $input]);
      if ($request->getStatusCode() === 200) {
        $body = $request->getBody()->getContents();
        $data = Json::decode($body);

        if (empty($data)) {
          throw new BadResponseException($this->t('Empty response returned by Newsroom newsletter API.'), NULL);
        }

        $response = NULL;
        // @todo Support multiple distribution list.
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

        throw new BadResponseException($this->t('Newsroom API returned a response with HTTP status %status', ['%status' => $request->getStatusCode()]), NULL);
      }
    }
    catch (ClientException $e) {
      throw new BadResponseException($this->t('Invalid response returned by Newsroom API.'), $e->getRequest(), $e->getResponse());
    }

    throw new BadResponseException($this->t('The subscription service is not available at the moment. Please try again later.'), NULL);
  }

  /**
   * {@inheritDoc}
   */
  public function unsubscribe(string $email, array $svIds = []): bool {
    $this->subscriptionServiceConfigured();

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
      throw new BadResponseException($this->t('Invalid response returned by Newsroom API.'), $e->getRequest(), $e->getResponse());
    }
  }

  /**
   * {@inheritDoc}
   */
  public function isSubscribed(string $email, array $svIds = []): bool {
    $this->subscriptionServiceConfigured();

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
      throw new BadResponseException($this->t('Invalid response returned by Newsroom API.'), $e->getRequest(), $e->getResponse());
    }

    throw new BadResponseException($this->t('The subscription service is not available at the moment. Please try again later.'), NULL);
  }

}
