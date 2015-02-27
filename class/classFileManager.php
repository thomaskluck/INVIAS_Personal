<?
define("FCOPY_LOCAL_LOCAL", 1);
define("FCOPY_LOCAL_REMOTE", 2);
define("FCOPY_REMOTE_LOCAL", 4);

class FileManager extends BaseObjectAdmin
{
	var $FileManagerLCL;
	var	$FileManagerFTP;
	var	$FileManagerSFTP;
	var	$FileManagerSCP;

	var $LocalRemote	=	FCOPY_LOCAL_LOCAL;
	var	$RemoteType		=	'FTP';

	var	$RemoteModes	=	array('LCL', 'FTP', 'SFTP', 'SCP');

	var	$remoteHost;
	var	$remotePort;
	var	$remoteUser;
	var	$remotePass;

	var	$dirDelimiter = "/";

	function FileManager()
	{
		
	}

	function setParams($local_remote, $remoteType, $remoteHost=false, $remotePort=false, $remoteUser=false, $remotePass=false)
	{
		$this->setLocalRemote($local_remote);
		$this->setRemoteType($remoteType);
		$this->setRemoteHost($remoteHost);
		$this->setRemotePort($remotePort);
		$this->setRemoteUser($remoteUser);
		$this->setRemotePass($remotePass);
		
		$this->FileManagerLCL = new FileManagerLCL;
		if($this->getLocalRemote() == FCOPY_LOCAL_REMOTE || $this->getLocalRemote() == FCOPY_REMOTE_LOCAL)
		{
			switch($this->getRemoteType())
			{
				case	'FTP'	:	$this->FileManagerFTP = new FileManagerFTP($this->getRemoteHost(), $this->getRemotePort(), $this->getRemoteUser(), $this->getRemotePass());break;
				case	'SFTP'	:	$this->FileManagerSFTP = new FileManagerSFTP($this->getRemoteHost(), $this->getRemotePort(), $this->getRemoteUser(), $this->getRemotePass());break;
				case	'SCP'	:	$this->FileManagerSCP = new FileManagerSCP($this->getRemoteHost(), $this->getRemotePort(), $this->getRemoteUser(), $this->getRemotePass());break;
			}
		}
		else
		{
			$this->FileManagerFTP = false;
			$this->FileManagerSCP = false;
		}
	}

/*
	function _createDirStructureLocal($root, $structure)
	{
		if(is_array($structure) && count($structure))
		{
			$ok = true;
			foreach($structure as $dirname => $subdirs)
				$ok = (is_dir($root . "/" . $dirname) ? true : mkdir($root . "/" . $dirname, 0775)) && $ok;
			foreach($structure as $dirname => $subdirs)
				$ok = ((is_array($subdirs) && @count($subdirs)) ? $this->_createDirStructure($root . "/$dirname", $subdirs) : true) && $ok;
		}
		return $ok;
	}*/

	function getDirectoryStructure($directory)
	{
		$directory = $this->_fixPath($directory);
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->getDirectoryStructure($directory);
	}

	function _createDirStructure($root, $structure, $mode=false)
	{
		if(is_array($structure) && count($structure))
		{
			$fmObjectName = "FileManager" . $this->getRemoteType();
			$fmObject =& $this->$fmObjectName;
			$ok = true;
			foreach($structure as $dirname => $subdirs) $ok = (($fmObject->_isDir($root . "/" . $dirname) ? true : $fmObject->makeDir($root . "/" . $dirname, $mode)) && $ok);
			foreach($structure as $dirname => $subdirs) $ok = (((is_array($subdirs) && @count($subdirs)) ? $this->_createDirStructure($root . "/$dirname", $subdirs, $mode) : true) && $ok);
		}
		return @$ok;
	}

