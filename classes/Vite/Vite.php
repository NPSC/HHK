<?php

namespace HHK\Vite;

/**
 * Vite.php
 *
 * Renders <script>/<link> tags for Vite-built entry points. In dev, points
 * pages at the Vite dev server (detected via public/build/hot) for HMR; in
 * prod, resolves hashed filenames from the build manifest.
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2026 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */
class Vite
{
    private static ?array $manifest = null;
    private static ?string $devServerUrl = null;
    private static bool $devChecked = false;

    /**
     * Render tags for one or more entry points, e.g.
     * Vite::asset('resources/js/app.js') using the same path given to
     * build.rollupOptions.input in vite.config.js.
     *
     * @param string|string[] $entries
     */
    public static function asset(string|array $entries): string
    {
        $entries = (array) $entries;
        $devServerUrl = self::devServerUrl();

        if ($devServerUrl !== null) {
            $tags = '<script type="module" src="' . $devServerUrl . '/@vite/client"></script>';

            foreach ($entries as $entry) {
                $tags .= '<script type="module" src="' . $devServerUrl . '/' . ltrim($entry, '/') . '"></script>';
            }

            return $tags;
        }

        $manifest = self::manifest();
        $preloaded = [];
        $tags = '';

        foreach ($entries as $entry) {
            $tags .= self::renderChunk($manifest, $entry, $preloaded);
        }

        return $tags;
    }

    /**
     * Renders a <link> tag for a CSS-only entry point, e.g.
     * Vite::cssLink('resources/css/pdf/receipt.css'), for consumers that
     * fetch stylesheets themselves (mPDF's WriteHTML) rather than running
     * in a browser. Always resolves from the manifest, even under the dev
     * server, since these consumers never load in a browser.
     */
    public static function cssLink(string $entry): string
    {
        return '<link rel="stylesheet" href="' . self::basePath() . '/build/' . self::resolveFile($entry) . '">';
    }

    /**
     * The URL path prefix under which this instance is served, e.g. "/siteA"
     * when reached via a shared vhost that rewrites {domain}/siteA/... to
     * this instance's public/ dir, or "" when the instance is served from
     * its own document root (e.g. a dedicated subdomain). Derived by
     * comparing the request's URL (SCRIPT_NAME) against the executing
     * script's known path relative to this instance's public/ dir, so it
     * needs no per-deployment configuration.
     */
    private static function basePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $scriptFile = str_replace('\\', '/', $_SERVER['SCRIPT_FILENAME'] ?? '');
        $publicRoot = str_replace('\\', '/', rtrim(REL_BASE_DIR, DS) . '/public');

        if ($scriptFile === '' || !str_starts_with($scriptFile, $publicRoot)) {
            return '';
        }

        $relative = ltrim(substr($scriptFile, strlen($publicRoot)), '/');
        $suffix = '/' . $relative;

        if ($relative === '' || !str_ends_with($scriptName, $suffix)) {
            return '';
        }

        return substr($scriptName, 0, -strlen($suffix));
    }

    private static function resolveFile(string $entry): string
    {
        $manifest = self::manifest();

        if (!isset($manifest[$entry])) {
            throw new \RuntimeException("Vite manifest entry not found: {$entry}");
        }

        return $manifest[$entry]['file'];
    }

    private static function devServerUrl(): ?string
    {
        if (self::$devChecked) {
            return self::$devServerUrl;
        }

        self::$devChecked = true;
        $hotFile = self::hotFilePath();

        if (is_file($hotFile)) {
            self::$devServerUrl = rtrim((string) file_get_contents($hotFile));
        }

        return self::$devServerUrl;
    }

    private static function manifest(): array
    {
        if (self::$manifest !== null) {
            return self::$manifest;
        }

        $path = self::manifestPath();

        if (!is_file($path)) {
            throw new \RuntimeException("Vite manifest not found at {$path}. Run `npm run build` first.");
        }

        self::$manifest = json_decode((string) file_get_contents($path), true);

        return self::$manifest;
    }

    private static function renderChunk(array $manifest, string $entry, array &$preloaded): string
    {
        if (!isset($manifest[$entry])) {
            throw new \RuntimeException("Vite manifest entry not found: {$entry}");
        }

        $chunk = $manifest[$entry];
        $tags = '';
        $basePath = self::basePath();

        foreach ($chunk['imports'] ?? [] as $import) {
            $tags .= self::renderPreload($manifest, $import, $preloaded);
        }

        foreach ($chunk['css'] ?? [] as $css) {
            $tags .= '<link rel="stylesheet" href="' . $basePath . '/build/' . $css . '">';
        }

        $tags .= '<script type="module" src="' . $basePath . '/build/' . $chunk['file'] . '"></script>';

        return $tags;
    }

    private static function renderPreload(array $manifest, string $entry, array &$preloaded): string
    {
        if (isset($preloaded[$entry]) || !isset($manifest[$entry])) {
            return '';
        }

        $preloaded[$entry] = true;
        $chunk = $manifest[$entry];
        $tags = '';
        $basePath = self::basePath();

        foreach ($chunk['imports'] ?? [] as $import) {
            $tags .= self::renderPreload($manifest, $import, $preloaded);
        }

        foreach ($chunk['css'] ?? [] as $css) {
            $tags .= '<link rel="stylesheet" href="' . $basePath . '/build/' . $css . '">';
        }

        $tags .= '<link rel="modulepreload" href="' . $basePath . '/build/' . $chunk['file'] . '">';

        return $tags;
    }

    private static function hotFilePath(): string
    {
        return REL_BASE_DIR . 'public' . DS . 'build' . DS . 'hot';
    }

    private static function manifestPath(): string
    {
        return REL_BASE_DIR . 'public' . DS . 'build' . DS . '.vite' . DS . 'manifest.json';
    }
}
