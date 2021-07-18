<?php

namespace HHK\House\Family;

use HHK\sec\Session;
use HHK\House\ReserveData\ReserveData;
use HHK\House\ReserveData\PSGMember\{PSGMember, PSGMemStay};
use HHK\Member\Role\Guest;
use HHK\SysConst\VolMemberType;

/**
 * Description of JoinNewFamily
 *
 * @author Eric
 */

class JoinNewFamily extends Family {

    public function initMembers(\PDO $dbh, ReserveData &$rData) {

        $uS = Session::getInstance();

        // forced New PSG
        $psgMember = $rData->findMemberById($rData->getId());

        if ($psgMember != NULL) {
            $prefix = $psgMember->getPrefix();
        } else {
            $prefix = $uS->addPerPrefix++;
            $psgMember = new PSGMember($rData->getId(), $prefix, VolMemberType::Guest, FALSE, new PSGMemStay(ReserveData::NOT_STAYING));
            $rData->setMember($psgMember);
        }

        $this->roleObjs[$prefix] = new Guest($dbh, $prefix, $rData->getId());

        $psgMember->setStay(($this->roleObjs[$prefix]->getNoReturn() == '' ? ReserveData::NOT_STAYING : ReserveData::CANT_STAY));

    }
}
?>