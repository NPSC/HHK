<?php
use HHK\sec\Login;
use HHK\sec\UserClass;
use PHPUnit\Framework\TestCase;

class LoginTest extends TestCase {

    protected array $post = [
        "testUser"=> [
            "txtUname"=>"testUser",
            "txtPass"=>"*mfR5SAMt"
        ]
    ];

    /**
     * Login test
     * @return void
     */
    public function testLoginTestuser() {
        $login = new Login();
        $dbh = $login->initHhkSession(CONF_PATH, ciCFG_FILE);
        $u = new UserClass();
        $loggedIn = $u->_checkLogin($dbh, $this->post['testUser']['txtUname'], $this->post['testUser']['txtPass']);
        $this->assertTrue($loggedIn);
    }

}

?>