<?php
namespace HHK\API\Controllers;

use HHK\sec\SysConfig;
use HHK\SysConst\ReservationStatus;
use HHK\SysConst\VisitStatus;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for accessing calendar events
 */
class CalendarController
{

    private $dbh;

   public function __construct(\PDO $dbh)
   {
       $this->dbh = $dbh;
   }
    public function index(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface
    {
        $startDate = filter_input(INPUT_GET, 'startDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $endDate = filter_input(INPUT_GET, 'endDate', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        try{
            $startDate = new \DateTime($startDate);
            $endDate = new \DateTime($endDate);
        }catch(\Exception $e){
            $response->getBody()->write(json_encode(["error"=>"Bad Request", "error_description"=>"Invalid date: " . $e->getMessage()]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

        if ($startDate > $endDate) {
            $response->getBody()->write(json_encode(["error"=>"Bad Request", "error_description"=>"Invalid date range"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
        }

                $returnData = [];
                $returnData["houseName"] = html_entity_decode(SysConfig::getKeyValue($this->dbh, "sys_config", "siteName"));
                $returnData["generatedAt"] = (new \DateTime())->format("Y-m-d H:i:s");
                $returnData["startDate"] = $startDate->format("Y-m-d");
                $returnData["endDate"] = $endDate->format("Y-m-d");
                
                $query = "select * from vapi_register_resv where ReservationStatusId in ('" . ReservationStatus::Committed . "','" . ReservationStatus::UnCommitted . "','" . ReservationStatus::Waitlist . "') "
                . " and DATE(ExpectedArrival) <= DATE('" . $endDate->format('Y-m-d') . "') and DATE(ExpectedDeparture) > DATE('" . $startDate->format('Y-m-d') . "') order by ExpectedArrival asc, ReservationId asc";

                $stmt = $this->dbh->query($query);
                $resvRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($resvRows as &$row) {
                    $row["PrimaryGuest"] = [
                        "id"=>$row["PrimaryGuestId"],
                        "firstName"=>$row["PrimaryGuestFirst"],
                        "lastName"=>$row["PrimaryGuestLast"],
                        "fullName"=>$row["PrimaryGuestFullName"],
                        "email"=>$row["PrimaryGuestEmail"]
                    ];
                    unset($row["PrimaryGuestId"], $row["PrimaryGuestFirst"], $row["PrimaryGuestLast"], $row["PrimaryGuestFullName"], $row["PrimaryGuestEmail"]);
                }

                $returnData["reservations"] = $resvRows;

                $query = "select * from vapi_register vr  where vr.VisitStatusId not in ('" . VisitStatus::Pending . "' , '" . VisitStatus::Cancelled . "') and
            DATE(vr.SpanStart) <= DATE('" . $endDate->format('Y-m-d') . "') and ifnull(DATE(vr.SpanEnd), case when DATE(now()) > DATE(vr.ExpectedDeparture) then DATE(now()) else DATE(vr.ExpectedDeparture) end) >= DATE('" .$startDate->format('Y-m-d') . "');";
                $stmtv = $this->dbh->query($query);
                $visitRows = $stmtv->fetchAll(\PDO::FETCH_ASSOC);
                foreach ($visitRows as &$row) {
                    $row["PrimaryGuest"] = [
                        "id"=>$row["PrimaryGuestId"],
                        "firstName"=>$row["PrimaryGuestFirst"],
                        "lastName"=>$row["PrimaryGuestLast"],
                        "fullName"=>$row["PrimaryGuestFullName"],
                        "email"=>$row["PrimaryGuestEmail"]
                    ];
                    unset($row["PrimaryGuestId"], $row["PrimaryGuestFirst"], $row["PrimaryGuestLast"], $row["PrimaryGuestFullName"], $row["PrimaryGuestEmail"]);
                }

                $returnData["visits"] = $visitRows;

                $response->getBody()->write(json_encode($returnData));
                return $response->withHeader('Content-Type', 'application/json');
    }
}