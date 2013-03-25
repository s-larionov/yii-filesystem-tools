<?php

class FSDirectory {
	/** @var string */
	protected $path;

	/**
	 * @param string $path
	 */
	public function __construct($path) {
		$this->path = (string) $path;
	}

	/**
	 * @return array()
	 */
	public function getSubdirectories() {
		return scandir($this->getPath());
	}

	/** @return bool */
	public function isReadable() {
		return is_readable($this->getPath());
	}

	/** @return bool */
	public function isWritable() {
		return is_writable($this->getPath()) || !$this->exists();
	}

	/** @return bool */
	public function exists() {
		return file_exists($this->getPath());
	}

	/** @return string */
	public function getPath() {
		return $this->path;
	}

	/** @return boolean */
	public function delete() {
		return @unlink($this->getPath());
	}

	/**
	 * @param int $mode
	 * @return bool
	 */
	public function chmod($mode = 0777) {
		return @chmod($this->getPath(), $mode);
	}

	public static function create($path, $recursive = true, $mode = 0777) {
		$directory = new self($path);
		if (!$directory->exists()) {
			return mkdir($path, $mode, $recursive);
		}
		return true;
	}
}

class FSDirectoryException extends CException {

}
