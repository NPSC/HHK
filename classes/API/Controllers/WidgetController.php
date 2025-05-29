<?php
namespace HHK\API\Controllers;

use HHK\House\Report\RoomReport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for API Widgets
 */
class WidgetController
{

    private $dbh;

    public function __construct(\PDO $dbh)
    {
        $this->dbh = $dbh;
    }

    public function vacancy(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
        $vacancies = RoomReport::getTonightVacancies($this->dbh);
    
        $data = ["status"=>"success", "hasVacancy" => $vacancies > 0, "vacancyCount" => $vacancies];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

}