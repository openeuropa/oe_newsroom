<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Api;

use Drupal\Component\Serialization\Json;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Site\Settings;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\oe_newsroom\Newsroom;
use Drupal\oe_newsroom_newsletter\Exception\ClientException;
use Drupal\oe_newsroom_newsletter\Exception\InvalidResponseException;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Client to access the Newsroom newsletter subscription API.
 *
 * @internal This class is marked as final and internal as it will be later
 *   moved to a dedicated library. Please note that this class may change at any
 *   time and this will break any dependencies on it.
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
final class NewsroomClient implements NewsroomClientInterface, ContainerInjectionInterface {

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
   * Api waits for normalised data in hash or not.
   *
   * @var bool
   */
  protected $normalised;

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
   * The service ID used for node notifications.
   *
   * @var string
   */
  protected $svId;

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
  public function __construct(ConfigFactoryInterface $configFactory, Settings $settings, ClientInterface $httpClient) {
    $config = $configFactory->get(Newsroom::CONFIG_NAME);

    $this->privateKey = $settings->get('oe_newsroom')['newsroom_api_key'] ?? NULL;
    $this->hashMethod = $config->get('hash_method');
    $this->normalised = $config->get('normalised');
    $this->universe = $config->get('universe');
    $this->appId = $config->get('app_id');
    $this->svId = $config->get('sv_id');
    $this->httpClient = $httpClient;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): NewsroomClient {
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
  public function isConfigured(): bool {
    // These fields should be filled up and have no default value. Without them,
    // it's not possible to communicate with Newsroom.
    return !empty($this->privateKey) && !empty($this->universe) && !empty($this->appId);
  }

  /**
   * Generates a multiple parameters key.
   *
   * @param array $params
   *   The parameters to be used for the key.
   *
   * @return string
   *   Generated communication key.
   */
  protected function generateComposedKey(array $params): string {
    return hash($this->hashMethod, implode($params) . $this->privateKey);
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
    // @todo handle type parameters for key the validation.
    if ($this->normalised) {
      return hash($this->hashMethod, mb_strtolower($email) . $this->privateKey);
    }

    return hash($this->hashMethod, $email . $this->privateKey);
  }

  /**
   * {@inheritdoc}
   *
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function subscribe(string $email, array $svIds = [], array $relatedSvIds = [], ?string $language = NULL, array $topicExtId = []): array {
    $payload = [
      'key' => $this->generateKey($email),
      'subscription' => array_diff_assoc([
        'universeAcronym' => $this->universe,
        'topicExtWebsite' => $this->appId,
        'sv_id' => implode(',', $svIds),
        'email' => $this->normalised ? mb_strtolower($email) : $email,
        'language' => $language ?? '',
      ], [
        'sv_id' => '',
        'language' => '',
      ]),
    ];

    if (!empty($relatedSvIds)) {
      $payload['subscription']['relatedSv_Id'] = implode(',', $relatedSvIds);
    }
    if (!empty($topicExtId)) {
      $payload['subscription']['topicExtId'] = implode(',', $topicExtId);
    }

    // Send the request.
    try {
      $request = $this->httpClient->request('POST', self::API_URL . '/subscribe', ['json' => $payload]);
    }
    catch (GuzzleException $exception) {
      throw new ClientException('An error has occurred during a subscribe request.', 0, $exception);
    }

    // @todo The HTTP client should already throw exceptions for any response
    //   code other than 200.
    if ($request->getStatusCode() !== 200) {
      throw new InvalidResponseException('Newsroom API returned a response with HTTP status ' . $request->getStatusCode() . ' instead of expected 200.');
    }

    $data = Json::decode((string) $request->getBody());
    if (empty($data)) {
      throw new InvalidResponseException('Empty response returned by Newsroom newsletter API.');
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

    throw new InvalidResponseException('Newsroom API returned a 200 response but subscription items were found in it.');
  }

  /**
   * {@inheritdoc}
   */
  public function unsubscribe(string $email, array $svIds = []): bool {
    // @todo This method should unsubscribe from one sv ID only.
    // This is necessary to split separately newsletters distribution lists.
    $sv_ids_separated = explode(',', implode(',', $svIds));

    // The API does not support multiple unsubscription, so we need to call it
    // one by one.
    foreach ($sv_ids_separated as $sv_id) {
      $payload = [
        'query' => [
          'user_email' => $this->normalised ? mb_strtolower($email) : $email,
          'key' => $this->generateKey($email),
          'app' => $this->appId,
          'sv_id' => $sv_id,
        ],
      ];

      // Send the request.
      try {
        $response = $this->httpClient->get(self::API_URL . '/unsubscribe', $payload);
      }
      catch (GuzzleException $exception) {
        throw new ClientException('An error has occurred during an unsubscribe request.', 0, $exception);
      }

      // If the unsubscription was success the API returns HTTP code 200.
      // And a text message in the HTTP message body that we don't need now.
      // @todo Do not bail out at first failure, but instead run all the
      //   unsubscriptions and show that some of them where unsuccessful.
      // @todo This is leaking if a user is subscribed to a newsletter.
      //   MUST be removed.
      if ($response->getStatusCode() !== 200) {
        return FALSE;
      }
    }

    // If all were succeeded, we return true.
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function subscriptions(string $email) {
    // @todo For the moment keep this clean only with the needed parameters to
    //   get the subscriptions, the endpoint allows more like subscribing to
    //   newsletters.
    $query = [
      'key' => $this->generateKey($email),
      'app' => $this->appId,
      'user_email' => $email,
    ];

    try {
      $result = $this->httpClient->request('GET', self::API_URL . '/subscriptions', ['query' => $query]);
    }
    catch (GuzzleException $exception) {
      throw new ClientException('An error has occurred during the subscriptions request.', 0, $exception);
    }

    // @todo Handle different cases.
    $data = Json::decode((string) $result->getBody());

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function nodeNotificationCreate(
    string $section_id,
    string $notification_title,
    string $notification_description,
    string $notification_url,
    string $node_id,
    string $node_title,
    // Is this required and what is the expected format?
    string $create_date = '',
  ): void {
    $payload = [
      'key' => $this->generateComposedKey([
        $this->svId,
        $section_id,
        $notification_title,
        $notification_description,
        $notification_url,
        $node_id,
      ]),
      'app' => $this->appId,
      'item' => [
        'sv_id' => $this->svId,
        'section_id' => $section_id,
        'notification_title' => $notification_title,
        'notification_description' => $notification_description,
        'notification_URL' => $notification_url,
        'node_id' => $node_id,
        'node_title' => $node_title,
        // Is this really mandatory?
        'createDate' => $create_date,
      ],
    ];

    try {
      $this->httpClient->request('POST', self::API_URL . '/node-notification/create', ['json' => $payload]);
    }
    catch (GuzzleException $exception) {
      throw new ClientException('An error has occurred during the node notification create request.', previous: $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function nodeNotificationDelete(string $node_id): void {
    $payload = [
      'key' => $this->generateComposedKey([
        $this->svId,
        $node_id,
      ]),
      'app' => $this->appId,
      'item' => [
        'sv_id' => $this->svId,
        'node_id' => $node_id,
      ],
    ];

    try {
      $this->httpClient->request('POST', self::API_URL . '/node-notification/delete', ['json' => $payload]);
    }
    catch (GuzzleException $exception) {
      throw new ClientException('An error has occurred during the node notification delete request.', 0, $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function nodeNotificationSubscribe(
    string $node_id,
    string $email,
    // Add constants for the frecuency?
    int $frequency,
    bool $nomail = FALSE,
  ):array {
    $payload = [
      'key' => $this->generateKey(
        $this->normalised ? mb_strtolower($email) : $email,
      ),
      'app' => $this->appId,
      'subscription' => [
        'sv_id' => $this->svId,
        'email' => $email,
        'frequency' => $frequency,
        'node_id' => $node_id,
        'nomail' => $nomail,
      ],
    ];

    try {
      $result = $this->httpClient->request('POST', self::API_URL . '/subscribe', ['json' => $payload]);
    }
    catch (GuzzleException $exception) {
      throw new ClientException('An error has occurred during the node subscribe request.', 0, $exception);
    }

    // @todo Handle different cases.
    $data = Json::decode((string) $result->getBody());

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function nodeNotificationUnsubscribe(
    string $node_id,
    string $email,
    bool $request_authentication = FALSE,
    ?string $redirect_to = '',
  ): void {
    $payload = [
      'key' => $this->generateKey(
        $this->normalised ? mb_strtolower($email) : $email,
      ),
      'app' => $this->appId,
      'subscription' => [
        'sv_id' => $this->svId,
        'node_id' => $node_id,
        'email' => $email,
      ],
    ];

    if ($request_authentication) {
      if ($redirect_to === NULL || empty($redirect_to)) {
        throw new ClientException('Missing required parameter.');
      }
      $payload['subscription']['request_authentication'] = TRUE;
      $payload['subscription']['redirect_to'] = $redirect_to;
    }

    try {
      $this->httpClient->request('POST', self::API_URL . '/unsubscribe/node-notification', ['json' => $payload]);
    }
    catch (GuzzleException $exception) {
      throw new ClientException('An error has occurred during the node unsubscribe request.', 0, $exception);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function nodeNotificationGet(string $node_id):array {
    $query = [
      'key' => $this->generateComposedKey([
        $this->svId,
        $node_id,
      ]),
      'app' => $this->appId,
      'sv_id' => $this->svId,
      'node_id' => $node_id,
    ];

    try {
      $result = $this->httpClient->request('GET', self::API_URL . '/node-notification/get', ['query' => $query]);
    }
    catch (GuzzleException $exception) {
      throw new ClientException('An error has occurred duting the get notifications request.', 0, $exception);
    }

    // @todo Handle different cases.
    $data = Json::decode((string) $result->getBody());

    return $data;
  }

}
