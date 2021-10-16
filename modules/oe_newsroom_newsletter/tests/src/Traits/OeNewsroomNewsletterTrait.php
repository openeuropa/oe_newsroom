<?php

namespace Drupal\Tests\oe_newsroom_newsletter\Traits;

use Drupal\oe_newsroom\OeNewsroom;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;

/**
 * Shared methods prepared to use in any test, if needed.
 */
trait OeNewsroomNewsletterTrait {

  /**
   * Path to subscribe page.
   *
   * @var string
   */
  protected $subscribePath = '';

  /**
   * Path to unsubscribe page.
   *
   * @var string
   */
  protected $unsubscribePath = '';

  /**
   * Set the API private key.
   */
  public function setApiPrivateKey() {
    $settings['settings']['newsroom_api_private_key'] = (object) [
      'value' => 'phpunit-test-private-key',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Create pages with subscribe and unsubscribe blocks.
   */
  public function createNewsletterPages($multi_distro = FALSE): void {
    $this->drupalCreateContentType(['type' => 'page']);
    if (!$multi_distro) {
      $distribution_list = [
        ['sv_id' => '123', 'name' => 'Newsletter 1'],
      ];
    }
    else {
      $distribution_list = [
        ['sv_id' => '123,321', 'name' => 'Newsletter collection 1'],
        ['sv_id' => '234', 'name' => 'Newsletter 2'],
      ];
    }

    // Create subscribe page.
    $subscribe_page = $this->drupalCreateNode(['type' => 'page']);
    $this->subscribePath = 'node/' . $subscribe_page->id();

    // Place subscribe block.
    $settings = [
      'label' => 'Subscribe to newsletter',
      'id' => 'subscribetonewsletter',
      'region' => 'content',
      'distribution_list' => $distribution_list,
      'visibility' => [
        'request_path' => [
          'pages' => '/node/1',
        ],
      ],
    ];
    $this->drupalPlaceBlock('oe_newsroom_newsletter_subscription_block', $settings);

    // Create unsubscribe page.
    $unsubscribe_page = $this->drupalCreateNode(['type' => 'page']);
    $this->unsubscribePath = 'node/' . $unsubscribe_page->id();

    // Place unsubscribe block.
    $settings = [
      'label' => 'Unsubscribe from newsletter',
      'id' => 'unsubscribefromnewsletter',
      'region' => 'content',
      'distribution_list' => $distribution_list,
      'visibility' => [
        'request_path' => [
          'pages' => '/node/2',
        ],
      ],
    ];
    $this->drupalPlaceBlock('oe_newsroom_newsletter_unsubscription_block', $settings);
  }

  /**
   * Configure the Newsroom settings.
   */
  public function configureNewsroom(): void {
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroom::CONFIG_NAME)
      ->set('universe', 'example-universe')
      ->set('app', 'example-app');
    $config->save();
  }

  /**
   * Configure the Newsletter settings.
   */
  public function configureNewsletter(): void {
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroomNewsletter::CONFIG_NAME)
      ->set('intro_text', 'This is the introduction text.')
      ->set('privacy_uri', '/privacy-url');
    $config->save();
  }

}
