<?php

namespace HHK\CrmExport\Neon;

/*

 * NeonCRM PHP API Library

 * http://github.com/z2systems/neon-php

 * Learn more about the API at http://help.neoncrm.com/api

 * Learn more about NeonCRM at http://www.z2systems.com

 * Authored by Colin Pizarek

 * http://github.com/colinpizarek

 */

/**
 * Summary of Neon
 */
class Neon {

    /**
     * Summary of userSession
     * @var
     */
    private $userSession;

    /**
     * Summary of txMethod
     * @var string
     */
    public $txMethod;

    /**
     * Summary of txParams
     * @var
     */
    public $txParams;

    /**
     * Summary of rxResult
     * @var
     */
    public $rxResult;


    /**
     * Summary of api
     * @param mixed $request
     * @return mixed
     */
    private function api($request) {

        $this->txMethod = $request['method'];
        $this->txParams = $request['parameters'];
        $url = 'https://api.neoncrm.com/neonws/services/api/' . $this->txMethod;

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $this->txParams);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Required for WAMP only

        $this->rxResult = curl_exec($ch);

        curl_close($ch);

        $reply = json_decode($this->rxResult, TRUE);

        if (is_null($reply)) {
            return array('result'=> array('operationResult'=>'ERROR', 'errorMessage'=>$this->rxResult));
        }

        return $reply;
    }


    /*
     * Retrieves the session ID
     */
    public function getSession() {

        return $this->userSession;

    }



    /*
     * Saves the session ID
     */
    public function setSession($session) {

        $this->userSession = $session;

    }



    /*
     * Executes a login and stores the Session ID.
     */
    public function login(array $keys) {

        $this->setSession('');

        if (isset($keys['orgId']) && isset($keys['apiKey'])) {

            $request = array();
            $request['method'] = 'common/login';
            $request['parameters'] = '&login.apiKey=' . $keys['apiKey'] . '&login.orgid=' . $keys['orgId'];

            $response = $this->api($request);

            reset($response);

            $first_key = key($response);

            $reply = $response[$first_key];

            if ($reply['operationResult'] == 'SUCCESS' && isset($reply['userSessionId'])) {

                $this->setSession($reply['userSessionId']);
                return $reply;

            } else {
                return $reply;
            }

        } else {
            return null;
        }
    }



    /*
     * General purpose API request to be executed after login
     */
    public function go(array $request) {

        if (isset($request['method'])) {

            $str = null;

            if (isset($request['parameters'])) {
                $str = http_build_query($request['parameters']);
            }

            if (isset($request['customParmeters']) && $request['customParmeters'] != '') {
                $str .= $request['customParmeters'];
            }

            $parameters = 'responseType=json&userSessionId=' . $this->getSession() . '&' . $str;

            $build = array();

            $build['method'] = $request['method'];

            $build['parameters'] = $parameters;

            $go = $this->api($build);


            reset($go);

            $first_key = key($go);


            $reply = $go[$first_key];

            return $reply;
        }
    }


    public function getIndividualAccount($accountId) {



        $request = array(

            'method' => 'account/retrieveIndividualAccount',

            'parameters' => array('accountId' => $accountId)

        );



        $result = $this->go($request);



        if (isset($result['individualAccount'])) {

            return $result['individualAccount'];

        }



        return array();

    }


    /*

     * search

     * works with listaccounts, listmemberships, listdonations, etc

     */

    public function search(array $request) {

        $standard = null;

        $custom = null;

        $criteria = null;

        $paging = null;


        if (isset($request['method'])) {

            if (isset($request['columns']['standardFields'])) {

                foreach ($request['columns']['standardFields'] as $std) {

                    $std = str_replace(' ', '%20', $std);

                    $standard = $standard . '&outputfields.idnamepair.id=&outputfields.idnamepair.name=' . $std;

                }
            }


            if (isset($request['columns']['customFields'])) {

                foreach ($request['columns']['customFields'] as $cus) {

                    $cus = str_replace(' ', '%20', $cus);

                    $custom = $custom . '&outputfields.idnamepair.name=&outputfields.idnamepair.id=' . $cus;

                }
            }



            if (isset($request['criteria'])) {

                foreach ($request['criteria'] as $crit) {

                    $key = '&searches.search.key=' . $crit[0];

                    $operator = '&searches.search.searchOperator=' . $crit[1];

                    $value = '&searches.search.value=' . $crit[2];

                    $criteria = $criteria . $key . $operator . $value;

                    $criteria = str_replace(' ', '%20', $criteria);

                }
            }


            if (isset($request['page']['currentPage'])) {
                $paging = $paging . '&page.currentPage=' . $request['page']['currentPage'];
            }

            if (isset($request['page']['pageSize'])) {
                $paging = $paging . '&page.pageSize=' . $request['page']['pageSize'];
            }

            if (isset($request['page']['sortColumn'])) {
                $paging = $paging . '&page.sortColumn=' . str_replace(' ', '%20', $request['page']['sortColumn']);
            }

            if (isset($request['page']['sortDirection'])) {
                $paging = $paging . '&page.sortDirection=' . $request['page']['sortDirection'];
            }

            $addon = 'responseType=json&userSessionId=' . $this->getSession();

            $parameters = $addon . $criteria . $standard . $custom . $paging;

            $build = array();

            $build['method'] = $request['method'];

            $build['parameters'] = $parameters;

            $go = $this->api($build);

            if (is_null($go) === FALSE) {

                return $this->parseListRequest($go);

            }


            return NULL;

        } else {
            return null;
        }
    }



    public function searchKeyword($letters) {

        $l = str_replace(' ', '%20', $letters);

        if ($l != '') {

            $parameters = 'responseType=json&userSessionId=' . $this->getSession() . '&userType=Individual' . '&keyword=' . $l;


            $build = array();

            $build['method'] = 'account/listAccountsByKeywordSearch';

            $build['parameters'] = $parameters;

            $go = $this->api($build);


            reset($go);

            $fk = key($go);

            $result = $go[$fk];


            $result['searchResults'] = $result['accountSearchResults']['accountSearchResult'];


            return $result;

        } else {

            return null;

        }
    }



    /*

     * Parses the server response for list requests

     */

    private function parseListRequest(array $result) {

        reset($result);

        $first_key = key($result);

        $data = $result[$first_key];


        if ($data['operationResult'] == 'SUCCESS') {

            $people = array();

            foreach ($data['searchResults']['nameValuePairs'] as $key => $value) {

                $people[$key] = $value;

                foreach ($people as $person) {

                    foreach ($person['nameValuePair'] as $pair) {

                        if (isset($pair['name'])) {
                            $name = $pair['name'];
                        } else {
                            $name = null;
                        }

                        if (isset($pair['value'])) {
                            $value = $pair['value'];
                        } else {
                            $value = null;
                        }

                        $data['searchResults'][$key][$name] = $value;
                    }
                }
            }

            unset($data['searchResults']['nameValuePairs']);
        }

        return $data;
    }

}
