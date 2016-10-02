<!DOCTYPE html>
<!--
To change this license header, choose License Headers in Project Properties.
To change this template file, choose Tools | Templates
and open the template in the editor.
-->
<html>
    <head>
        <meta charset="UTF-8">
        <title></title>
    </head>
    <body>
        <?php
require ("AutoIncludes.php");

        function expandArray($result, &$markup = '', &$indent = 0) {

            if (is_array($result)) {

                foreach ($result as $k => $r) {

                    if (is_array($r)) {
                        $markup .= HTMLContainer::generateMarkup('p', $k, array('style'=>'font-weight:bold;text-indent:' . $indent .'px;'));
                        $indent = $indent + 50;
                        expandArray($r, $markup, $indent);
                        $indent = $indent - 50;

                    } else {

                        $markup .= HTMLContainer::generateMarkup('p', $k . ': ' . $r, array('style'=>'text-indent:' . $indent .'px;'));
                    }
                }
            } else {
                $markup = $result;
            }

            return $markup;
        }


        require_once('../thirdParty/neon.php');

        $neon = new Neon();

        $keys = array(
            'orgId'=>'beta_imdguesthouse',
            'apiKey'=>'fc0b98aa873885a37b694554b80ee667'
        );

        $loginResult = $neon->login($keys);

        if ( isset( $loginResult['operationResult'] ) && $loginResult['operationResult'] != 'SUCCESS' ) {
            throw new Exception('Login failed');
        }

        $search = array(
            'method' => 'account/listAccounts',
            'columns' => array(
                'standardFields' => array('Account ID', 'Account Type', 'First Name', 'Last Name', 'Phone 1 Number', 'Email 1', 'City', 'State' ),
            ),
            'page' => array(
                'currentPage' => 1,
                'pageSize' => 200,
                'sortColumn' => 'Last Name',
                'sortDirection' => 'ASC',
            ),
        );

        $searchCriteria = array(
            'First Name' =>'Eric',
            'Last Name' => 'Cran'
        );

        foreach ($searchCriteria as $k => $v) {

            if ($k != '' && $v != '') {
                $search['criteria'][] = array($k, 'CONTAIN', $v);
            }
        }



        if ( !empty( $search['criteria'] ) ) {
            $result = $neon->search($search);
        } else {
            $result = null;
        }



        // Check results
        if( isset($result['page']['totalResults'] ) && $result['page']['totalResults'] >= 1 ) {

            foreach($result['searchResults'] as $r) {

                foreach ($r as $l => $f) {
                    echo $l . ': ' . $f . ';  ';
                }
                echo "<br/>";

            }

        }

        // get user by id
        $result = $neon->getIndividualAccount(16182);

        echo expandArray($result);

        // Logout
        $reply = $neon->go( array( 'method' => 'common/logout' ) );


        ?>
    </body>
</html>
