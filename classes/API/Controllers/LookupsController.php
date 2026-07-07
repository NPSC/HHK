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
 * Controller for accessing lookup data from gen_lookups table
 */
class LookupsController
{

    private Container $container;
    private PDO $dbh;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->dbh = $this->container->get("dbh");
    }

    /**
     * Get list of lookup table_names
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return Response
     */
    public function index(Request $request, Response $response, array $args): Response
    {

        $returnData = [];

        $query = "select Table_Name from gen_lookups group by Table_Name order by Table_Name";
        $stmt = $this->dbh->query($query);
        $returnData = $stmt->fetchAll(PDO::FETCH_COLUMN);

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
        // Implementation for showing a specific gen lookup
        $returnData = [];

        $args = filter_var_array($args, [
            'table_name' => FILTER_SANITIZE_FULL_SPECIAL_CHARS
        ]);

        $query = "select `Code` as `code`, `Description` as `name` from `gen_lookups` where `Table_Name` = :table_name order by `Order` asc, `Description` asc";
        $stmt = $this->dbh->prepare($query);
        $stmt->bindValue(':table_name', $args['table_name']);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $returnData = ["lookup" => $args['table_name'], "items" => $stmt->fetchAll(PDO::FETCH_ASSOC)];
        }else{
            $response->getBody()->write(json_encode(["error"=>"Not Found", "error_description"=>"Lookup not found"]));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write(json_encode($returnData));
        return $response->withHeader('Content-Type', 'application/json');
    }
}