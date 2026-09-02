<?php

namespace Drupal\oe_newsroom\Attribute;

/**
 * Marks a class as explorable with the API explorer submodule.
 *
 * The attribute only has an effect, if:
 * - The 'newsroom_api_explorer' submodule is not installed, AND
 * - The service already has the 'newsroom_api_explorer' tag, usually via
 *   *.services.yml.
 *
 * This attribute does not extend symfony's AutoconfigureTag, because that would
 * require the additional 'symfony/config' package.
 * Instead, the tag needs to be added via *.services.yml, then this attribute
 * is used to narrow the selection.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class NewsroomApiExplorer {

}
