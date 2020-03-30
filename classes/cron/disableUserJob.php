<?php
/**
 * disableUserJob.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2020 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

class disableUserJob extends Cron {

    public function action(\PDO $dbh){
        $users = $this->getUsers($dbh);
        
        if($users){
            foreach($users as $user){
                if($user->Last_Login){
                    
                    $lastLogin = new DateTime($user->Last_Login);
                    $lastLogin->setTime(0,0);
                    $now = new DateTime();
                    $today = $now->setTime(0,0);
                    $days = $lastLogin->diff($today)->format('%a');
                    if($days >= $uS->userInactiveDays){
                        $stmt = "update w_users set `status` = 'd' where idName = $user->idName";
                        if($dbh->exec($stmt) > 0){
                            $this->insertUserLog($dbh, $user->User_Name, "User deactivated from inactivity");
                        }
                        
                    }
                }
            }
        }
    }
    
    protected function getUsers(\PDO $dbh){
        $stmt = $dbh->query("SELECT * FROM w_users;");
        
        if ($stmt->rowCount() > 0) {
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            return $rows;
        }else{
            return false;
        }
    }
    
    protected function insertUserLog(\PDO $dbh, $username, $action) {
        
        $remoteIp = self::getRemoteIp();
        if($dbh->exec("insert into w_user_log (Username, Access_Date, IP, `Action`) values ('" . $username . "', now(), '$remoteIp', '$action')")){
            return true;
        }else{
            return false;
        }
    }

}