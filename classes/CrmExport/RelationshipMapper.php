<?php
namespace HHK\CrmExport;

/*
 * RelationshipMapper.php
 * @author    Eric K. Crane <ecrane@nonprofitsoftwarecorp.org>
 * @copyright 2010-2022 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */


/**
 *
 * @author Eric
 *
 */
class RelationshipMapper
{

    protected $map;
    protected $patientToPG;
    protected $guestToPG;
    protected $mapNeonTypes;

    protected $PGtoPatient;

    /**
     */
    public function __construct(\PDO $dbh) {

        $rstmt = $dbh->query("Select * from map_relations;");

        $this->map = $rstmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->mapNeonTypes = $this->mapNeonTypes($dbh);

    }

    public function relateGuest($guestToPatient) {

        foreach ($this->map as $m) {

            if ($m['PG_Patient'] == $this->PGtoPatient && $m['Guest_Patient'] == $guestToPatient) {
                $this->setGuestToPG($m['Guest_PG']);
                $this->setPatientToPG($m['Patient_PG']);
                break;
            }
        }


        return $this->getGuestToPGId();
    }

    public function clear() {

        $this->setGuestToPG('');
        $this->setPatientToPG('');
        $this->setPGtoPatient('');

        return $this;
    }

    public function mapNeonTypes(\PDO $dbh, $listName = 'relationTypes') {

        $stmtList = $dbh->query("Select * from neon_type_map where List_Name = '" . $listName . "'");
        $items = $stmtList->fetchAll(\PDO::FETCH_ASSOC);

        $mappedItems = array();
        foreach ($items as $i) {
            $mappedItems[$i['HHK_Type_Code']] = array('Neon_Type_Code' => $i['Neon_Type_Code'], 'Neon_Type_Name' => $i['Neon_Type_Name']);
        }

        return $mappedItems;
    }


    public function mapNeonTypeName($relationId) {

        foreach ($this->mapNeonTypes as $r) {
            if ($r['Neon_Type_Code'] == $relationId) {
                return $r['Neon_Type_Name'];
            }
        }
    }

    public function setPGtoPatient($hhkRelCode) {
        $this->PGtoPatient = $hhkRelCode;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getPatientToPGId()
    {
        if (isset($this->mapNeonTypes[$this->getPatientToPG()])) {
            return $this->mapNeonTypes[$this->getPatientToPG()]['Neon_Type_Code'];
        }
        return 0;
    }

    /**
     * @return mixed
     */
    public function getGuestToPGId()
    {
        if (isset($this->mapNeonTypes[$this->getGuestToPG()])) {
            return $this->mapNeonTypes[$this->getGuestToPG()]['Neon_Type_Code'];
        }
        return 0;
    }

    /**
     * @return mixed
     */
    public function getPatientToPG()
    {
        return $this->patientToPG;
    }

    /**
     * @return mixed
     */
    public function getGuestToPG()
    {
        return $this->guestToPG;
    }

    /**
     * @param mixed $patientToPG
     */
    public function setPatientToPG($patientToPG)
    {
        $this->patientToPG = $patientToPG;
    }

    /**
     * @param mixed $guestToPG
     */
    public function setGuestToPG($guestToPG)
    {
        $this->guestToPG = $guestToPG;
    }


}

