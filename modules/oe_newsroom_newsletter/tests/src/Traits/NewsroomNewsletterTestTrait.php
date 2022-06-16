<?php

declare(strict_types = 1);

namespace Drupal\Tests\oe_newsroom_newsletter\Traits;

use Drupal\oe_newsroom_newsletter\NewsroomNewsletter;

/**
 * Shared methods prepared to use in any test, if needed.
 */
trait NewsroomNewsletterTestTrait {

  /**
   * Block default settings.
   *
   * @param bool $multiple_distributions
   *   TRUE if the block will have multiple distribution lists, FALSE otherwise.
   *
   * @return array
   *   Array of the block default settings.
   */
  protected static function blockCommonSettings(bool $multiple_distributions): array {
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
    $defaults = [
      'label' => 'Subscribe to newsletter',
      'id' => 'subscribe',
      'intro_text' => 'This is the introduction text.',
      'successful_subscription_message' => '',
    ];
    $defaults += static::blockCommonSettings($multiple_distributions);
    $settings += $defaults;
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
    $defaults = [
      'label' => 'Unsubscribe from newsletter',
      'id' => 'unsubscribe',
    ];
    $defaults += static::blockCommonSettings($multiple_distributions);
    $settings += $defaults;
    $this->drupalPlaceBlock('oe_newsroom_newsletter_unsubscription_block', $settings);
  }

  /**
   * Sets default values for the oe_newsroom_newsletter configuration.
   *
   * @param string $privacy_url
   *   The value of the privacy url. If left empty, a default is provided.
   */
  protected function configureNewsletter(string $privacy_url = '/privacy-url'): void {
    \Drupal::configFactory()
      ->getEditable(NewsroomNewsletter::CONFIG_NAME)
      ->set('privacy_uri', $privacy_url)
      ->save();
  }

}
