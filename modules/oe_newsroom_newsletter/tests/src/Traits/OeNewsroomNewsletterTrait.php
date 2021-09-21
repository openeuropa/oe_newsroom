<?php

namespace Drupal\Tests\oe_newsroom_newsletter\Traits;

use Drupal\oe_newsroom\Api\MockNewsroomMessenger;
use Drupal\oe_newsroom\OeNewsroom;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;

/**
 * Shared methods prepared to use in any test, if needed.
 */
trait OeNewsroomNewsletterTrait {

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
   * Enable the mock.
   */
  public function enableMock() {
    $config = \Drupal::configFactory()
      ->getEditable('oe_newsroom.messenger')
      ->set('class', MockNewsroomMessenger::class);
    $config->save();
  }

  /**
   * Configure the Newsroom settings.
   */
  public function configureNewsroom() {
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroom::CONFIG_NAME)
      ->set('universe', 'example-universe')
      ->set('app', 'example-app');
    $config->save();
  }

  /**
   * Configure the Newsletter settings.
   */
  public function configureNewsletter() {
    $distro_list[] = [
      'sv_id' => '123',
      'name' => 'distro1',
    ];
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroomNewsletter::CONFIG_NAME)
      ->set('distribution_list', $distro_list)
      ->set('intro_text', 'This is the introduction text.')
      ->set('privacy_uri', '/');
    $config->save();
  }

  /**
   * Configure the Newsletters settings to have multiple newsletters.
   */
  public function configureMultipleNewsletters() {
    $distro_list = [
      ['sv_id' => '123,321', 'name' => 'Newsletter collection 1'],
      ['sv_id' => '234', 'name' => 'Newsletter 2'],
    ];
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroomNewsletter::CONFIG_NAME)
      ->set('distribution_list', $distro_list)
      ->set('intro_text', 'This is the introduction text.')
      ->set('privacy_uri', '/');
    $config->save();
  }

}
