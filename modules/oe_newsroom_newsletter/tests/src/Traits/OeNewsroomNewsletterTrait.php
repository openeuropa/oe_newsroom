<?php

declare(strict_types = 1);

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
  protected function unsetApiPrivateKey(): void {
    $settings['settings']['oe_newsroom']['newsroom_api_key'] = (object) [
      'value' => '',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Set the API private key.
   */
  protected function setApiPrivateKey(): void {
    $settings['settings']['oe_newsroom']['newsroom_api_key'] = (object) [
      'value' => 'phpunit-test-private-key',
      'required' => TRUE,
    ];
    $this->writeSettings($settings);
  }

  /**
   * Block default settings.
   *
   * @param bool $multiple_distributions
   *   TRUE if the block will have multiple distribution lists, FALSE otherwise.
   *
   * @return array
   *   Array of the block default settings.
   */
  protected static function blockDefaultSettings(bool $multiple_distributions): array {
    $distribution_lists = [
      ['sv_id' => '111', 'name' => 'Newsletter 1'],
    ];
    if ($multiple_distributions) {
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
   * @param bool $multiple_distributions
   *   TRUE if the block will have multiple distribution lists, FALSE otherwise.
   */
  protected function placeNewsletterSubscriptionBlock(array $settings = [], bool $multiple_distributions = FALSE): void {
    $settings_default = [
      'label' => 'Subscribe to newsletter',
      'id' => 'subscribe',
      'intro_text' => 'This is the introduction text.',
      'successful_subscription_message' => '',
    ];
    $settings_default += static::blockDefaultSettings($multiple_distributions);
    $settings += array_merge($settings_default, $settings);
    $this->drupalPlaceBlock('oe_newsroom_newsletter_subscription_block', $settings);
  }

  /**
   * Place unsubscribe block.
   *
   * @param array $settings
   *   Array of the block settings.
   * @param bool $multiple_distributions
   *   TRUE if the block will have multiple distribution lists, FALSE otherwise.
   */
  protected function placeNewsletterUnsubscriptionBlock(array $settings = [], bool $multiple_distributions = FALSE): void {
    $settings_default = [
      'label' => 'Unsubscribe from newsletter',
      'id' => 'unsubscribe',
    ];
    $settings_default += static::blockDefaultSettings($multiple_distributions);
    $settings += array_merge($settings_default, $settings);
    $this->drupalPlaceBlock('oe_newsroom_newsletter_unsubscription_block', $settings);
  }

  /**
   * Sets default values for the oe_newsroom module configuration.
   */
  protected function configureNewsroom(): void {
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroom::CONFIG_NAME)
      ->set('universe', 'example-universe')
      ->set('app_id', 'example-app');
    $config->save();
  }

  /**
   * Sets default values for the oe_newsroom_newsletter configuration.
   */
  protected function configureNewsletter(): void {
    $config = \Drupal::configFactory()
      ->getEditable(OeNewsroomNewsletter::CONFIG_NAME)
      ->set('privacy_uri', '/privacy-url');
    $config->save();
  }

}
