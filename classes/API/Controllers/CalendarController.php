<?php
namespace HHK\API\Controllers;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DI\Container;
use HHK\sec\SysConfig;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controller for accessing calendar events
 */
class CalendarController
{

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function index(Request $request, Response $response, array $args): Response
    {
        $startDate = filter_input(INPUT_GET, 'startDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $endDate = filter_input(INPUT_GET, 'endDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if($startDate && $endDate){
            $startDate = DateTime::createFromFormat("Y-m-d", $startDate);
            $endDate = DateTime::createFromFormat("Y-m-d", $endDate);
        }else{
            $startDate = new DateTimeImmutable("today");
            $endDate = $startDate->add(new DateInterval("P1W"));
        }

        if(!$startDate instanceof DateTimeInterface && !$endDate instanceof DateTimeInterface){
            $response->getBody()->write(json_encode(["error"=>"Bad Request", "error_description"=>"Invalid date: Dates must be formatted yyyy-mm-dd"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if ($startDate > $endDate) {
            $response->getBody()->write(json_encode(["error"=>"Bad Request", "error_description"=>"Invalid date range"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        $dbh = $this->container->get("dbh");
        $returnData = [];
        $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($dbh, "sys_config", "siteName"));
        $returnData["generatedAt"] = (new DateTime())->format(DateTime::RFC3339);
        $returnData["startDate"] = $startDate->format("Y-m-d");
        $returnData["endDate"] = $endDate->format("Y-m-d");
                
        $query = "SELECT * FROM `vapi_register_resv` WHERE `ReservationStatusId` IN (:stCommitted, :stUncommitted, :stWaitlist) "
            . " AND DATE(`ExpectedArrival`) <= DATE(:endDate1) AND DATE(`ExpectedDeparture`) > DATE(:startDate1) ORDER BY `ExpectedArrival` ASC, `ReservationId` ASC";

        $stmt = $dbh->prepare($query);
        $stmt->execute([
            ':stCommitted' => ReservationStatus::Committed,
            ':stUncommitted' => ReservationStatus::UnCommitted,
            ':stWaitlist' => ReservationStatus::Waitlist,
            ':endDate1' => $endDate->format('Y-m-d'),
            ':startDate1' => $startDate->format('Y-m-d'),
        ]);
        $resvRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($resvRows as &$row) {
            $row["PrimaryGuest"] = [
                "id"=>$row["PrimaryGuestId"],
                "firstName"=>$row["PrimaryGuestFirst"],
                "lastName"=>$row["PrimaryGuestLast"],
                "fullName"=>$row["PrimaryGuestFullName"],
                "email"=>$row["PrimaryGuestEmail"]
            ];
            $row["ExpectedArrival"] = (new DateTime($row["ExpectedArrival"]))->format(DateTime::RFC3339);
            $row["ExpectedDeparture"] = (new DateTime($row["ExpectedDeparture"]))->format(DateTime::RFC3339);
            unset($row["PrimaryGuestId"], $row["PrimaryGuestFirst"], $row["PrimaryGuestLast"], $row["PrimaryGuestFullName"], $row["PrimaryGuestEmail"]);
        }

        $returnData["reservations"] = $resvRows;

        $query = "SELECT * FROM `vapi_register` `vr`  WHERE `vr`.`VisitStatusId` NOT IN (:vsPending, :vsCancelled) AND
            DATE(`vr`.`SpanStart`) <= DATE(:endDate2) AND IFNULL(DATE(`vr`.`SpanEnd`), CASE WHEN DATE(NOW()) > DATE(`vr`.`ExpectedDeparture`) THEN DATE(NOW()) ELSE DATE(`vr`.`ExpectedDeparture`) END) >= DATE(:startDate2);";
        $stmtv = $dbh->prepare($query);
        $stmtv->execute([
            ':vsPending' => VisitStatus::Pending,
            ':vsCancelled' => VisitStatus::Cancelled,
            ':endDate2' => $endDate->format('Y-m-d'),
            ':startDate2' => $startDate->format('Y-m-d'),
        ]);
        $visitRows = $stmtv->fetchAll(\PDO::FETCH_ASSOC);
                
        foreach ($visitRows as &$row) {
            $row["PrimaryGuest"] = [
                "id"=>$row["PrimaryGuestId"],
                "firstName"=>$row["PrimaryGuestFirst"],
                "lastName"=>$row["PrimaryGuestLast"],
                "fullName"=>$row["PrimaryGuestFullName"],
                "email"=>$row["PrimaryGuestEmail"]
            ];
            $row["SpanStart"] = $row["SpanStart"] ? (new DateTime($row["SpanStart"]))->format(DateTime::RFC3339):null;
            $row["SpanEnd"] = $row["SpanEnd"] ? (new DateTime($row["SpanEnd"]))->format(DateTime::RFC3339):null;
            $row["ExpectedDeparture"] = $row["ExpectedDeparture"] ? (new DateTime($row["ExpectedDeparture"]))->format(DateTime::RFC3339):null;
            unset($row["PrimaryGuestId"], $row["PrimaryGuestFirst"], $row["PrimaryGuestLast"], $row["PrimaryGuestFullName"], $row["PrimaryGuestEmail"]);
        }

        $returnData["visits"] = $visitRows;

        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}