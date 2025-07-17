<?php
namespace HHK\TableLog;
use HHK\DataTableServer\DataTableServer;
use HHK\DataTableServer\SSP;
use HHK\Tables\ExternalAPILogRS;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ExternalAPILog extends AbstractTableLog {

    /**
     * Insert a full log entry to the external_api_log based on PSR request & response objects
     * @param \PDO $dbh
     * @param string $service
     * @param string $type
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param string $username
     * @return int
     */
    public static function log(\PDO $dbh, string $service, string $type, RequestInterface $request, ResponseInterface $response, string $username){

        $requestBody = json_decode($request->getBody(), true);
        $responseBody = json_decode($response->getBody(), true);

        $logRS = new ExternalAPILogRS();
        $logRS->Log_Type->setNewVal($service);
        $logRS->Sub_Type->setNewVal($type);
        $logRS->requestMethod->setNewVal($request->getMethod());
        $logRS->endpoint->setNewVal($request->getUri());
        $logRS->responseCode->setNewVal($response->getStatusCode());
        $logRS->request->setNewVal($requestBody ? json_encode($requestBody, JSON_PRETTY_PRINT) : $request->getBody()->__tostring());
        $logRS->response->setNewVal($responseBody ? json_encode($responseBody, JSON_PRETTY_PRINT) : $response->getBody()->__tostring());
        $logRS->username->setNewVal($username);

        return self::insertLog($dbh, $logRS);

    }

    /**
     * Insert a custom/summary log entry to the external_api_log
     * @param \PDO $dbh
     * @param string $service
     * @param string $type
     * @param \Psr\Http\Message\RequestInterface $request
     * @param \Psr\Http\Message\ResponseInterface $response
     * @param string $requestBody
     * @param string $responseBody
     * @param string $username
     * @return int
     */
    public static function customlog(\PDO $dbh, string $service, string $type, RequestInterface $request, ResponseInterface $response, string $requestBody, string $responseBody, string $username){

        $logRS = new ExternalAPILogRS();
        $logRS->Log_Type->setNewVal($service);
        $logRS->Sub_Type->setNewVal($type);
        $logRS->requestMethod->setNewVal($request->getMethod());
        $logRS->endpoint->setNewVal($request->getUri());
        $logRS->responseCode->setNewVal($response->getStatusCode());
        $logRS->request->setNewVal($requestBody);
        $logRS->response->setNewVal($responseBody);
        $logRS->username->setNewVal($username);

        return self::insertLog($dbh, $logRS);

    }

    public static function getLog(\PDO $dbh, string $type){
        
        $columns = array(
                array( 'db' => 'idLog', 'dt' => 'idLog'),
                array( 'db' => 'Sub_Type',  'dt' => 'Type' ),
                array( 'db' => 'requestMethod',    'dt' => 'requestMethod'),
                array( 'db' => 'endpoint', 'dt'=>'endpoint'),
                array( 'db' => 'responseCode', 'dt'=> 'responseCode'),
                array( 'db' => 'request', 'dt'=>'request'),
                array( 'db' => 'response', 'dt'=>'response'),
                array( 'db' => 'username', 'dt'=>'username'),
                array( 'db' => 'Timestamp', 'dt'=>'Timestamp'),
            );

            $whereClause = '`Log_Type` IN ("' . $type . '")';

            return SSP::complex($_GET, $dbh, 'external_api_log', 'idLog', $columns, null, $whereClause);
    }

}