<?php
/**
 * Note.php
 *
 * @author    Will Ireland <wireland@nonprofitsoftwarecorp.org>
 * @copyright 2010-2018 <nonprofitsoftwarecorp.org>
 * @license   MIT
 * @link      https://github.com/NPSC/HHK
 */

/**
 * Note Object
 *
 * @author Will
 */
class Note {
	

	
	public function __construct(PDO $dbh)
	{
      $this->$dbh = $dbh;
	}
	
	
	public function get($id)
	{
		//get single note
	}
	
	
	public function save()
	{
		//save new note
	}
	
}

?>