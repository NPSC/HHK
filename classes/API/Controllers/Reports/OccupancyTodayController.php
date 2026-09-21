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

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $dbh = $this->container->get("dbh");

        $dailyOccupancy = new DailyOccupancyReport($dbh);
        $rawData = $dailyOccupancy->getMainSummaryData();

        $returnData = [];
        $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($dbh, "sys_config", "siteName"));
        $returnData["date"] = (new \DateTime())->format("Y-m-d");
        $returnData["occupancy"] = array_map(function ($val) {
            if (is_numeric($val)) {
                return (float) $val;
            } else {
                return $val;
            }
        }, $rawData[0]);
        $returnData["generated"] = (new \DateTime())->format(\DateTime::RFC3339);


        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}