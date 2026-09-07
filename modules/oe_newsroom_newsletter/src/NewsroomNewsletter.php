<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter;

use Drupal\Component\Utility\Html;

/**
 * Class for constants and general functions for the Newsroom newsletter module.
 */
final class NewsroomNewsletter {

  public const CONFIG_NAME = 'oe_newsroom_newsletter.settings';

  /**
   * Returns the class marking the block title belonging to a form.
   *
   * @param string $html_id
   *   The HTML ID of the rendered form.
   *
   * @return string
   *   The class name.
   */
  public static function getBlockTitleClass(string $html_id): string {
    return Html::getClass('oe-newsroom-newsletter-title-' . $html_id);
  }

}
