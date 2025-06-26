<?php
namespace HHK\API\Controllers\Widgets;

use DI\Container;
use HHK\House\Report\RoomReport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Controller for API Widgets
 */
class VacancyWidgetController
{

    private Container $container;

   public function __construct(Container $container)
   {
       $this->container = $container;
   }

    public function __invoke(ServerRequestInterface $request, ResponseInterface $response, array $args): ResponseInterface{
        $dbh = $this->container->get("dbh");
        $vacancies = RoomReport::getTonightVacancies($dbh);
    
        $data = ["status"=>"success", "hasVacancy" => $vacancies > 0, "vacancyCount" => $vacancies];
        $response->getBody()->write(json_encode($data));
        return $response->withHeader('Content-Type', 'application/json');
    }

}