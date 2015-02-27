<?
class FileManagerFTP extends BaseObjectAdmin
{
	var $Connection;
	var $MaxConnectionAttempts = 3;
	var $TransferMode =   FTP_ASCII;
	var $validTransferModes	= array(FTP_BINARY, FTP_ASCII, FTP_TEXT, FTP_IMAGE);

	var $dirDelimiter			= "/";
	var $passive;
	var $overwrite;
	var $nonBlocking			= false;
	var $blockSizeDifference	= 1024;

	var $logfile = "tmp/ftp.log";
	var $logfileHandle;
	
	var	$_debug = false;
	
	function FileManagerFTP($host = false, $port = false, $user = false, $pass = false)
	{
		$this->openMyLog();
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		$this->setNonBlocking(false);
		$this->setTransferMode(FTP_BINARY);
		$this->setOverwrite(true);
		$this->setMaxConnectionAttempts(1);
		if($host && $port && $user && $pass)
		{
			$this->setHost($host);
			$this->setPort($port);
			$this->setUser($user);
			$this->setPass($pass);
			$this->connect();
			$this->_pasvAutoSense();
		}
	}

	function connect($attempts=0)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection) ftp_close($this->Connection);
		$this->Connection = false;
		if(!$this->getHost() || !$this->getPort() || !$this->getUser() || !$this->getPass()) die('<b>You tried to connect without having set the proper arguments!<br></b>');
		$this->Connection = ftp_connect($this->getHost(), $this->getPort(), 30);
		if(!$this->Connection && ($attempts < $this->getMaxConnectionAttempts())) $this->connect($attempts++);
		if($this->Connection) ftp_set_option($this->Connection, FTP_TIMEOUT_SEC, 30);
		# addobjectError causes a mean bug when called in helper classes that are not registered
		# in the system! The XML output would be rendered not wellformed and while publishing a
		# website vias ftp the whole system locks up.
		#else $this->addObjectError(1);
		$loginResult		= ftp_login($this->Connection, $this->getUser(), $this->getPass());
		ftp_set_option($this->Connection, FTP_AUTOSEEK, false);
		if(!$loginResult) die('Could not establish connection with server and login!');
		else return $this->Connection;
	}

	function logout() { return $this->Connection ? ftp_close($this->Connection) : true; }

	function pasv($arg)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect()) return ftp_pasv($this->Connection, $this->passive = $arg);
		else die('Passive mode for what? There is no connection! Connect to server and login first!<br>');
	}

	function _pasvAutoSense()
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		ftp_set_option($this->Connection, FTP_TIMEOUT_SEC, 20);
		if(!is_array(@ftp_rawlist($this->Connection, ".")))
		{
			$this->connect();
			$this->pasv(1);
		}
		if(!is_array(@ftp_rawlist($this->Connection, "."))) die('Error! Connection closed!');
		ftp_set_option($this->Connection, FTP_TIMEOUT_SEC, 10);
		return $this->passive;
	}

	function upload($sourceLocal, $targetRemote, $continue = false)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			if(is_file($this->_fixPath($sourceLocal))) return $this->_ftpPut($sourceLocal, $targetRemote, $continue);
			elseif(@is_dir($this->_fixPath($sourceLocal))) return $this->_uploadDir($sourceLocal, $targetRemote);
			else return false;
		}
		else die('No download without a connection! Connect to server and login first!<br>');
	}

	function _uploadDir($sourceLocal, $targetRemote)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		#if(!$this->makeDir($this->_fixPath($targetRemote), 0777)) die('cannot create remote dir &quot;'.$targetRemote.'&quot;! Cannot copy dir &quot;'.$sourceLocal.'&quot;!');
		#if(!$this->makeDir($this->_fixPath($targetRemote)) && !$this->changeMod($this->_fixPath($targetLocal), 0777)) die('cannot create remote dir &quot;'.$targetRemote.'&quot;! Cannot copy dir &quot;'.$sourceLocal.'&quot;!');
		if(!$this->makeDir($this->_fixPath($targetRemote), 775)) die('cannot create remote dir &quot;'.$targetRemote.'&quot;! Cannot copy dir &quot;'.$sourceLocal.'&quot;!');
		if(count($dirList = $this->_getDirLocal($sourceLocal)))
		{
			while(list($key, $itemname) = each($dirList))
			{
				if($itemname != "." && $itemname != "..")
				{
					if($this->_isDirLocal($this->_fixPath($sourceLocal) . $this->dirDelimiter . $itemname)) $this->_uploadDir($this->_fixPath($sourceLocal) . $this->dirDelimiter . $itemname, $this->_fixPath($targetRemote) . $this->dirDelimiter . $itemname);
					else @$this->_ftpPut($this->_fixPath($sourceLocal).$this->dirDelimiter.$itemname, $this->_fixPath($targetRemote).$this->dirDelimiter.$itemname);
				}
			}
		}
	}

	function download($sourceRemote, $targetLocal)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			if($this->_isFile($sourceRemote)) return @ftp_get($this->Connection, $targetLocal, $sourceRemote, $this->getTransferMode());
			elseif($this->_isDir($sourceRemote)) return $this->_downloadDir($sourceRemote, $targetLocal);
			else return false;
		}
		else die('No download without a connection! Connect to server and login first!<br>');
	}

	function _downloadDir($sourceRemote, $targetLocal)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if(!@mkdir($this->_fixPath($targetLocal), 0777) && !@chmod($this->_fixPath($targetLocal), 0777)) die('cannot create dir &quot;'.$targetLocal.'&quot;! Cannot copy dir &quot;'.$sourceRemote.'&quot;!');
		if(count($dirList = $this->readDirectory($sourceRemote)))
		{
			while(list($key, $itemname) = each($dirList))
			{
				if($itemname != "." && $itemname != "..")
				{
					$itemname = str_replace($sourceRemote,"", $itemname);
					if($this->_isDir($this->_fixPath($sourceRemote) . $this->dirDelimiter . $itemname))
						$this->_downloadDir($this->_fixPath($sourceRemote) . $this->dirDelimiter . $itemname, $this->_fixPath($targetLocal) . $this->dirDelimiter . $itemname);
					else
						@ftp_get($this->Connection, $this->_fixPath($targetLocal) . $this->dirDelimiter . $itemname, $this->_fixPath($sourceRemote) . $this->dirDelimiter . $itemname, $this->getTransferMode());
				}
			}
		}
	}

	function deleteFile($targetRemote)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect()) return @ftp_delete($this->Connection, $targetRemote);
		else die('No deletion without a connection! Connect to server and login first!<br>');
	}

	function deleteDirectory($root, $path)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			$root		 = $this->_fixPath($root);
			$path	 = $this->_fixPath($path);
			$arrayFiles = $this->readDirectory($root . $this->dirDelimiter . $path);
			if(is_array($arrayFiles) && count($arrayFiles))
			{
				foreach($arrayFiles as $key => $file)
				{
					$file = str_replace($root . $this->dirDelimiter . $path, "", $file);
					if($this->_isDir($root . $this->dirDelimiter . $path . $this->dirDelimiter . $file)) $this->deleteDirectory($root, $path . $this->dirDelimiter . $file);
					else $this->deleteFile($root . $this->dirDelimiter . $path . $this->dirDelimiter . $file);
				}
			}
			@ftp_rmdir($this->Connection, $root . $this->dirDelimiter . $path);
		}
		else die('No deletion without a connection! Connect to server and login first!<br>');
	}

	function copyFile($source, $target, $local_remote, $mode=false)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			$source = $this->_fixPath($source);
			$target = $this->_fixPath($target);
			switch($local_remote)
			{
				case  FCOPY_LOCAL_REMOTE	:	$status = ($this->upload($source, $target) && $this->changeMod($target, $mode)); break;
				case  FCOPY_REMOTE_LOCAL	:	$status = ($this->download($source, $target) && @chmod($target, $mode)); break;
			}
			return $status;
		}
		else die('I WANT a connection! Never try to copy a file without a connection!');
	}

	function moveFile($source, $target, $local_remote, $mode=false)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			$source = $this->_fixPath($source);
			$target = $this->_fixPath($target);
			switch($local_remote)
			{
				case  FCOPY_LOCAL_REMOTE	:	$status = ($this->upload($source, $target) && unlink($source) && ($mode ? $this->changeMod($target, $mode) : true)); break;
				case  FCOPY_REMOTE_LOCAL	:	$status = ($this->download($source, $target) && $this->deleteFile($source) && ($mode ? chmod($target, $mode) : true)); break;
			}
			return $status;
		}
		else die('I WANT a connection! Never try to copy a file without a connection!');
	}

	function renameFile($oldName, $newName)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect()) return @ftp_rename($this->Connection, $oldName, $newName);
		else die('You want to rename a file, huh? Try connecting to the server first, smartass!');
	}

	function copyDirectory($srcRoot, $targetRoot, $dir, $local_remote, $recursive = true)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			$srcRoot	= $this->_fixPath($srcRoot);
			$targetRoot = $this->_fixPath($targetRoot);
			$dir	  = $this->_fixPath($dir);
			switch($local_remote)
			{
				case  FCOPY_LOCAL_REMOTE	:	$this->deleteDirectory($targetRoot, $dir);
												$this->upload($srcRoot . $this->dirDelimiter . $dir, $targetRoot . $this->dirDelimiter . $dir);
												break;
				case  FCOPY_REMOTE_LOCAL	:	$this->deleteDirectoryLocal($targetRoot, $dir);
												$this->download($srcRoot . $this->dirDelimiter . $dir, $targetRoot . $this->dirDelimiter . $dir);
												break;
			}
		}
		die('You are not the man who is generous on connections, huh? C´mon, I need one!');
	}

	function getDirectoryStructure($directory)
	{
		$_logArgs = func_get_args();
		$this->trace(__FUNCTION__, $_logArgs);
		return array();
	}

	function _readDirCopyData($root, $path, $recursive=false, $level=0)
	{
		$_logArgs = func_get_args(); $this->trace(__FUNCTION__, $_logArgs);
		$arrayFiles = array();
		$root = $this->_fixPath($root); $path = $this->_fixPath($path);
		if($level==0) $this->changeDir($this->dirDelimiter);
		if($this->_isDir($root . $path) && $path != "." && $path != "..")
		{
			@ftp_chdir($this->Connection, $root . $path);
			$dir = $this->readDirectory($root . $path);
			foreach($dir as $key => $file) $dir[$key] = str_replace($root . $path . $this->dirDelimiter, "", $file);
			while(list($key, $file) = each($dir))
			{
				if($file != "." && $file != "..")
				{
					if(($isDir = $this->_isDir($root . $path . $this->dirDelimiter . $file)) && $recursive)
						$arrayFiles = array_merge_recursive($arrayFiles, $this->_readDirCopyData($root, $path . $this->dirDelimiter . $file, $recursive, $level++));
					elseif($isDir && !$recursive) continue;
					else
					{
						$arrayFiles['fileName'][] = $path . $this->dirDelimiter . $file;
						$arrayFiles['fileMTime'][$path . $this->dirDelimiter . $file] = $this->fileModTime($root . $path . $this->dirDelimiter . $file);
					}
				}
			}
		}
		return $arrayFiles;
	}

	function makeDir($targetRemote, $mode=false)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			if($this->_isDir($targetRemote))
			{
				$this->changeMod($this->_fixPath($targetRemote), $mode);
				return true;
			}
			else
			{
				if($mode==false) ftp_mkdir($this->Connection, $this->_fixPath($targetRemote));
				else
				{
					@ftp_mkdir($this->Connection, $this->_fixPath($targetRemote));
					$this->changeMod($this->_fixPath($targetRemote), $mode);
					if($this->_isDir($targetRemote)) return true;
					else return false;
				}
			}
			#return ($this->_isDir($targetRemote) ? true : (($mode == false) ? @ftp_mkdir($this->Connection, $this->_fixPath($targetRemote)) : ( @ftp_mkdir($this->Connection, $this->_fixPath($targetRemote)) && $this->changeMod($this->_fixPath($targetRemote), $mode) ) ) );
		}
		else die('A connection would be bliss! Connect to server and login first!<br>');
	}

	function getFileSize($targetRemote)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect()) return ftp_size($this->Connection, $this->_fixPath($targetRemote));
		else return false;
	}

	function readDirectory($targetRemote)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect()) return (($list = ftp_nlist($this->Connection, $this->_fixPath($targetRemote))) ? $list : array());
		else die('No directory listing for guys who don´t connect! Connect to server and login first!<br>');
	}

	function _ftpGet($sourceRemote, $targetLocal)
	{
		$_logArgs = func_get_args(); $this->trace(__FUNCTION__, $_logArgs);
		if($this->getNonBlocking()==true) return false;
		else return false;
	}

	function _ftpPut($sourceLocal, $targetRemote, $continue=false)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			if($this->getOverwrite()==true) $this->deleteFile($targetRemote);
			if($this->getNonBlocking()==true)
			{
				$currentDir = (strlen($temp = ftp_pwd($this->Connection)) ? $temp : $this->dirDelimiter);
				$ret = ($continue ? @ftp_nb_put($this->Connection, $this->_fixPath($targetRemote), $this->_fixPath($sourceLocal), $this->getTransferMode(), $this->getFileSize($targetRemote)) : ftp_nb_put($this->Connection, $this->_fixPath($targetRemote), $this->_fixPath($sourceLocal), $this->getTransferMode()));
				while ($ret == FTP_MOREDATA) $ret = @ftp_nb_continue($this->Connection);
				return (($ret == FTP_FINISHED) ? true : false);
			}
			else return ($continue ? @ftp_put($this->Connection, $targetRemote, $sourceLocal, $this->getTransferMode(), $this->getFileSize($targetRemote)) : @ftp_put($this->Connection, $targetRemote, $sourceLocal, $this->getTransferMode()));
		}
		else die('No connection no cookie, pal!');
	}

	# chmod only with php > 5
	/*
	function changeMod($targetRemote, $mode)
	{
		if($this->Connection || $this->connect()) return (ftp_chmod($this->Connection, $mode, $this->_fixPath($targetRemote)) ? true : false);
		else die('You cannot chmod if you are not connected! Connect to server and login first!<br>');
	}*/

	function changeMod($targetRemote, $mode)
	{
		#mail("tk@invias.de", "Chmod lLog - $targetRemote $mode", "");
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			$chmodCmd = "CHMOD 0" . base_convert($mode, 10, 8) . " " . $this->_fixPath($targetRemote);
			return ftp_site($this->Connection, $chmodCmd);
		}
		else die('You cannot chmod if you are not connected! Connect to server and login first!<br>');
	}

	/*
	function _isDir($targetRemote)
	{
		if($this->Connection || $this->connect()) { $currentDir = ftp_pwd($this->Connection); return (@ftp_chdir($this->Connection, $this->_fixPath($targetRemote)) ? ftp_chdir($this->Connection, $currentDir) : false ); }
		else die('Would be nice if you connected before trying to use the isDir() function! Connect to server and login first!<br>');
	}
	*/

	function _isDir($targetRemote)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			$currentDir = ftp_pwd($this->Connection);
			if(@ftp_chdir($this->Connection, $this->_fixPath($targetRemote)))
			{
				@ftp_chdir($this->Connection, $currentDir);
				return true;
			}
			else return false;
		}
		else die('Would be nice if you connected before trying to use the isDir() function! Connect to server and login first!<br>');
	}

	function _isFile($targetRemote)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect()) return (($this->getFileSize($this->_fixPath($targetRemote)) == -1) ? false : true);
		else die('Would be nice if you connected before trying to use the isFile() function! Connect to server and login first!<br>');
	}

