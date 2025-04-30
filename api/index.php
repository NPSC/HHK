<?php

declare(strict_types=1);

use HHK\House\Report\DailyOccupancyReport;
use HHK\House\Report\RoomReport;
use HHK\sec\OAuth\Entity\AccessTokenEntity;
use HHK\sec\OAuth\Repository\AccessTokenRepository;
use HHK\sec\SysConfig;
use HHK\sec\OAuth\Middleware\ResourceServerMiddleware;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use HHK\sec\OAuth\OAuthServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use HHK\sec\Session;
use HHK\sec\Login;
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
    $dbh = initPDO(TRUE);
    $oAuthServer = new OAuthServer($dbh);

    //create Slim App instance
    $app = AppFactory::create();
    $app->setBasePath('/api'); // Set the base path for the API

    //add middleware
    $app->addRoutingMiddleware();
    $app->addErrorMiddleware(false, false, false);


    // set up token endpoint
    $app->post('/oauth2/token', function (Request $request, Response $response) use ($oAuthServer) {
        try{
            return $oAuthServer->getAuthServer()->respondToAccessTokenRequest($request, $response);
        }catch (OAuthServerException $e) {
            return $e->generateHttpResponse($response);
        }
    });


    // actual protected API routes
    $app->group('/v1', function (RouteCollectorProxy $group) use ($dbh) {

        // Define your API routes here
        $group->get('/hello', function (Request $request, Response $response) {
            $data = ["status"=>"success", "message" => "Hello, World!"];
            $clientId = $request->getAttribute('oauth_client_id');
            $userId = $request->getAttribute('oauth_user_id');
            $data["user"] = ["clientId" => $clientId, "userId" => $userId];
            $response->getBody()->write(json_encode($data));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->get('/reports/occupancy/today', function (Request $request, Response $response) use ($dbh) {
            $dailyOccupancy = new DailyOccupancyReport($dbh);
            $rawData = $dailyOccupancy->getMainSummaryData();

            $returnData = [];
            $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($dbh, "sys_config", "siteName"));
            $returnData["date"] = (new \DateTime())->format("Y-m-d");
            $returnData["occupancy"] = $rawData[0];
            $returnData["generated"] = (new \DateTime())->format("Y-m-d H:i:s");

            $response->getBody()->write(json_encode($returnData));
            return $response->withHeader('Content-Type', 'application/json');
        });

        $group->get('/reports/occupancy/alltime', function (Request $request, Response $response) use ($dbh) {

            $uS = Session::getInstance();
            $stats = [];
            $stats["nightsOfRest"] = RoomReport::getGlobalNightsCounter($dbh);
            $stats["nightsOfRest"] = $uS->gnc;
            $stats["totalStays"] = RoomReport::getGlobalStaysCounter($dbh);
            $stats["totalStays"] = $uS->gsc;
            $stats["totalOccupancyPercentage"] = RoomReport::getGlobalRoomOccupancy($dbh);
            $stats["totalOccupancyPercentage"] = $uS->goc;

            $returnData = [];
            $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($dbh, "sys_config", "siteName"));
            $returnData["generated"] = (new \DateTime())->format("Y-m-d H:i:s");
            $returnData["occupancy"] = $stats;

            $response->getBody()->write(json_encode($returnData));
            return $response->withHeader('Content-Type', 'application/json');
        });

    })->add(new ResourceServerMiddleware($oAuthServer->getResourceServer(), $app->getResponseFactory()->createResponse()));

    $app->run();