	function moveFile($source, $target, $mode=false)
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->moveFile($source, $target, $this->getLocalRemote(), $mode);
	}

	function copyFile($source, $target, $mode=false)
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->copyFile($source, $target, $this->getLocalRemote(), $mode);
	}

	function copyDirectory($srcRoot, $targetRoot, $dir, $recursive = true, $compare = false)
	{
		$changeLog = array();
		if($compare) $changeLog = (is_array($arrayChanges = $this->_compareCopyData($srcRoot, $targetRoot, $dir, $recursive)) ? $arrayChanges : array());
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		$fmObject->copyDirectory($srcRoot, $targetRoot, $dir, $this->getLocalRemote(), $recursive);
		return $changeLog;
	}

	function changeMod($target)
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->changeMod($target, $mode);
	}

	function isDir($target)
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->_isDir($target);
	}

	function makeDir($target, $mode=false)
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->makeDir($target, $mode);
	}

	function getFileSize($target)
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->getFileSize($target);
	}

	function deleteFile($file)
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->deleteFile($file);
	}

	# $delete_empty -> 0 = just empty directory / 1 = delete it completely
	function deleteDirectory($dir, $local_remote, $delete_empty=1)
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		$fmObject->deleteDirectory($dir, $this->getLocalRemote());
	}

	function _compareCopyData($srcRoot, $targetRoot, $dir, $recursive)
	{
		$fmSource =& $this->_fetchSource();
		$fmTarget =& $this->_fetchTarget();
		$compareData = array("add" => array(), "chg" => array(), "del" => array());
		$srcFiles		= $fmSource->_readDirCopyData($srcRoot, $dir, $recursive);
		$targetFiles	= $fmTarget->_readDirCopyData($targetRoot, $dir, $recursive);
		
		#mail("tk@invias.de", "SM2 Debug Fuck", "Source:\n" . var_export($srcFiles, true) . "\n\nTarget:\n" . var_export($targetFiles, true));
		
		if(!is_array(@$srcFiles['fileName']) && !is_array($targetFiles['fileName'])) return array('add'=>array(),'chg'=>array(),'del'=>array());
		if(!is_array(@$srcFiles['fileName'])) $compareData['del'] = $targetFiles['fileName'];
		elseif(!is_array(@$targetFiles['fileName'])) $compareData['add'] = $srcFiles['fileName'];
		else
		{
			$compareData['add'] = array_diff($srcFiles['fileName'], $targetFiles['fileName']);
			$compareData['del'] = array_diff($targetFiles['fileName'], $srcFiles['fileName']);
			foreach($srcFiles['fileMTime'] as $key => $time)
				if(isset($targetFiles['fileMTime'][$key]))
					if($time > $targetFiles['fileMTime'][$key]) $compareData['chg'][] = $key;
		}
		return $compareData;
	}

	function &_fetchSource()
	{
		switch($this->getLocalRemote())
		{
			case	FCOPY_LOCAL_LOCAL	:	$fmObject	=&	$this->FileManagerLCL;break;
			case	FCOPY_LOCAL_REMOTE	:	$fmObject	=&	$this->FileManagerLCL;break;
			case	FCOPY_REMOTE_LOCAL	:	if($this->getRemoteType() == 'FTP') $fmObject =& $this->FileManagerFTP;
											elseif($this->getRemoteType() == 'SFTP') $fmObject =& $this->FileManagerSFTP;
											elseif($this->getRemoteType() == 'SCP') $fmObject =& $this->FileManagerSCP;
											break;
		}
		return $fmObject;
	}

	function &_fetchTarget()
	{
		switch($this->getLocalRemote())
		{
			case	FCOPY_LOCAL_LOCAL	:	$fmObject	=&	$this->FileManagerLCL;break;
			case	FCOPY_LOCAL_REMOTE	:	if($this->getRemoteType() == 'FTP') $fmObject =& $this->FileManagerFTP;
											elseif($this->getRemoteType() == 'SFTP') $fmObject =& $this->FileManagerSFTP;
											elseif($this->getRemoteType() == 'SCP') $fmObject =& $this->FileManagerSCP;
											break;
			case	FCOPY_REMOTE_LOCAL	:	$fmObject	=&	$this->FileManagerLCL;break;
		}
		return $fmObject;
	}

	function getLocalRemote() { return $this->LocalRemote; }
	function setLocalRemote($parm) { return $this->LocalRemote = $parm; }

	function getRemoteType() { return isset($this->RemoteType) ? $this->RemoteType : false; }
	function setRemoteType($type) { return $this->RemoteType = (in_array($type, $this->RemoteModes) ? $type : false ); }

	function getRemoteHost() { return $this->remoteHost ? $this->remoteHost : false; }
	function setRemoteHost($host) { return $this->remoteHost = strlen($host) ? $host : false; }
	function getRemotePort() { return $this->remotePort ? $this->remotePort : false; }
	function setRemotePort($port) { return $this->remotePort = strlen($port) ? $port : false; }
	function getRemoteUser() { return $this->remoteUser ? $this->remoteUser : false; }
	function setRemoteUser($user) { return $this->remoteUser = strlen($user) ? $user : false; }
	function getRemotePass() { return $this->remotePass ? $this->remotePass : false; }
	function setRemotePass($pass) { return $this->remotePass = strlen($pass) ? $pass : false; }

	function userIsLocked()
	{
		$fmObjectName = "FileManager" . $this->getRemoteType();
		$fmObject =& $this->$fmObjectName;
		return $fmObject->userIsLocked();
	}

	function getPathDifference($fixedPath, $targetPath)
	{
		$lenDiff	= (strlen($fixedPath) - strlen($targetPath));
		if($lenDiff > 0) $difference = substr($fixedPath, -$lenDiff, $lenDiff);
		elseif($lenDiff < 0)
		{
			$diff = substr($targetPath, $lenDiff, -$lenDiff);
			$dots = explode("/", $diff);
			if(is_array(@$dots) && count($dots)) $difference = str_repeat("/..", count($dots)-1);
			else $difference = "";
		}
		else $difference="";
		return $difference;
	}

	function getPathRoute($startPath, $targetPath)
	{
		$startPath = $this->_fixPathFull($startPath);
		$targetPath= $this->_fixPathFull($targetPath);
		$start = explode("/", $startPath);
		$target= explode("/", $targetPath);
		$pathBack = array();
		$startCount = count($start);
		$targetCount= count($target);
		$steps = count($start) + count($target);
		foreach($start as $key => $val) $pathBack[] = "..";
		return implode("/", array_merge($pathBack, $target));
	}

	function _fixPath($path) { return (substr($path,-1,1)==$this->dirDelimiter) ? substr($path,0,-1) : $path; }

	function _fixPathFull($path)
	{
		if(substr($path,-1,1)==$this->dirDelimiter) $path = substr($path,0,-1);
		if(substr($path,0,1)==$this->dirDelimiter) $path = substr($path, 1, strlen($path)-1);
		return $path;
	}
}
?>