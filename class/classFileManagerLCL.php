<?

class FileManagerLCL extends BaseObjectAdmin
{
	var	$dirDelimiter	= "/";
	var	$root			= ".";
	var	$parent			= "..";

	function FileManagerLCL()
	{
		
	}

	function copyFile($source, $target)
	{
		return copy($source, $target);
	}

	function copyDirectory($srcRoot, $targetRoot, $dir, $local_remote, $recursive = true)
	{
		$srcRoot	= $this->_fixPath($srcRoot);
		$targetRoot	= $this->_fixPath($targetRoot);
		$dir		= $this->_fixPath($dir);
		$this->deleteDirectory($targetRoot, $dir);
		$this->_copy($srcRoot . $this->dirDelimiter . $dir, $targetRoot . $this->dirDelimiter . $dir);
	}

	function _copy($source, $target)
	{
		if(!mkdir($this->_fixPath($target), 0777)) die('cannot create local dir &quot;'.$target.'&quot;! Cannot copy dir &quot;'.$source.'&quot;!');
		if(count($dirList = $this->_getDir($source)))
		{
			while(list($key, $itemname) = each($dirList))
			{
				if($itemname != $this->parent && $itemname != $this->root)
				{
					if($this->_isDir($this->_fixPath($source).$this->dirDelimiter.$itemname))
						$this->_copy($this->_fixPath($source).$this->dirDelimiter.$itemname, $this->_fixPath($target).$this->dirDelimiter.$itemname);
					else
						copy($this->_fixPath($source).$this->dirDelimiter.$itemname, $this->_fixPath($target).$this->dirDelimiter.$itemname);
				}
			}
		}
	}

	function deleteDirectory($root, $path)
	{
		$root 		= $this->_fixPath($root);
		$path		= $this->_fixPath($path);
		if($this->_isDir($root . $this->dirDelimiter . $path))
		{
			$arrayFiles = $this->_getDir($root . $this->dirDelimiter . $path, "NO_PARENT");
			if(is_array($arrayFiles) && count($arrayFiles))
			{
				foreach($arrayFiles as $key => $file)
				{
					if($this->_isDir($root . $this->dirDelimiter . $path . $this->dirDelimiter . $file)) $this->deleteDirectory($root, $path . $this->dirDelimiter . $file);
					else unlink($root . $this->dirDelimiter . $path . $this->dirDelimiter . $file);
				}
			}
			rmdir($root . $this->dirDelimiter . $path);
		}
	}

	function getDirectoryStructure($directory)
	{
		$structure = array();
		if($this->_isDir($directory))
		{
			$dir = $this->_getDir($directory, "NO_PARENT");
			if(is_array(@$dir) && count($dir))
			{
				foreach($dir as $key => $item)
					if($this->_isDir($directory . $this->dirDelimiter . $item)) $structure[$item] = $this->getDirectoryStructure($directory . $this->dirDelimiter . $item);
			}
		}
		return $structure;
	}

	function _readDirCopyData($root, $path, $recursive=false)
	{
		$arrayFiles = array();
		$root = $this->_fixPath($root); $path = $this->_fixPath($path);
		if(is_dir($root . $this->dirDelimiter . $path) && $path != $this->root && $path != $this->parent)
		{
			$dir = array();
			$d = dir($root . $this->dirDelimiter .$path);
			while(false !== ($file = $d->read()))
			{
				if($file != $this->root && $file != $this->parent)
				{
					if(($isDir = $this->_isDir($root . $this->dirDelimiter . $path . $this->dirDelimiter . $file)) && $recursive)
						$arrayFiles	= array_merge_recursive($arrayFiles, $this->_readDirCopyData($root, $path . $this->dirDelimiter . $file, $recursive));
					elseif($isDir && !$recursive) continue;
					else
					{
						$arrayFiles['fileName'][]						= $path . $this->dirDelimiter . $file;
						$arrayFiles['fileMTime'][$path . $this->dirDelimiter . $file]	= filemtime($root . $this->dirDelimiter . $path . $this->dirDelimiter . $file);
					}
				}
			}
		}
		return $arrayFiles;
	}

	function _createTarBall($archiveName, $arrayDirs)
	{
		chdir($this->getEnvironmentVariable("docRoot") . "/");
		$execStr = "tar -czf $archiveName ";
		foreach($arrayDirs as $key => $value) $execStr .= $value . " ";
		exec($execStr, $output, $return);
		return ((@$return > 0) ? false : true);
	}

	function _unTarBall($archiveName, $destination)
	{
		chdir($this->getEnvironmentVariable("docRoot") . "/..");
		$execStr = "tar --same-owner -xpzf $archiveName -C $destination";
		exec($execStr, $output, $return);
		return ((@$return > 0) ? false : true);
	}

	function _getDir($path, $noParent=false)
	{
		if($this->_isDir($path))
		{
			$handle = openDir($path);
			while (false !== ($file = readdir($handle)))
			{
				if($noParent=="NO_PARENT")
				{
					if($file != $this->parent && $file != $this->root)
						$tree[] = $file;
				}
				elseif($noParent=="NO_ROOT")
				{
					if($file != $this->root)
						$tree[] = $file;
				}
				else
						$tree[] = $file;
			}
			closeDir($handle);
		}
		return (is_array(@$tree) ? $tree : array());
	}

	function makeDir($dir, $mode=false) { return ($this->_isDir($dir) ? true : ($mode ? mkdir($dir, $mode) : mkdir($dir))); }

	function _isDir($path) { return file_exists($this->_fixPath($path) . $this->dirDelimiter . $this->parent) ? 1 : 0; }
	function _isFile($file) { return is_file($this->_fixPath($file)); }

	function _fixPath($path) { return (substr($path,-1,1)==$this->dirDelimiter) ? substr($path,0,-1) : $path; }
}


?>