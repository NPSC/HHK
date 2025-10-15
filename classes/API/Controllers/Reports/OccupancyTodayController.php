<?php
namespace HHK\API\Controllers\Reports;

use DI\Container;
use HHK\House\Report\DailyOccupancyReport;
use HHK\sec\SysConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for accessing Occupancy Today report
 */
class OccupancyTodayController
{

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function occupancyToday(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $dbh = $this->container->get("dbh");

        $dailyOccupancy = new DailyOccupancyReport($dbh);
        $rawData = $dailyOccupancy->getMainSummaryData();

        $returnData = [];
        $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($dbh, "sys_config", "siteName"));
        $returnData["date"] = (new \DateTime())->format("Y-m-d");
        $returnData["occupancy"] = $rawData[0];
        $returnData["generated"] = (new \DateTime())->format(\DateTime::RFC3339);


        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}