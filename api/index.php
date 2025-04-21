<?php

use HHK\House\Report\DailyOccupancyReport;
use HHK\House\Report\RoomReport;
use HHK\sec\SysConfig;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use HHK\sec\OAuth\OAuthServer;
use League\OAuth2\Server\Exception\OAuthServerException;
use HHK\sec\Session;
use HHK\sec\Login;


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
    

    //create Slim App instance
    $app = AppFactory::create();
    $app->setBasePath('/api'); // Set the base path for the API

    $app->addRoutingMiddleware();
    $errorMiddleware = $app->addErrorMiddleware(false, false, false);

    $app->post('/oauth2/token', function (Request $request, Response $response) use ($app, $dbh) {

        try {
            $server = new OAuthServer($dbh);

            // Try to respond to the request
            return $server->requestAccessToken($dbh, $request, $response);

            
        } catch (OAuthServerException $exception) {
        
            // All instances of OAuthServerException can be formatted into a HTTP response
            return $exception->generateHttpResponse($response);
        }
    });


    $app->get('/reports/occupancy/today', function (Request $request, Response $response) use ($dbh) {
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

    $app->get('/reports/occupancy/alltime', function (Request $request, Response $response) use ($dbh) {

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


    $app->run();

