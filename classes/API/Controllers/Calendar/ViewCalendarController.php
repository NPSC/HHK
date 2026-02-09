<?php
namespace HHK\API\Controllers\Calendar;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DI\Container;
use HHK\sec\SysConfig;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for accessing calendar events
 */
class ViewCalendarController
{

    private Container $container;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
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
                
        $query = "select * from vapi_register_resv where ReservationStatusId in ('" . ReservationStatus::Committed . "','" . ReservationStatus::UnCommitted . "','" . ReservationStatus::Waitlist . "') "
            . " and ExpectedArrival < DATE_ADD('" . $endDate->format('Y-m-d') . "', INTERVAL 1 DAY) and ExpectedDeparture >= DATE_ADD('" . $startDate->format('Y-m-d') . "', INTERVAL 1 DAY) order by ExpectedArrival asc, ReservationId asc";

        $stmt = $dbh->query($query);
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

        $query = "select * from vapi_register vr  where vr.VisitStatusId not in ('" . VisitStatus::Pending . "' , '" . VisitStatus::Cancelled . "') and
            vr.SpanStart < DATE_ADD('" . $endDate->format('Y-m-d') . "', INTERVAL 1 DAY) and ifnull(vr.SpanEnd, case when now() > vr.ExpectedDeparture then now() else vr.ExpectedDeparture end) >= '" .$startDate->format('Y-m-d') . "';";
        $stmtv = $dbh->query($query);
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
