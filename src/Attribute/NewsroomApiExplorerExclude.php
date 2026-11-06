<?php

namespace Drupal\oe_newsroom\Attribute;

/**
 * Marks a method as not explorable with the API explorer submodule.
 *
 * This is only needed if the class itself has the #[NewsroomApiExplorer]
 * attribute, and the associated service has the tag 'oe_newsroom_api_explorer'.
 *
 * @internal
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
class NewsroomApiExplorerExclude {

}
