<?php

declare(strict_types=1);

use DI\Container;
use HHK\API\Controllers\Calendar\ViewCalendarController;
use HHK\API\Controllers\Oauth\RequestTokenController;
use HHK\API\Controllers\Reports\OccupancyAllTimeController;
use HHK\API\Controllers\Reports\OccupancyTodayController;
use HHK\API\Controllers\Widgets\VacancyWidgetController;
use HHK\API\Handlers\ErrorHandler;
use HHK\Common;
use HHK\sec\Session;
use HHK\sec\Login;
use HHK\API\OAuth\OAuthServer;
use HHK\API\Middleware\{AccessTokenHasScopeMiddleware, AllowedOriginMiddleware, LogMiddleware, ResourceServerMiddleware, CorsMiddleware};
use HHK\sec\SysConfig;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;

/**
 * index.php
 *
 * Web Service for API access to HHK
 *
 * @author    Will Ireland <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2025 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

require ("../house/homeIncludes.php");

$login = new Login();
$login->initHhkSession(CONF_PATH, ciCFG_FILE);
$uS = Session::getInstance();
$dbh = Common::initPDO(TRUE);
$oAuthServer = new OAuthServer($dbh);
$debugMode = ($uS->mode == "dev");
    
//create Slim App instance
$container = new Container();
$container->set("dbh", $dbh);
$container->set("oAuthServer", $oAuthServer);
AppFactory::setContainer($container);
$app = AppFactory::create();
$app->setBasePath('/api'); // Set the base path for the API

//add middleware
$app->addRoutingMiddleware();
$app->addErrorMiddleware($debugMode, true, true)->setDefaultErrorHandler(new ErrorHandler($app->getContainer(), $app->getCallableResolver(), $app->getResponseFactory()));
$app->add(new CorsMiddleware($app));


//is API enabled in site config?
if(SysConfig::getKeyValue($dbh, "sys_config", "useAPI", false)){

    // set up token endpoint
    // Endpoint: /api/oauth2/token
    $app->post('/oauth2/token', RequestTokenController::class)->add(new LogMiddleware($dbh));

    // actual protected API routes
    $app->group('/v1', function (RouteCollectorProxy $group) use ($dbh, $oAuthServer) {

        //public widgets protected by CORS
        $group->group('/widget', function (RouteCollectorProxy $group) use ($dbh) {
                
            //vacancy widget 
            // Endpoint: /api/v1/widget/vacancy
            $group->get('/vacancy', VacancyWidgetController::class);

        })->add(new AllowedOriginMiddleware($dbh))->add(new LogMiddleware($dbh));

        //OAuth protected routes
        $group->group("", function (RouteCollectorProxy $group) use ($dbh) {

            $group->group('/reports', function (RouteCollectorProxy $group) use ($dbh) {

                // Occupancy Today
                // Endpoint: /api/v1/reports/occupancy/today
                $group->get('/occupancy/today', OccupancyTodayController::class);

                // All time occupancy
                // Endpoint: /api/v1/reports/occupancy/alltime
                $group->get('/occupancy/alltime', OccupancyAllTimeController::class);

            })->add(new AccessTokenHasScopeMiddleware("aggregatereports:read"));

            // Calendar
            // Endpoint: /api/v1/calendar
            $group->get('/calendar', ViewCalendarController::class)->add(new AccessTokenHasScopeMiddleware("calendar:read"));

        })->add(new LogMiddleware($dbh))->add(new ResourceServerMiddleware($oAuthServer->getResourceServer()));
    });
}

$app->run();