Back to module development [README.md](README.md) file.

# Intro
This module is used to connect to Newsrooms' services via their API. Currently,
this module provides a base module called `oe_newsroom` and specific modules for
Newsroom's services, currently only for newsletter as `oe_newsroom_newsletter`.

The base module can be used as a base of your own custom implementation, it has
its own generic settings implementation, ~~and also it provides fully configurable
API class~~ (postponed).

The submodule called `oe_newsroom_newsletter` provides an implementation with
further settings and to allow users to subscribe and unsubscribe from the
newsroom newsletter. It allows with ~~field plugin~~, block plugin ~~and predefined
URLs~~ to subscribe and unsubscribe users.

# Provides

1. Base module (`oe_newsroom`)
Only ~~an~~ API ~~and its~~ main configurations from UI and settings.php for private key.
Supports multilingualism, newsletter and notifications. (subscribe, unsubscribe,
get subscriptions)
2. Submodule `oe_newsroom_newsletter`
Provides for subscription and unsubscription and supports multilingualism also
supports multiple distribution list, in a limited way. Integration ways:
- ~~full page (`/newsletter/subscribe` and `/newsletter/unsubscribe`)~~
- block (two separate block for subscribe and unsubscribe)
- ~~field (newsroom field type with subscribe and unsubscribe separated field formatters)~~

# Requirements
When you would like to connect to Newsroom, you need to have the following:

For connection: universe, application (name), private key, hash method, normalization.

Then you will need for subscription the distribution list IDs (`sv_id`).

All of these are provided by Newsroom.

# Setup
## Base module
The following is configurable from the UI: universe, application (name), hash
method, normalization; from this location: `/admin/config/system/newsroom-settings`

The private key is a server settings, which needs to be configured via `.env` file (and settings.php).
In your demo `.env` file create a new variable called `NEWSROOM_API_PRIVATE_KEY=`
in your local `.env` file create the same and place the key after it.
In your demo runner file add `$settings['newsroom_api_private_key'] = getenv('NEWSROOM_API_PRIVATE_KEY');`
into the settings file, i.e. with `additional_settings` option. Create a devops
ticket for acceptance and production. Do not commit into git the private key!

## Newsroom Newsletter module
This requires the distribution list in the block setting page (only newsletter
supported) and further settings can be set up on the
`/admin/config/system/newsroom-settings/newsletter` page, for proper setup
please follow the field descriptions.
