<?php

namespace HHK\Debug;

use DebugBar\OpenHandler;
use DebugBar\JavascriptRenderer;
use DebugBar\Storage\FileStorage;
use DebugBar\StandardDebugBar;
use HHK\sec\Session;
use HHK\SysConst\Mode;

/**
 * Optional PHP Debugbar integration for browser HTML pages.
 */
final class DebugBarSupport {

    private const STORAGE_PATH = '/tmp/hhk-debugbar';

    private static ?StandardDebugBar $debugBar = null;
    private static ?JavascriptRenderer $renderer = null;
    private static bool $initialized = false;

    public static function bootstrap(): void {

        if (self::$initialized === true || self::shouldEnable() === false) {
            return;
        }

        if (class_exists(StandardDebugBar::class) === false || \PHP_SAPI === 'cli') {
            return;
        }

        self::$debugBar = new StandardDebugBar();
        self::$renderer = self::$debugBar->getJavascriptRenderer();
        self::$renderer->setBaseUrl(self::resourceBaseUrl());
        self::configureStorageAndAjax();
        self::$renderer->setAjaxHandlerEnableTab(true);
        self::$renderer->setAjaxHandlerAutoShow(false);

        self::$debugBar['messages']->info('PHP Debugbar enabled');
        self::$initialized = true;

        ob_start([self::class, 'injectIntoHtml']);
        register_shutdown_function([self::class, 'finalize']);
    }

    public static function bar(): ?StandardDebugBar {
        return self::$debugBar;
    }

    public static function addMessage(string $message, string $level = 'info'): void {

        if (self::$debugBar === null || isset(self::$debugBar['messages']) === false) {
            return;
        }

        self::$debugBar['messages']->addMessage($message, $level);
    }

    private static function shouldEnable(): bool {

        if (self::isOpenHandlerRequest()) {
            return false;
        }

        return self::isDebugbarEnabled();
    }

    private static function isDebugbarEnabled(): bool {

        // Keep default off. Enable via query string or environment variable.
        if (isset($_GET['debugbar'])) {
            return self::isTruthy($_GET['debugbar']);
        }

        if (isset($_SERVER['HTTP_X_HHK_DEBUGBAR'])) {
            return self::isTruthy($_SERVER['HTTP_X_HHK_DEBUGBAR']);
        }

        $uS = Session::getInstance();
        if($uS->mode == Mode::Dev){
            return true;
        }

        return self::isTruthy(getenv('HHK_DEBUGBAR'));
    }

    private static function isTruthy($value): bool {

        if ($value === false || $value === null) {
            return false;
        }

        $normalized = strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
    }

    private static function resourceBaseUrl(): string {

        $uS = Session::getInstance();

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/';
        $scriptDir = trim((string) dirname($scriptName), '/');
        $depth = ($scriptDir === '' || $scriptDir === '.') ? 0 : substr_count($scriptDir, '/') + 1;
        $prefix = str_repeat('../', $depth);

        return $uS->resourceURL . '/vendor/php-debugbar/php-debugbar/resources';
    }

    public static function injectIntoHtml(string $buffer): string {

        if (self::$renderer === null || self::isHtmlResponse() === false) {
            return $buffer;
        }

        if (stripos($buffer, '<html') === false) {
            return $buffer;
        }

        $headMarkup = self::$renderer->renderHead();
        $bodyMarkup = self::$renderer->render();

        if (stripos($buffer, '</head>') !== false) {
            $buffer = preg_replace('/<\/head>/i', $headMarkup . "\n</head>", $buffer, 1) ?? $buffer;
        } else {
            $buffer = $headMarkup . "\n" . $buffer;
        }

        if (stripos($buffer, '</body>') !== false) {
            $buffer = preg_replace('/<\/body>/i', $bodyMarkup . "\n</body>", $buffer, 1) ?? $buffer;
        } else {
            $buffer .= "\n" . $bodyMarkup;
        }

        return $buffer;
    }

    public static function handleOpenRequest(): void {

        if (class_exists(StandardDebugBar::class) === false || \PHP_SAPI === 'cli') {
            http_response_code(404);
            return;
        }

        if (self::isDebugbarEnabled() === false) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Unknown command']);
            return;
        }

        if (self::$debugBar === null) {
            self::$debugBar = new StandardDebugBar();
            self::configureStorageAndAjax();
        }

        if (self::$debugBar->getStorage() === null) {
            http_response_code(503);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'DebugBar storage is unavailable']);
            return;
        }

        $openHandler = new OpenHandler(self::$debugBar);
        $openHandler->handle();
    }

    public static function finalize(): void {

        if (self::$debugBar === null || headers_sent() || self::isAjaxRequest() === false || self::isOpenHandlerRequest()) {
            return;
        }

        self::$debugBar->sendDataInHeaders(true);
    }

    private static function isHtmlResponse(): bool {

        $headers = headers_list();

        foreach ($headers as $header) {
            if (stripos($header, 'Content-Type:') !== 0) {
                continue;
            }

            return stripos($header, 'text/html') !== false;
        }

        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return stripos($accept, 'text/html') !== false;
    }

    private static function isAjaxRequest(): bool {

        $requestedWith = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
        if (strcasecmp($requestedWith, 'XMLHttpRequest') === 0) {
            return true;
        }

        $scriptName = basename($_SERVER['SCRIPT_NAME'] ?? '');
        if (str_starts_with($scriptName, 'ws_')) {
            return true;
        }

        $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
        if (str_contains($accept, 'application/json')) {
            return true;
        }

        foreach (headers_list() as $header) {
            if (stripos($header, 'Content-Type:') !== 0) {
                continue;
            }

            $contentType = strtolower($header);
            if (str_contains($contentType, 'application/json') || str_contains($contentType, 'text/javascript')) {
                return true;
            }
        }

        return false;
    }

    private static function configureStorageAndAjax(): void {

        if (self::$debugBar === null) {
            return;
        }

        if (is_dir(self::STORAGE_PATH) === false) {
            @mkdir(self::STORAGE_PATH, 0775, true);
        }

        if (is_dir(self::STORAGE_PATH) && is_writable(self::STORAGE_PATH)) {
            self::$debugBar->setStorage(new FileStorage(self::STORAGE_PATH));
        } else {
            self::$debugBar['messages']->warning('DebugBar storage unavailable; large AJAX payloads may fail');
        }

        if (self::$renderer !== null) {
            self::$renderer->setOpenHandlerUrl(self::openHandlerUrl());
        }
    }

    private static function openHandlerUrl(): string {

        $uS = Session::getInstance();
        return rtrim($uS->resourceURL, '/') . '/admin/ws_session.php?cmd=debugbarOpen';
    }

    private static function isOpenHandlerRequest(): bool {

        $cmd = $_GET['cmd'] ?? $_POST['cmd'] ?? null;
        return is_string($cmd) && $cmd === 'debugbarOpen';
    }
}
