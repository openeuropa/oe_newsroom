Back to module development [README.md](README.md) file.

# Intro
This module is used to connect to Newsroom services via its API. Currently,
the module provides a base module called `oe_newsroom` and specific modules for
Newsroom services, currently only for newsletter as `oe_newsroom_newsletter`.

The base module can be used as a base of your own custom implementation, it has
its own generic settings implementation.

The submodule called `oe_newsroom_newsletter` provides an implementation with
further settings and to allow users (using blocks) to subscribe and unsubscribe
from the Newsroom newsletters.

# Provides

1. Base module (`oe_newsroom`) - only API main configurations from UI and settings.php for a private key.
Supports multilingualism, newsletter and notifications. (subscribe, unsubscribe,
get subscriptions)
2. Submodule (`oe_newsroom_newsletter`) - provides for subscription and
unsubscription, supports multilingualism and also supports multiple distribution
lists, in a limited way.

# Requirements
When you would like to connect to Newsroom, you need to have the following
configuration:
- universe,
- application (name),
- hash method,
- normalisation,
- private key.

To subscribe/unsubscribe it needs to have indicated the distribution list IDs (`sv_id`).

All of these are provided by Newsroom team.

# Setup
## Base module
The following is configurable from the UI: universe, application (name), hash
method, normalisation; from this location: `/admin/config/system/newsroom-settings`

To set the private key (in the settings.php) you can use the following:
- Add to `runner.yml` file:
  - `$settings['oe_newsroom']['newsroom_api_key'] = getenv('NEWSROOM_API_PRIVATE_KEY');`
- Set the environment variable `NEWSROOM_API_PRIVATE_KEY` in the `docker-compose.override.yml`.

**NEVER commit the private key into GIT!**

Create a DevOps ticket to set the environment variable `NEWSROOM_API_PRIVATE_KEY` for acceptance and production environments.

## Newsroom newsletter module
This requires the distribution list in the block setting page (only newsletter
supported) and further settings can be set up on the
`/admin/config/system/newsroom-settings/newsletter` page, for proper setup
please follow the field descriptions.
