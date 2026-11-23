<?php

namespace HHK\Config;

use Dotenv\Dotenv;
use Dotenv\Exception\InvalidPathException;
use Dotenv\Repository\Adapter\EnvConstAdapter;
use Dotenv\Repository\RepositoryBuilder;

/**
 * Env.php
 *
 * Loads site configuration (database credentials, pepper, etc.) from the
 * .env file in the project root (the directory above public/).
 *
 * Values are written to $_ENV only - not $_SERVER or getenv() - so secrets
 * don't show up in request dumps such as the debug bar or error reports.
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

        $repository = RepositoryBuilder::createWithNoAdapters()
            ->addAdapter(EnvConstAdapter::class)
            ->immutable()
            ->make();

        try {
            Dotenv::create($repository, $rootDir)->load();
        } catch (InvalidPathException $e) {
            http_response_code(500);
            exit("Site configuration file (.env) is missing.  Copy .env.example to .env in the site root and fill it in.");
        }

        self::$loaded = true;
    }

    /**
     * Get a configuration value, or $default when it is missing.
     */
    public static function get(string $key, string $default = ''): string
    {
        return isset($_ENV[$key]) ? (string) $_ENV[$key] : $default;
    }
}
