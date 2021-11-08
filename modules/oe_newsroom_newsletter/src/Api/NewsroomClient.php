<?php

declare(strict_types = 1);

namespace Drupal\oe_newsroom_newsletter\Api;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_newsroom\Exception\InvalidApiConfiguration;
use Drupal\oe_newsroom\OeNewsroom;
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
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @internal
 */
final class NewsroomClient implements NewsroomClientInterface {

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
  protected $appId;

  /**
   * Http client to send http messages.
   *
   * @var \GuzzleHttp\Client
   */
  protected $httpClient;

  /**
   * Client constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   Configuration factory to automatically load configurations.
   * @param \Drupal\Core\Site\Settings $settings
   *   Required for API private key.
   * @param \GuzzleHttp\ClientInterface $httpClient
   *   Http client to send requests to the API.
   */
  protected function __construct(ConfigFactoryInterface $configFactory, Settings $settings, ClientInterface $httpClient) {
    $config = $configFactory->get(OeNewsroom::CONFIG_NAME);

    $this->privateKey = $settings->get('oe_newsroom')['newsroom_api_key'];
    $this->hashMethod = $config->get('hash_method');
    $this->normalized = $config->get('normalized');
    $this->universe = $config->get('universe');
    $this->appId = $config->get('app_id');
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('config.factory'),
      $container->get('settings'),
      $container->get('http_client')
    );
  }

  /**
   * Checks if the class is functional.
   *
   * @return bool
   *   True if the class is functional.
   */
  protected function isConfigured(): bool {
    // These fields should be filled up and has no default value, without it
    // it's not possible to communicate with Newsroom.
    return !empty($this->privateKey) && !empty($this->universe) && !empty($this->appId);
  }

  /**
   * {@inheritDoc}
   */
  public function subscriptionServiceConfigured(bool $throw_error = TRUE): bool {
    if (!$throw_error) {
      return $this->isConfigured();
    }

    if (!$this->isConfigured()) {
      throw new InvalidApiConfiguration($this->t('The subscription service is not configured at the moment. Please try again later.')->render());
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
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], string $language = NULL, array $topicExtId = []): ?array {
    $this->subscriptionServiceConfigured();

    // Prepare the post.
    $payload = [
      'key' => $this->generateKey($email),
      'subscription' => [
        'universeAcronym' => $this->universe,
        'topicExtWebsite' => $this->appId,
        'sv_id' => implode(',', $svIds),
        'email' => $this->normalized ? mb_strtolower($email) : $email,
        'language' => $language,
      ],
    ];

    if (!empty($relatedSvIds)) {
      $payload['subscription']['relatedSv_Id'] = implode(',', $relatedSvIds);
    }
    if (!empty($topicExtId)) {
      $payload['subscription']['topicExtId'] = implode(',', $topicExtId);
    }

    try {
      // Send the request.
      $request = $this->httpClient->request('POST', 'https://ec.europa.eu/newsroom/api/v1/subscribe', ['json' => $payload]);
    }
    catch (ClientException $e) {
      throw new BadResponseException($this->t('Invalid response returned by Newsroom API.')->render(), $e->getRequest(), $e->getResponse());
    }
    if ($request->getStatusCode() === 200) {
      $data = Json::decode($request->getBody()->getContents());

      if (empty($data)) {
        throw new BadResponseException($this->t('Empty response returned by Newsroom newsletter API.')->render(), NULL);
      }

      $response = NULL;
      // This is necessary to split separately newsletters distribution lists.
      $sv_ids_separated = explode(',', implode(',', $svIds));
      // @todo Support multiple distribution list in a better way.
      foreach ($data as $subscription_item) {
        // This will fetch only the first item found.
        if (in_array($subscription_item['newsletterId'], $sv_ids_separated, FALSE)) {
          $response = $subscription_item;
          break;
        }
      }
      if (isset($response)) {
        return $response;
      }

      throw new BadResponseException($this->t('Newsroom API returned a response with HTTP status %status but subscription item not found in it.', ['%status' => $request->getStatusCode()])->render(), NULL);
    }

    throw new BadResponseException($this->t('The subscription service is not available at the moment. Please try again later.')->render(), NULL);
  }

  /**
   * {@inheritDoc}
   */
  public function unsubscribe(string $email, array $svIds = []): bool {
    $this->subscriptionServiceConfigured();

    // This is necessary to split separately newsletters distribion lists.
    $sv_ids_separated = explode(',', implode(',', $svIds));

    // The API does not support multiple unsubscription, so we need to call it
    // one by one.
    foreach ($sv_ids_separated as $sv_id) {
      $options = [
        'query' => [
          'user_email' => $this->normalized ? mb_strtolower($email) : $email,
          'key' => $this->generateKey($email),
          'app' => $this->appId,
          'sv_id' => $sv_id,
        ],
      ];

      try {
        // Send the request.
        $response = $this->httpClient->get('https://ec.europa.eu/newsroom/api/v1/unsubscribe', $options);
      }
      catch (ClientException $e) {
        throw new BadResponseException(
          $this->t('Invalid response returned by Newsroom API.')
            ->render(), $e->getRequest(), $e->getResponse()
        );
      }

      // If the unsubscription was success the API returns HTTP code 200.
      // And a text message in the HTTP message body that we don't need now.
      if ($response->getStatusCode() !== 200) {
        return FALSE;
      }
    }

    // If all were succeeded, we return true.
    return TRUE;
  }

}
