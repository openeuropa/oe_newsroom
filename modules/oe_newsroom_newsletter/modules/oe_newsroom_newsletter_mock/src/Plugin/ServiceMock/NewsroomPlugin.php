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
  public const STATE_KEY_SUBSCRIPTIONS = 'oe_newsroom.mock_api_subscriptions';

  /**
   * The key of the state entry that contains the mocked api universe.
   */
  public const STATE_KEY_UNIVERSE = 'oe_newsroom.mock_api_universe';

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
   *   Returns TRUE if it is a new subscription, FALSE otherwise.
   *
   * @return array
   *   Generated subscription array, similar to newsrooms one.
   */
  protected function generateSubscriptionArray(string $universe, string $email, string $sv_id, string $language_code, bool $isNewSubscription): array {
    // These are here google translations, but it will do the job to simulate
    // the original behaviour.
    $new_subscription = [
      'bg' => 'Благодарим ви, че сте се регистрирали за услугата: Услуга за бюлетини за тестове',
      'cs' => 'Děkujeme, že jste se zaregistrovali do služby: Testovací služba zpravodaje',
      'da' => 'Tak fordi du tilmeldte dig tjenesten: Test nyhedsbrevsservice',
      'de' => 'Vielen Dank für Ihre Anmeldung zum Service: Test Newsletter Service',
      'et' => 'Täname, et registreerusite teenusesse: testige uudiskirja teenust',
      'el' => 'Ευχαριστούμε για την εγγραφή σας στην υπηρεσία: Δοκιμή υπηρεσίας ενημερωτικών δελτίων',
      'en' => 'Thanks for Signing Up to the service: Test Newsletter Service',
      'es' => 'Gracias por suscribirse al servicio: Test Newsletter Service',
      'fr' => 'Merci de vous être inscrit au service : Testez le service de newsletter',
      'ga' => 'Go raibh maith agat as Síniú leis an tseirbhís: Seirbhís Nuachtlitir Tástála',
      'hr' => 'Hvala vam što ste se prijavili za uslugu: Test Newsletter Service',
      'it' => 'Grazie per esserti iscritto al servizio: Test Newsletter Service',
      'lv' => 'Paldies, ka reģistrējāties pakalpojumam: Pārbaudiet biļetenu pakalpojumu',
      'lt' => 'Dėkojame, kad prisiregistravote prie paslaugos: išbandykite naujienlaiškio paslaugą',
      'hu' => 'Köszönjük, hogy feliratkozott a szolgáltatásra: Teszt hírlevél szolgáltatás',
      'mt' => 'Grazzi talli rreġistrajt għas-servizz: Test Newsletter Service',
      'nl' => 'Bedankt voor het aanmelden voor de service: Test nieuwsbriefservice',
      'pl' => 'Dziękujemy za zapisanie się do usługi: Testowa usługa Newsletter',
      'pt' => 'Obrigado por se inscrever no serviço: Serviço de boletim informativo de teste',
      'ro' => 'Vă mulțumim că v-ați înscris la serviciu: serviciul Newsletter Test',
      'sk' => 'Ďakujeme, že ste sa zaregistrovali do služby: Služba testovania spravodajcov',
      'sl' => 'Hvala za prijavo na storitev: Test Newsletter Service',
      'fi' => 'Kiitos rekisteröitymisestä palveluun: Testaa uutiskirjepalvelu',
      'sv' => 'Tack för att du anmäler dig till tjänsten: Testa nyhetsbrevstjänsten',
    ];

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
      'feedbackMessage' => $new_subscription[$language_code] ?? $new_subscription['en'],
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
    $data = Json::decode((string) $request->getBody());
    $universe = $data['subscription']['universeAcronym'];
    $email = $data['subscription']['email'];
    $sv_ids = explode(',', $data['subscription']['sv_id']);

    $subscriptions = $this->state->get(self::STATE_KEY_SUBSCRIPTIONS, []);
    $current_subs = [];
    // If sv_id is present, then it used as a filter.
    if (count($sv_ids) > 0) {
      foreach ($sv_ids as $sv_id) {
        if (isset($subscriptions[$universe][$sv_id][$email]) && $subscriptions[$universe][$sv_id][$email]['subscribed']) {
          $current_subs[] = $this->generateSubscriptionArray($universe, $email, $sv_id, $subscriptions[$universe][$sv_id][$email]['language'], FALSE);
        }
      }
    }
    // If not present, then display all results.
    else {
      foreach ($subscriptions[$universe] as $sv_id => $subscription) {
        if (isset($subscription[$email]) && $subscription[$email]['subscribed']) {
          $current_subs[] = $this->generateSubscriptionArray($universe, $email, $sv_id, $subscription[$email]['language'], FALSE);
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
    $data = Json::decode((string) $request->getBody());
    $universe = $data['subscription']['universeAcronym'];
    $app_id = $data['subscription']['topicExtWebsite'];
    $email = $data['subscription']['email'];
    $sv_ids = explode(',', $data['subscription']['sv_id']);
    $related_sv_ids = isset($data['subscription']['relatedSv_Id']) ? explode(',', $data['subscription']['relatedSv_Id']) : [];
    $language = $data['subscription']['language'] ?? 'en';
    $topic_ext_id = isset($data['subscription']['topicExtId']) ? explode(',', $data['subscription']['topicExtId']) : [];

    $subscriptions = $this->state->get(self::STATE_KEY_SUBSCRIPTIONS, []);
    $universes = $this->state->get(self::STATE_KEY_UNIVERSE, []);
    $current_subs = [];
    foreach (array_merge($sv_ids, $related_sv_ids) as $sv_id) {
      // Select the first to returned as the normal API class, but the
      // webservice marks all as subscribed, so let's mark it here too.
      $new_subscription = empty($subscriptions[$universe][$sv_id][$email]['subscribed']);
      $current_subs[] = $this->generateSubscriptionArray($universe, $email, $sv_id, $language, $new_subscription);

      $universes[$app_id] = $universe;

      $subscriptions[$universe][$sv_id][$email] = [
        'subscribed' => TRUE,
        'language' => $language,
        'topicExtId' => $topic_ext_id,
      ];
    }
    $this->state->set(self::STATE_KEY_SUBSCRIPTIONS, $subscriptions);
    $this->state->set(self::STATE_KEY_UNIVERSE, $universes);

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

    $email = $parameters['user_email'];
    // The API does not support multiple sv_ids in a single call.
    $sv_id = $parameters['sv_id'];

    $subscriptions = $this->state->get(self::STATE_KEY_SUBSCRIPTIONS, []);
    $universes = $this->state->get(self::STATE_KEY_UNIVERSE, []);
    $universe = $universes[$parameters['app']];

    // When you try to unsubscribe a user that newsroom does not have at all,
    // you will get an internal error which will converted by our API to this.
    if (!isset($subscriptions[$universe][$sv_id][$email])) {
      return new Response(404, [], 'Not found');
    }

    // When the user e-mail exists in the e-mail it will return the same message
    // regardless if it's subscribed or not previously.
    $subscriptions[$universe][$sv_id][$email] = [
      'subscribed' => FALSE,
      'language' => NULL,
      'topicExtId' => NULL,
    ];

    $this->state->set(self::STATE_KEY_SUBSCRIPTIONS, $subscriptions);

    return new Response(200, [], 'User unsubscribed!');
  }

}
