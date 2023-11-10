<?php
use HHK\sec\Crypto;
use HHK\Update\Install;
use PHPUnit\Framework\TestCase;

class InstallTest extends TestCase {

    protected array $post = [
        "adminPW"=> ENC,
        "numRooms" =>NUM_ROOMS,
        "selModel"=>PRICE_MODEL,
        'cbFin'=>"on"
    ];

    /**
     * DB install test
     * @return void
     */
    public function testInstallDatabase() {
        
        $install = new Install();
        $this->assertInstanceOf("HHK\Update\Install", $install);

        $results = $install->installDB();
        $this->assertArrayHasKey("success", $results);
    }

    public function testInstallInitialData(){
        $install = new Install();
        $this->assertInstanceOf("HHK\Update\Install", $install);

        $results = $install->loadMetadata(Crypto::decryptMessage($this->post["adminPW"]));
        $this->assertArrayHasKey("success", $results);
    }

    public function testInstallRooms(){
        $install = new Install();
        $this->assertInstanceOf("HHK\Update\Install", $install);

        $results = $install->installRooms($this->post["numRooms"], $this->post["selModel"], $this->post["cbFin"]);
        $this->assertArrayHasKey("success", $results);
    }

}
