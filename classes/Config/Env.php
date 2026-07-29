<?php

namespace HHK\Config;

use Dotenv\Dotenv;

/**
 * Env.php
 *
 * Loads site configuration from a .env file at the given project root.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Env {

    private static bool $loaded = false;

    public static function load(string $rootDir): void
    {
        if (self::$loaded) {
            return;
        }

        Dotenv::createImmutable($rootDir)->load();
        self::$loaded = true;
    }
}