/*
	function fileModTime($targetRemote)
	{
		if($this->Connection || $this->connect()) return (($unixTime = @ftp_mdtm($this->Connection, $this->_fixPath($targetRemote))) != -1 ? $unixTime : false);
		else die('Would be nice if you connected before trying to use the fileModTime() function! Connect to server and login first!<br>');
	}*/

	function fileModTime($targetRemote)
	{
		$_logArgs = func_get_args();
			$this->trace(__FUNCTION__, $_logArgs);
		if($this->Connection || $this->connect())
		{
			$unixTime = @ftp_mdtm($this->Connection, $this->_fixPath($targetRemote));
			if($unixTime != -1) return $unixTime;
			else return false;
		}
		else die('fuck!');
	}

	function _isDirLocal($path) { return file_exists($this->_fixPath($path) . $this->dirDelimiter . $this->parent) ? 1 : 0; }

	function _getDirLocal($path, $noParent=false)
	{
		if($this->_isDirLocal($path))
		{
			$handle = openDir($path);
			while (false !== ($file = readdir($handle)))
			{
				if($noParent=="NO_PARENT")
				{
					if($file != ".." && $file != ".")
						$tree[] = $file;
				}
				elseif($noParent=="NO_ROOT")
				{
					if($file != ".")
						$tree[] = $file;
				}
				else $tree[] = $file;
			}
			closeDir($handle);
		}
		return (is_array($tree) ? $tree : array());
	}

	function deleteDirectoryLocal($root, $path)
	{
		$root	 = $this->_fixPath($root);
		$path	 = $this->_fixPath($path);
		if($this->_isDirLocal($root . $this->dirDelimiter . $path))
		{
			$arrayFiles = $this->_getDirLocal($root . $this->dirDelimiter . $path, "NO_PARENT");
			if(is_array($arrayFiles) && count($arrayFiles))
			{
				foreach($arrayFiles as $key => $file)
				{
					if($this->_isDirLocal($root . $this->dirDelimiter . $path . $this->dirDelimiter . $file)) $this->deleteDirectoryLocal($root, $path . $this->dirDelimiter . $file);
					else unlink($root . $this->dirDelimiter . $path . $this->dirDelimiter . $file);
				}
			}
			rmdir($root . $this->dirDelimiter . $path);
		}
	}

	function userIsLocked()
	{
		$current = ftp_pwd($this->Connection);
		$up = ftp_cdup($this->Connection);
		if(!$up) return true;
		elseif(ftp_pwd($this->Connection) == $current) return true;
		else
		{
			ftp_chdir($this->Connection, $current);
			return false;
		}
	}

	function changeDir($targetRemote) { return (($this->Connection || $this->connect()) ? @ftp_chdir($this->Connection, $targetRemote) : false); }

	function _fixPath($path) { return (substr($path,-1,1)==$this->dirDelimiter) ? substr($path,0,-1) : $path; }

	function _fixPathFull($path)
	{
		if(substr($path,-1,1)==$this->dirDelimiter) $path = substr($path,0,-1);
		if(substr($path,0,1)==$this->dirDelimiter) $path = substr($path, 1, strlen($path)-1);
		return $path;
	}

	/** logging functions **/
	function openMyLog() { if($this->_debug) $this->logfileHandle = fopen($this->logfile, "a+"); }

	function _log($message) { if($this->_debug) fputs($this->logfileHandle, $message."\n"); }

	function trace($functionName, $args) { if($this->_debug) $this->_log(sprintf("%s  %s(%s)", date("Y-m-d H:i:s"), $functionName, join($args, ", "))); }

	function getNonBlocking() { return $this->nonBlocking; }
	function setNonBlocking($blocking) { $this->nonBlocking = ($blocking ? true : false); }

	function getOverwrite() { return $this->overwrite; }
	function setOverwrite($overwrite) { return ($this->overwrite = ($overwrite ? true : false)); }
	function getTransferMode() { return $this->TransferMode ? $this->TransferMode : false; }
	function setTransferMode($mode) { $this->TransferMode = in_array($mode, $this->validTransferModes) ? $mode : FTP_BINARY; }
	function getHost() { return $this->Host ? $this->Host : false; }
	function setHost($host) { $this->Host = $host; }
	function getPort() { return $this->Port ? $this->Port : false; }
	function setPort($port) { $this->Port = $port; }
	function getUser() { return $this->User ? $this->User : false; }
	function setUser($user) { $this->User = $user; }
	function getPass() { return $this->Pass ? $this->Pass : false; }
	function setPass($pass) { $this->Pass = $pass; }
	function getMaxConnectionAttempts() { return $this->MaxConnectionAttempts; }
	function setMaxConnectionAttempts($attemps) { return $this->MaxConnectionAttempts = $attemps; }
}
?>
