<?php

/**
 * @file
 * Loads the .env file.
 *
 * This file is included very early. See autoload.files in composer.json.
 */

use Dotenv\Dotenv;

// Load any .env, .env.dist or .env.example in the current or parent directory.
// If shortCircuit parameter is TRUE, than only the first found file will be
// loaded from the list from top down to the bottom direction.
// Unsafe means that we can use the getenv and putenv functions, but these are
// not thread safe, that's why it's unsafe:
// @see: https://github.com/vlucas/phpdotenv#putenv-and-getenv
// @see: https://bugs.php.net/bug.php?id=71607
// @see: https://github.com/laravel/framework/issues/7354
$envs = [
  '.env',
  '../.env',
  '.env.dist',
  '../.env.dist',
  '.env.example',
  '../.env.example',
];

$dotenv = Dotenv::createUnsafeImmutable(__DIR__, $envs, TRUE);

// Because production environments rarely use .env files make sure there's no
// exception.
$dotenv->safeLoad();
