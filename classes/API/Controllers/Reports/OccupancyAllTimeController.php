<?php
namespace HHK\API\Controllers\Reports;

use DI\Container;
use HHK\House\Report\RoomReport;
use HHK\sec\SysConfig;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for accessing Occupancy All Time report
 */
class OccupancyAllTimeController
{

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args = []): ResponseInterface
    {
        $dbh = $this->container->get("dbh");

        $previousNights = SysConfig::getKeyValue($dbh, "sys_config", "PreviousNights");
        $roomOccCat = SysConfig::getKeyValue($dbh, "sys_config", "RoomOccCat");

        $stats = [];
        $stats["totalNightsOfRest"] = intval(RoomReport::getGlobalNightsCount($dbh) + $previousNights);
        $stats["totalStays"] = RoomReport::getGlobalStaysCount($dbh);

        $returnData = [];
        $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($dbh, "sys_config", "siteName"));
        $returnData["generated"] = (new \DateTime())->format(\DateTime::RFC3339);
        $returnData["occupancy"] = $stats;

        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }

}