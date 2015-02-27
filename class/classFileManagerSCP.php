<?
define("PIPE_READY", 0);
define("PIPE_WRITY", 1);
define("PIPE_ERROR", 2);

class FileManagerSCP extends BaseObjectAdmin
{
	var	$dirDelimiter	= "/";
	var	$root			= ".";
	var	$parent			= "..";

	var	$Connection;
	var	$descriptorspec = array(
								PIPE_READY	=> array("pipe", "r"),  							// stdin is a pipe that the child will read from
								PIPE_WRITY	=> array("pipe", "w"),							// stdout is a pipe that the child will write to
								PIPE_ERROR	=> array("file", "/tmp/error-output.txt", "a")	// stderr is a file to write to
								);
	var	$pipes;

	function FileManagerSCP($host = false, $user = false, $pass = false)
	{
		if($host && $user && $pass)
		{
			$this->setHost($host);
			$this->setUser($user);
			$this->setPass($pass);
			$this->connect();
		}
	}

	function connect()
	{
		$connection = proc_open("bash", $this->descriptorspec, $this->pipes);
		if(is_resource($connection)) $this->setConnection($connection);
		#if(!$this->getHost() || !$this->getUser() || !$this->GetPass()) die('<b>You tried to connect without having set the proper arguments!<br></b>');
		#	system("ssh ".$this->getUser().":".$this->getPass()."@".$this->getHost(), $a, $b);
		#s(implode("", file("/tmp/error-output.txt")));
	}

	function readDirectory($targetRemote)
	{
		
	}

	function copyDirectory($srcRoot, $targetRoot, $dir, $local_remote, $recursive = true)
	{
		#s(func_get_args());
		#echo "still no functionality. come back tomorrow!<br>";
	}

	function upload($sourceLocal, $targetRemote)
	{
		
	}

	function download($sourceLocal, $targetRemote)
	{
		
	}

	function deleteDirectory($dir, $delete_empty)
	{
		
	}

	function deleteDirectoryLocal($dir, $delete_empty)
	{
		
	}

	

	function _readDirCopyData($root, $path, $recursive=false)
	{
		$arrayFiles = array();
		$root = ((substr($root,-1,1)=="/") ? substr($root,0,-1) : $root);
		$path = ((substr($path,-1,1)=="/") ? substr($path,0,-1) : $path);
		if(is_dir($root . "/" . $path) && $path != "." && $path != "..")
		{
			$dir = array();
			$d = dir($root . "/" .$path);
			while(false !== ($file = $d->read()))
			{
				if($file != "." && $file != "..")
				{
					if(($isDir = is_dir($root . "/" . $path . "/" . $file)) && $recursive)
						$arrayFiles	= array_merge_recursive($arrayFiles, $this->_readDirCopyData($root, $path . "/" . $file, $recursive));
					elseif($isDir && !$recursive) continue;
					else
					{
						$arrayFiles['fileName'][]						= $path . "/" . $file;
						$arrayFiles['fileMTime'][$path . "/" . $file]	= filemtime($root . "/" . $path . "/" . $file);
					}
				}
			}
		}
		return $arrayFiles;
	}

	function _fixPath($path) { return (substr($path,-1,1)==$this->dirDelimiter) ? substr($path,0,-1) : $path; }

	function getHost() { return $this->Host ? $this->Host : false; }
	function setHost($host) { $this->Host = $host; }
	function getUser() { return $this->User ? $this->User : false; }
	function setUser($user) { $this->User = $user; }
	function getPass() { return $this->Pass ? $this->Pass : false; }
	function setPass($pass) { $this->Pass = $pass; }

	function &getConnection() { return $this->Connection; }
	function &setConnection($connection) { return $this->Connection =& $connection; }
}
?>