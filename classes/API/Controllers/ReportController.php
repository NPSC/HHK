<?php
namespace HHK\API\Controllers;

use HHK\House\Report\DailyOccupancyReport;
use HHK\House\Report\RoomReport;
use HHK\sec\Session;
use HHK\sec\SysConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for accessing reports
 */
class ReportController
{

    private $dbh;

    public function __construct(\PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    public function occupancyToday(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $dailyOccupancy = new DailyOccupancyReport($this->dbh);
        $rawData = $dailyOccupancy->getMainSummaryData();

        $returnData = [];
        $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($this->dbh, "sys_config", "siteName"));
        $returnData["date"] = (new \DateTime())->format("Y-m-d");
        $returnData["occupancy"] = $rawData[0];
        $returnData["generated"] = (new \DateTime())->format("Y-m-d H:i:s");

        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    public function occupancyAllTime(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $uS = Session::getInstance();
        $stats = [];
        $stats["nightsOfRest"] = RoomReport::getGlobalNightsCounter($this->dbh);
        $stats["nightsOfRest"] = $uS->gnc;
        $stats["totalStays"] = RoomReport::getGlobalStaysCounter($this->dbh);
        $stats["totalOccupancyPercentage"] = RoomReport::getGlobalRoomOccupancy($this->dbh);

        $returnData = [];
        $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($this->dbh, "sys_config", "siteName"));
        $returnData["generated"] = (new \DateTime())->format("Y-m-d H:i:s");
        $returnData["occupancy"] = $stats;

        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}