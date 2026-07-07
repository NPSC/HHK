<?php
namespace HHK\API\Controllers;


use DI\Container;
use HHK\Common;
use HHK\House\Room\Room;
use HHK\sec\Session;
use PDO;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

/**
 * Controller for accessing room data
 */
class RoomsController
{

    private Container $container;
    private PDO $dbh;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->dbh = $this->container->get("dbh");
    }

    /**
     * Set standard format for room data
     * @param array $row
     * @return array
     */
    private function formatRoom(array $row): array
    {
        return [
            'id'           => (int) $row['idRoom'],
            'name'        => $row['roomName'],
            'group'        => $row['Group_Title'],
            'cleaning'     => [
                'lastCleaned'    => $row['Last_Cleaned'],
                'lastDeepClean' => $row['Last_Deep_Clean'],
                'status'       => [
                    'code' => $row['Status'],
                    'name' => $row['Status_Text'],
                ],    
            ],
            'currentVisit'    => $row['idVisit'] > 0 ? [
                'visitId'              => $row['idVisit'] . '-'.$row['span'],
                'numGuests'            => $row['numGuests'] !== '' ? (int) $row['numGuests'] : 0,
                'arrival'               => $row['Arrival'],
                'expectedDeparture'    => $row['Expected_Departure'],
                'primaryGuest' => [
                    'id' => $row['idGuest'],
                    'firstName' => $row['First_Name'],
                    'lastName' => $row['Last_Name'],
                    'fullName' => $row['Full_Name'],
                    'email' => $row['Email'],
                ]
            ]:null,
            'nextExpectedArrival' => $row['Next_Expected_Arrival'],
            'latestRoomNote' => $row['Notes'] !=='' ? [
                'flagged' => $row['noteFlagged'] == 1 ? true : false,
                'note' => $row['Notes'],
                'timestamp' => $row['noteDate'],
            ]:null,
        ];
    }

    private function sanitizeRoomInput(array $data): array
    {
        $sanitized = [];

        if (isset($data['name'])) {
            $sanitized['name'] = filter_var($data['name'], FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        }

        if (isset($data['cleaning']) && \is_array($data['cleaning'])) {
            $sanitized['cleaning'] = filter_var_array($data['cleaning'], [
                'cycle_code'    => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'lastCleaned'   => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'lastDeepClean' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            ]);

            if (isset($data['cleaning']['status']) && \is_array($data['cleaning']['status'])) {
                $sanitized['cleaning']['status'] = filter_var_array($data['cleaning']['status'], [
                    'code' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                ]);
            }
        }

        if (isset($data['latestNote']) && \is_array($data['latestNote'])) {
            $sanitized['latestNote'] = filter_var_array($data['latestNote'], [
                'date' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
                'text' => FILTER_SANITIZE_FULL_SPECIAL_CHARS,
            ]);
        }

        return $sanitized;
    }

    /**
     * Get list of rooms
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function index(Request $request, Response $response, array $args): Response
    {

        $returnData = [];

        $query = "select * from vapi_rooms";

        $stmt = $this->dbh->query($query);
        $roomRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($roomRows as $row) {
            $returnData["rooms"][] = $this->formatRoom($row);
        }

        if (!isset($returnData["rooms"])) {
            $returnData["rooms"] = [];
        }

        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Show a specific room
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        // Implementation for showing a specific room
        $returnData = [];

        $args = filter_var_array($args, [
            'id' => FILTER_SANITIZE_FULL_SPECIAL_CHARS
        ]);

        $query = "select * from vapi_rooms where idRoom = :id";
        $stmt = $this->dbh->prepare($query);
        $stmt->bindValue(':id', $args['id']);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $returnData = $this->formatRoom($stmt->fetch(PDO::FETCH_ASSOC));
        }else{
            $response->getBody()->write(json_encode(["error"=>"Not Found", "error_description"=>"Room not found"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Update a specific room
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function update(Request $request, Response $response, array $args): Response
    {
        // Implementation for updating a specific room
        $returnData = [];
        $statusCode = '';

        $args = filter_var_array($args, [
            'id' => FILTER_SANITIZE_FULL_SPECIAL_CHARS
        ]);

        $query = "select * from vapi_rooms where idRoom = :id";
        $stmt = $this->dbh->prepare($query);
        $stmt->bindValue(':id', $args['id']);
        $stmt->execute();
        
        if($stmt->rowCount() == 0) {
            $response->getBody()->write(json_encode(["error"=>"Not Found", "error_description"=>"Room not found"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $room = new Room($this->dbh, $args['id']);

        $requestData = $this->sanitizeRoomInput(
            json_decode($request->getBody()->getContents(), true) ?? []
        );

        if(isset($requestData['cleaning']['status']['code'])){
            $statusCode = $requestData['cleaning']['status']['code'];

            $roomStatuses = Common::readGenLookupsPDO($this->dbh, "Room_Status");
            if(isset($roomStatuses[$statusCode]) === FALSE){
                $response->getBody()->write(json_encode(["error"=>"Invalid Status Code", "error_description"=>"The provided housekeeping status code is not valid"]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
            }

            if ($room->setCleanStatus($statusCode) === FALSE) {
                $response->getBody()->write(json_encode(["error"=>"Housekeeping Status Update Failed", "error_description"=>"Failed to update housekeeping status"]));
                return $response->withHeader('Content-Type', 'application/json')->withStatus(500);
            }

            $uS = Session::getInstance();
            $room->saveRoom($this->dbh, $uS->username, true);

        }

        $query = "select * from vapi_rooms where idRoom = :id";
        $stmt = $this->dbh->prepare($query);
        $stmt->bindValue(':id', $args['id']);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $returnData = $this->formatRoom($stmt->fetch(PDO::FETCH_ASSOC));
        }else{
            $response->getBody()->write(json_encode(["error"=>"Not Found", "error_description"=>"Room not found"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}