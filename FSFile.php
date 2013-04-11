<?php

class FSFile {
	/** @var string */
	protected $filename;

	/** @var string */
	protected $content = null;

	/** @var array */
	protected $attributes = array();

	/** @var int|null */
	protected $modifyTime = null;

	/**
	 * @param string $filename
	 */
	public function __construct($filename) {
		$this->filename = (string) $filename;
	}

	public static function isAcceptableFilename($filename) {
		return preg_match("/^[^\\/?*:;{}\\\\]+(?:\\.[^\\/?*:;{}\\\\]{3})?$/", $filename);
	}

	/**
	 * @return boolean|string
	 */
	public function getContent() {
		if ($this->content === null) {
			$this->load();
		}
		return $this->content;
	}

	/**
	 * @param string $content
	 * @param boolean $autoSave
	 * @return FSFile
	 */
	public function setContent($content, $autoSave = true) {
		$this->content = (string) $content;
		if ($autoSave) {
			$this->save();
		}
		return $this;
	}

	/**
	 * @throws CException
	 * @return boolean
	 */
	public function save() {
		if (!$this->isWritable() ) {
			throw new CException("File {$this->getFilename()} is not writable");
		}
		return file_put_contents($this->getFilename(), $this->getContent()) !== false;
	}

	/**
	 * @throws CException
	 * @return FSFile
	 */
	public function load() {
		if (!$this->isReadable()) {
			throw new CException("File {$this->getFilename()} is not readable");
		}
		$this->content = file_get_contents($this->getFilename());
		return $this;
	}

	/** @return bool */
	public function isReadable() {
		return is_readable($this->getFilename());
	}

	/** @return bool */
	public function isWritable() {
		return is_writable($this->getFilename()) || !$this->exists();
	}

	/** @return bool */
	public function exists() {
		return file_exists($this->getFilename());
	}

	/** @return string */
	public function getFilename() {
		return $this->filename;
	}

	/** @return string */
	public function getMimeType() {
		return mime_content_type($this->getFilename());
	}

	/** @return boolean */
	public function delete() {
		return @unlink($this->getFilename());
	}

	/** @return int */
	public function getMTime() {
		if ($this->modifyTime === null) {
			$this->modifyTime = $this->isReadable()? filemtime($this->getFilename()): 0;
		}
		return $this->modifyTime;
	}
}

class FSFileException extends CException {

}
