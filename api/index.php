<?php

declare(strict_types=1);

use HHK\sec\Session;
use HHK\sec\Login;
use HHK\API\OAuth\OAuthServer;
use HHK\API\Controllers\{CalendarController, ReportController, WidgetController};
use HHK\API\Middleware\{AccessTokenHasScopeMiddleware, AllowedOriginMiddleware, LogMiddleware, ResourceServerMiddleware};
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Routing\RouteCollectorProxy;
use League\OAuth2\Server\Exception\OAuthServerException;

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
$dbh = initPDO(TRUE);
$oAuthServer = new OAuthServer($dbh);
    
//create Slim App instance
$app = AppFactory::create();
$app->setBasePath('/api'); // Set the base path for the API

//add middleware
$app->addRoutingMiddleware();
$app->addErrorMiddleware(true, false, false);

// set up token endpoint
// Endpoint: /api/oauth2/token
$app->post('/oauth2/token', function (Request $request, Response $response) use ($oAuthServer) {
    $response->withHeader("Access-Control-Allow-Headers", "Content-Type, Authorization, Accept");
    try{
        return $oAuthServer->getAuthServer()->respondToAccessTokenRequest($request, $response);
    }catch (OAuthServerException $e) {
        return $e->generateHttpResponse($response);
    }
})->add(new LogMiddleware($dbh));


// actual protected API routes
$app->group('/v1', function (RouteCollectorProxy $group) use ($dbh, $oAuthServer) {

    //public widgets protected by CORS
    $group->group('/widget', function (RouteCollectorProxy $group) use ($dbh) {
            
        //vacancy widget 
        // Endpoint: /api/v1/widget/vacancy
        $group->get('/vacancy', function (Request $request, Response $response, array $args) use ($dbh) {
            return (new WidgetController($dbh))->vacancy($request, $response, $args);
        });

    })->add(new LogMiddleware($dbh))->add(new AllowedOriginMiddleware($dbh));

    //OAuth protected routes
    $group->group("", function (RouteCollectorProxy $group) use ($dbh) {

        $group->group('/reports', function (RouteCollectorProxy $group) use ($dbh) {

            // Occupancy Today
            // Endpoint: /api/v1/reports/occupancy/today
            $group->get('/occupancy/today', function (Request $request, Response $response, array $args) use ($dbh) {
                return (new ReportController($dbh))->occupancyToday($request, $response, $args);
            });

            // All time occupancy
            // Endpoint: /api/v1/reports/occupancy/alltime
            $group->get('/occupancy/alltime', function (Request $request, Response $response, array $args) use ($dbh) {
                return (new ReportController($dbh))->occupancyAllTime($request, $response, $args);
            });
        })->add(new AccessTokenHasScopeMiddleware("aggregatereports:read"));

        // Calendar
        // Endpoint: /api/v1/calendar
        $group->get('/calendar', function(Request $request, Response $response, array $args) use ($dbh){
            return (new CalendarController($dbh))->index($request, $response, $args);
        })->add(new AccessTokenHasScopeMiddleware("calendar:read"));

    })->add(new LogMiddleware($dbh))->add(new ResourceServerMiddleware($oAuthServer->getResourceServer()));
});

$app->run();