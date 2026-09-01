<?php

declare(strict_types=1);

namespace Drupal\oe_newsroom_newsletter\Ajax;

use Drupal\Core\Ajax\CommandInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsInterface;
use Drupal\Core\Ajax\CommandWithAttachedAssetsTrait;

/**
 * AJAX command to replace the wrapper marked by the newsletter blocks.
 *
 * The target element is resolved client-side: the outermost ancestor of the
 * submitted form carrying the data-oe-newsroom-newsletter-wrapper attribute,
 * falling back to the form itself. The target cannot be expressed as a
 * server-side selector because form and wrapper IDs are regenerated on every
 * request, so only the client knows the IDs present in the page.
 *
 * @see js/newsletter-form.js
 *
 * @internal
 */
class ReplaceFormWrapperCommand implements CommandInterface, CommandWithAttachedAssetsInterface {

  use CommandWithAttachedAssetsTrait;

  /**
   * The attribute marking the wrapper element the command replaces.
   *
   * Mirrored as a string literal in js/newsletter-form.js: update both places
   * when renaming it.
   */
  public const WRAPPER_ATTRIBUTE = 'data-oe-newsroom-newsletter-wrapper';

  /**
   * Constructs a ReplaceFormWrapperCommand object.
   *
   * @param array|string $content
   *   The content that will replace the wrapper, either a render array or an
   *   HTML string.
   */
  public function __construct(protected array|string $content) {
    // Attach the library defining the client-side handler, so pages rendered
    // before it existed still receive it: ajax.js silently ignores unknown
    // commands.
    if (is_array($this->content)) {
      $this->content['#attached']['library'][] = 'oe_newsroom_newsletter/newsletter_form';
    }
  }

  /**
   * {@inheritdoc}
   */
  public function render() {
    return [
      'command' => 'oeNewsroomNewsletterReplaceFormWrapper',
      'data' => $this->getRenderedContent(),
    ];
  }

  /**
   * Marks the wrapper of a form built by one of the newsletter blocks.
   *
   * The marker is set through both #wrapper_attributes (added to the block
   * wrapper since Drupal 11.4) and #attributes (copied to the wrapper by
   * older core). On Drupal >= 11.4 the #attributes copy stays on the form
   * element itself, so the marker can appear on two nested elements: the
   * client-side handler picks the outermost one.
   *
   * @param array $build
   *   The form render array returned by the form builder.
   *
   * @return array
   *   The render array with the marked wrapper.
   */
  public static function markFormWrapper(array $build): array {
    $build['#wrapper_attributes'][self::WRAPPER_ATTRIBUTE] = '';
    $build['#attributes'][self::WRAPPER_ATTRIBUTE] = '';
    return $build;
  }

}
