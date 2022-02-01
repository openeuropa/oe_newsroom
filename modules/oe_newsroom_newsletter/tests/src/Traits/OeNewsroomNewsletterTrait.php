<?php

namespace Drupal\Tests\oe_newsroom_newsletter\Traits;

use Drupal\oe_newsroom\OeNewsroom;
use Drupal\oe_newsroom_newsletter\OeNewsroomNewsletter;

/**
 * Shared methods prepared to use in any test, if needed.
 */
trait OeNewsroomNewsletterTrait {

  /**
   * Unset the API private key.
   */
  public function unsetApiPrivateKey(): void {
    $settings['settings']['oe_newsroom']['newsroom_api_key'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Set the API private key.
   */
  public function setApiPrivateKey(): void {
    $settings['settings']['oe_newsroom']['newsroom_api_key'] = (object) [
      'value' => 'phpunit-test-private-key',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Block default settings.
   *
   * @param bool $multi_distro
   *   TRUE if the block will have multiple distribution lists, FALSE otherwise.
   *
   * @return array
   *   Array of the block default settings.
   */
  protected static function blockDefaultSettings(bool $multi_distro): array {
    $distribution_lists = [
      ['sv_id' => '111', 'name' => 'Newsletter 1'],
    ];
    if ($multi_distro) {
      $distribution_lists[] = [
        'sv_id' => '222,333',
        'name' => 'Newsletter collection',
      ];
    }

    return [
      'distribution_lists' => $distribution_lists,
      'region' => 'content',
    ];
  }

  /**
   * Place subscribe block.
   *
   * @param array $settings
   *   Array of the block settings.
   * @param bool $multi_distro
   *   TRUE if the block will have multiple distribution lists, FALSE otherwise.
   */
  public function placeNewsletterSubscriptionBlock(array $settings = [], bool $multi_distro = FALSE): void {
    $settings_default = [
      'label' => 'Subscribe to newsletter',
      'id' => 'subscribe',
      'intro_text' => 'This is the introduction text.',
      'successful_subscription_message' => '',
    ];
    $settings_default += static::blockDefaultSettings($multi_distro);
    $settings += array_merge($settings_default, $settings);
    $this->drupalPlaceBlock('oe_newsroom_newsletter_subscription_block', $settings);
  }

  /**
   * Place unsubscribe block.
   *
   * @param array $settings
   *   Array of the block settings.
   * @param bool $multi_distro
   *   TRUE if the block will have multiple distribution lists, FALSE otherwise.
   */
  public function placeNewsletterUnsubscriptionBlock(array $settings = [], bool $multi_distro = FALSE): void {
    $settings_default = [
      'label' => 'Unsubscribe from newsletter',
      'id' => 'unsubscribe',
    ];
    $settings_default += static::blockDefaultSettings($multi_distro);
    $settings += array_merge($settings_default, $settings);
    $this->drupalPlaceBlock('oe_newsroom_newsletter_unsubscription_block', $settings);
  }

  /**
   * Configure the Newsroom settings.
   */
  public function configureNewsroom(): void {
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroom::CONFIG_NAME)
      ->set('universe', 'example-universe')
      ->set('app_id', 'example-app');
    $config->save();
  }

  /**
   * Configure the newsletter settings.
   */
  public function configureNewsletter(): void {
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroomNewsletter::CONFIG_NAME)
      ->set('privacy_uri', '/privacy-url');
    $config->save();
  }

}
