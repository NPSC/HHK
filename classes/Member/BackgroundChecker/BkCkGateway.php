<?php
namespace HHK\Member\BackgroundChecker;

use HHK\Member\Role\Guest;

/**
 *
 * @author Eric
 *
 */
class BkCkGateway
{

    protected $account;
    protected $username;
    protected $password;
    protected $mode;

    protected $orderNumber;

    /**
     */
    public function __construct($account, $username, $password, $mode = 'TEST') {

        $this->account = $account;
        $this->username = $username;
        $this->password = $password;
        $this->mode = $mode;
    }

    public function newOrder(Subject $subject, $orderNumber) {

        $this->orderNumber = $orderNumber;

        $file = '<New_Order>
<login>
    <account>' . $this->account . '</account>
    <username>' . $this->username . '</username>
    <password>' . $this->password . '</password>
</login>
<mode>' . $this->mode . '</mode>
<placeOrder number="' . $orderNumber . '">
    <preselect_all_included_products/>
    <IncludeDefaultProducts />
    <package>Package Name</package>
    <subject>
        <portalfromapplicant>Y</portalfromapplicant>
        <name_first>' . $subject->getFirst() . '</name_first>
        <name_last>' . $subject->getLast() . '</name_last>
        <email>' . $subject->getEmail() . '</email>
        <phone_number>' . $subject->getPhone() . '</phone_number>
    </subject>
</placeOrder>
</New_Order>';

        return $file;
    }


    public function curlGateway($url, $xmlfile, $username, $password) {

        $request_xml = "<?xml version='1.0' encoding='utf-8'?>" . $xmlfile;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);

        curl_setopt($ch, CURLOPT_USERPWD, $username.':'.$password);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_ANY);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_xml);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Connection: close'));

        //Execute the request and also time the transaction ( optional )
//        $start = array_sum(explode(' ', microtime()));
        $result = curl_exec($ch);
//        $stop = array_sum(explode(' ', microtime()));
//        $totalTime = $stop - $start;

        //Check for errors ( again optional )
        if ( curl_errno($ch) ) {
            $result = 'ERROR -> ' . curl_errno($ch) . ': ' . curl_error($ch);
        } else {
            $returnCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            switch($returnCode){
                case 200:
                    break;
                default:
                    $result = 'HTTP ERROR -> ' . $returnCode;
                    break;
            }
        }

        //Close the handle
        curl_close($ch);

        //Output the results and time
        //echo 'Total time for request: ' . $totalTime . "\n";
        //echo $result;

        return $result;
    }

}

