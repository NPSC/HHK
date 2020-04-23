<?php
class SFTPConnection
{
	private $connection;
	private $sftp;
	
	public function __construct($host, $port=22)
	{
		if (function_exists('ssh2_connect') === FALSE) {
			throw new Exception("ssh2_sftp is missing. ");
		}
		
		$this->connection = ssh2_connect($host, $port);
		
		if (! $this->connection) {
			throw new Exception("Could not connect to $host on port $port.");
		}
	}
	
	public function login($username, $password)
	{
		if (! ssh2_auth_password($this->connection, $username, $password)) {
			throw new Exception("Could not authenticate with username $username " .
					"and password $password.");
		}
			
		$this->sftp = ssh2_sftp($this->connection);
		
		if (! $this->sftp) {
			throw new Exception("Could not initialize SFTP subsystem.");
		}
	}
	
	public function uploadFile($data_to_send, $remote_file)
	{
		$sftp = $this->sftp;
		$stream = fopen("ssh2.sftp://$sftp$remote_file", 'w');
		
		if (! $stream) {
			throw new Exception("Could not open file: $remote_file");
		}
			
		//$data_to_send = @file_get_contents($local_file);
		
		if ($data_to_send === FALSE) {
			throw new Exception("No data to send.");
		}

//		if (fwrite($stream, $data_to_send) === FALSE) {
//			throw new Exception("Could not send data.");
//		}

		$bytesWritten = $this->fwriteStream($stream, $data_to_send);
					
		fclose($stream);
		
		return $bytesWritten;
	}
	
	// Writing to a network stream may end before the whole string is written.
	protected function fwriteStream($fp, $string) {
		$fwrite = 0;
		for ($written = 0; $written < strlen($string); $written += $fwrite) {
			$fwrite = fwrite($fp, substr($string, $written));
			if ($fwrite === false) {
				return $written;
			}
		}
		return $written;
	}

}

