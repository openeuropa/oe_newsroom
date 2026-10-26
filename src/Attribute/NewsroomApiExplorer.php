<?php

namespace Drupal\oe_newsroom\Attribute;

/**
 * Marks a class as explorable with the API explorer submodule.
 *
 * The attribute only has an effect, if all of the following are true:
 * - The 'newsroom_api_explorer' submodule is installed.
 * - The class with this attribute is registered as a service, with the class
 *   name as its service id and as the 'class' property.
 * - The service has the 'newsroom_api_explorer' service tag.
 *
 * This attribute does not extend symfony's AutoconfigureTag, because that would
 * require the additional 'symfony/config' package.
 * Instead, the tag can be added using the '_defaults' section in the
 * '*.services.yml' file, and then this attribute can be used to narrow the
 * selection.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class NewsroomApiExplorer {

}
