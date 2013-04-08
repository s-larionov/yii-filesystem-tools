<?php

require_once 'FSFile.php';

class FSImage extends FSFile {
	const RESIZE_METHOD_CROP       = 1;
	const RESIZE_METHOD_FILL       = 2;
	const RESIZE_METHOD_FIT        = 3;
	const RESIZE_METHOD_FIT_HEIGHT = 4;
	const RESIZE_METHOD_FIT_WIDTH  = 5;
	const RESIZE_METHOD_STRETCH    = 6;

	const RESIZE_WITH_FIELDS    = 1;
	const RESIZE_WITHOUT_FIELDS = 2;

	const DEFAULT_BACKGROUND_COLOR = 'FF0000';
	const DEFAULT_QUALITY_JPEG     = 90;
	const DEFAULT_QUALITY_PNG      = 9;

	/** @var resource */
	protected $imageHandle = null;

	/** @var int */
	protected $imageType = null;

	/**  * @param string $filename */
	public function __construct($filename) {
		parent::__construct($filename);
	}

	public static function createBySource($content) {
		$image = new self(tempnam(Yii::app()->runtimePath, 'image-'));
		$image->setContent($content, false);
		return $image;
	}

	/**
	 * Create a GD image resource from file (JPEG, PNG, GIF support).
	 *
	 * @throws FSFileException
	 * @throws FSImageException
	 * @return resource            GD image resource
	 */
	public function load() {
		if (!$this->exists()) {
			throw new FSFileException("File {$this->getFilename()} doesn't exists.");
		}
		if (!$this->isReadable()) {
			throw new FSFileException("File {$this->getFilename()} doesn't readable.");
		}

		switch ($this->getImageType()) {
			case IMAGETYPE_JPEG:
			case IMAGETYPE_JPEG2000:
				$this->imageHandle = imagecreatefromjpeg($this->getFilename());
				break;
			case IMAGETYPE_PNG:
				$this->imageHandle = imagecreatefrompng($this->getFilename());
				break;
			case IMAGETYPE_GIF:
				$this->imageHandle = imagecreatefromgif($this->getFilename());
				break;
			default:
				throw new FSImageException('Unsupported image type');
		}
		imagesavealpha($this->imageHandle, true);
	}

	/**  @return int */
	public function getImageType() {
		if ($this->imageType === null) {
			if (!empty($this->content)) {
				list(, , $this->imageType) = getimagesizefromstring($this->content);
			} else {
				// determine image format
				list(, , $this->imageType) = getimagesize($this->getFilename());
			}
		}
		return $this->imageType;
	}

	/**
	 * @return string
	 */
	public function getMimeType() {
		return image_type_to_mime_type($this->getImageType());
	}

	/**
	 * @return string
	 */
	public function getContent() {
		switch ($this->getImageType()) {
			case IMAGETYPE_PNG:
				return $this->toPng();
			case IMAGETYPE_GIF:
				return $this->toGif();
			case IMAGETYPE_JPEG:
			case IMAGETYPE_JPEG2000:
			default:
				return $this->toJpeg();
		}
	}

	/**
	 * @return string
	 */
	public function getExtension() {
		switch ($this->getImageType()) {
			case IMAGETYPE_PNG:
				return 'png';
			case IMAGETYPE_GIF:
				return 'gif';
			case IMAGETYPE_JPEG:
			case IMAGETYPE_JPEG2000:
			default:
				return 'jpg';
		}
	}

	/**
	 * @param int $quality
	 * @return string
	 */
	public function toJpeg($quality = self::DEFAULT_QUALITY_JPEG) {
		return $this->_toJpeg($this->getHandle(), $quality);
	}

	/**
	 * @param int $quality
	 * @return string
	 */
	public function toPng($quality = self::DEFAULT_QUALITY_PNG) {
		return $this->_toPng($this->getHandle(), $quality);
	}

	/**
	 * @return string
	 */
	public function toGif() {
		return $this->_toGif($this->getHandle());
	}

	/**
	 * @param resource $handle
	 * @param int $quality
	 * @throws FSImageException
	 * @return string
	 */
	protected function _toPng($handle, $quality = self::DEFAULT_QUALITY_PNG) {
		if (!is_resource($handle)) {
			throw new FSImageException('Resource is not valid');
		}
		ob_start();
		imagepng($handle, null, $quality);
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * @param resource $handle
	 * @return string
	 */
	protected function _toGif($handle) {
		ob_start();
		imagegif($handle);
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * @param resource $handle
	 * @param int $quality
	 * @throws FSImageException
	 * @return string
	 */
	protected function _toJpeg($handle, $quality = self::DEFAULT_QUALITY_JPEG) {
		if (!is_resource($handle)) {
			throw new FSImageException('Resource is not valid');
		}
		imageinterlace($handle, 1);
		ob_start();
		imagejpeg($handle, null, (int) $quality);
		$content = ob_get_contents();
		ob_end_clean();
		return $content;
	}

	/**
	 * Resize image
	 *
	 * @param int|null $width             Width of new image in pixels
	 * @param int|null $height            Height of new image in pixels
	 * @param int $method                 Method of resize image. @
	 * @param int $fields                 self::RESIZE_WITHOUT_FIELDS or self::RESIZE_WITH_FIELDS
	 * @param float $horizontalAlign      Horizontal position result image in original image (from 0 to 1)
	 * @param float $verticalAlign        Vertical position result image in original image (from 0 to 1)
	 * @param string $backgroundColor     Color in HEX format (XXXXXX, ex. FF0000 for red color).
	 * @param bool $withoutLossOfQuality  If result size great than source, then if true - don't resize
	 *
	 * @throws FSImageException
	 * @return FSImage
	 */
	public function resize($width = null, $height = null,
						   $method = self::RESIZE_METHOD_FILL, $fields = self::RESIZE_WITHOUT_FIELDS,
						   $horizontalAlign = 0.5, $verticalAlign = 0.5,
						   $backgroundColor = self::DEFAULT_BACKGROUND_COLOR,
						   $withoutLossOfQuality = true) {
		$sourceImage = $this->getHandle();

		$sourceWidth  = imagesx($sourceImage);
		$sourceHeight = imagesy($sourceImage);

		if ($width === null && $height !== null) {
			// Если указана только высота, то вычисляем ширину
			$height = (int) $height;
			$width  = (int) ($height * $sourceWidth / $sourceHeight);
		} else {
			if ($height === null && $width !== null) {
				// Если указана только ширина, то вычисляем высоту
				$width  = (int) $width;
				$height = (int) ($width * $sourceHeight / $sourceWidth);
			} else {
				if ($width !== null && $height !== null) {
					// Если оба параметра переданы, то убеждаемся что это числа
					$width  = (int) $width;
					$height = (int) $height;
				} else {
					// Если ничего не указано, то прекращаем обработку
					$width  = (int) $width;
					$height = (int) $height;
				}
			}
		}

		// проверяем что размеры выходного изображения не отрицательные
		if ($width <= 0 || $height <= 0) {
			throw new FSImageException('Result image size is zero or negative');
		}

		// если новое изображение больше чем оригинал и указан
		// параметр $withoutLossOfQuality = true, прекращаем обработку
		if ($withoutLossOfQuality && $width > $sourceWidth && $height > $sourceHeight) {
			return $this;
		}

		// horizontalAlign и verticalAlign должны быть в пределах от 0 до 1
		$horizontalAlign = max(0, min(1, (float) $horizontalAlign));
		$verticalAlign   = max(0, min(1, (float) $verticalAlign));

		// непосредственные размеры копируемого изображения на оригинальном холсте
		// и положение копируемого изображения
		$sourceCopyWidth  = $sourceWidth;
		$sourceCopyHeight = $sourceHeight;
		$sourceCopyX      = 0;
		$sourceCopyY      = 0;

		// непосредственные размеры копируемого изображения на новом холсте
		// и положение копируемого изображения
		$resultCopyWidth  = $width;
		$resultCopyHeight = $height;
		$resultCopyX      = 0;
		$resultCopyY      = 0;

		// коэффициенты соотношения сторон
		$sourceCoefficient = $sourceWidth / $sourceHeight;
		$coefficient       = $width / $height;

		switch ($method) {
			case self::RESIZE_METHOD_STRETCH:
				break;
			case self::RESIZE_METHOD_FILL:
				if ($sourceCoefficient > $coefficient) {
					$sourceCopyWidth = floor($sourceHeight * $coefficient);
					$sourceCopyX     = -floor(($sourceCopyWidth - $sourceWidth) * $horizontalAlign);
				} else {
					$sourceCopyHeight = floor($sourceWidth / $coefficient);
					$sourceCopyY      = -floor(($sourceCopyHeight - $sourceHeight) * $verticalAlign);
				}
				break;
			case self::RESIZE_METHOD_FIT:
				if ($fields == self::RESIZE_WITH_FIELDS) {
					if ($sourceCoefficient > $coefficient) {
						$resultCopyHeight = floor($width / $sourceCoefficient);
						$resultCopyY      = -floor(($resultCopyHeight - $height) * $verticalAlign);
					} else {
						$resultCopyWidth = floor($height * $sourceCoefficient);
						$resultCopyX     = floor(($width - $resultCopyWidth) * $horizontalAlign);
					}
				} else {
					if ($sourceCoefficient > $coefficient) {
						$height = $resultCopyHeight = floor($width / $sourceCoefficient);
					} else {
						$width = $resultCopyWidth = floor($height * $sourceCoefficient);
					}
				}
				break;
			case self::RESIZE_METHOD_FIT_HEIGHT:
				if ($sourceCoefficient < $coefficient) {
					$resultCopyWidth = $width = floor($resultCopyHeight * $sourceCoefficient);
				} else {
					$resultCopyWidth = floor($height * $sourceCoefficient);
					$resultCopyX     = floor(($width - $resultCopyWidth) * $horizontalAlign);
				}
				break;
			case self::RESIZE_METHOD_FIT_WIDTH:
				if ($sourceCoefficient > $coefficient) {
					$resultCopyHeight = $height = floor($resultCopyWidth / $sourceCoefficient);
				} else {
					$resultCopyHeight = floor($width / $sourceCoefficient);
					$resultCopyY      = -floor(($resultCopyHeight - $height) * $verticalAlign);
				}
				break;
			case self::RESIZE_METHOD_CROP:
			default:
				$sourceCopyWidth  = $width;
				$sourceCopyHeight = $height;
		}

		// Create the target image
		$targetImage = imagecreatetruecolor($width, $height);
		if (!is_resource($targetImage)) {
			throw new FSImageException('Cannot initialize new GD image stream');
		}

		// save alpha channel
		imagealphablending($targetImage, false);
		imagesavealpha($targetImage, true);

		// заливаем новую картинку, если она должна быть с полями
		if ($fields == self::RESIZE_WITH_FIELDS) {
			$colorRgb = $this->rgbToArray($backgroundColor);
			$color    = imagecolorallocate($targetImage, $colorRgb[0], $colorRgb[1], $colorRgb[2]);
			imagefill($targetImage, 1, 1, $color);
		}


		// Copy the source image to the target image
		if ($method == self::RESIZE_METHOD_CROP) {
			$result = imagecopy($targetImage, $sourceImage, $resultCopyX, $resultCopyY, $sourceCopyX, $sourceCopyY, $sourceCopyWidth, $sourceCopyHeight);
		} else {
			$result = imagecopyresampled($targetImage, $sourceImage, $resultCopyX, $resultCopyY, $sourceCopyX, $sourceCopyY, $resultCopyWidth, $resultCopyHeight, $sourceCopyWidth, $sourceCopyHeight);
		}
		if (!$result) {
			throw new FSImageException('Cannot resize image');
		}

		// Free a memory from the source image
		imagedestroy($sourceImage);

		$this->imageHandle = $targetImage;

		return $this;
	}

	/**
	 * Rescale image
	 * @param float|int $scale
	 */
	public function scale($scale = 1) {
		$scale       = (float) $scale;
		$imageHandle = $this->getHandle();

		$this->resize(imagesx($imageHandle) * $scale, imagesy($imageHandle) * $scale, self::RESIZE_METHOD_CROP);
	}

	public function setContent($content, $autoSave = true) {
		$result            = parent::setContent($content, $autoSave);
		$this->imageHandle = imagecreatefromstring($content);

		return $result;
	}


	/**
	 * Get handle for image
	 * @return resource
	 */
	public function getHandle() {
		if ($this->imageHandle === null) {
			$this->load();
		}
		return $this->imageHandle;
	}

	/**
	 * Convert color from hex in XXXXXX (eg. FFFFFF, 000000, FF0000) to array(R, G, B)
	 * of integers (0-255).
	 *
	 * @param string $rgb hex in XXXXXX (eg. FFFFFF, 000000, FF0000)
	 * @return array; array(R, G, B) of integers (0-255)
	 * @author: Yetty
	 */
	protected function rgbToArray($rgb) {
		return array(
			base_convert(substr($rgb, 0, 2), 16, 10),
			base_convert(substr($rgb, 2, 2), 16, 10),
			base_convert(substr($rgb, 4, 2), 16, 10),
		);
	}

	/**
	 * Get slice of image by row and column index
	 *
	 * @param int $imageWidth    Width of result image
	 * @param int $imageHeight   Height of result image
	 * @param int $column        Column index (numeration begin from zero)
	 * @param int $row           Row index (numeration begin from zero)
	 * @param int $columnsCount  Count columns
	 * @param int $rowsCount     Count rows
	 * @param int $fields        self::RESIZE_WITHOUT_FIELDS or self::RESIZE_WITH_FIELDS
	 *
	 * @throws FSImageException
	 * @return resource
	 */
	public function getSliceHandle($imageWidth, $imageHeight,
								   $column, $row,
								   $columnsCount = 5, $rowsCount = 5,
								   $fields = self::RESIZE_WITHOUT_FIELDS) {
		if ($column >= $columnsCount || $column < 0) {
			throw new FSImageException('Column must in range 0-' . ($columnsCount - 1));
		}
		if ($row >= $rowsCount || $row < 0) {
			throw new FSImageException('Row must in range 0-' . ($rowsCount - 1));
		}

		if (self::RESIZE_WITHOUT_FIELDS) {
			$sliceWidth  = floor($imageWidth / $columnsCount);
			$sliceHeight = floor($imageHeight / $rowsCount);
		} else {
			$sliceWidth  = ceil($imageWidth / $columnsCount);
			$sliceHeight = ceil($imageHeight / $rowsCount);
		}

		$imageWidth  = (int) $sliceWidth * $columnsCount;
		$imageHeight = (int) $sliceHeight * $rowsCount;

		$this->resize($imageWidth, $imageHeight, self::RESIZE_METHOD_FILL, $fields);

		$slice = imagecreatetruecolor($sliceWidth, $sliceHeight);
		imagesavealpha($slice, true);
		imagecopy($slice, $this->getHandle(), 0, 0, (int) $column * $sliceWidth, (int) $row * $sliceHeight, $sliceWidth, $sliceHeight);
		return $slice;
	}

	/**
	 * Get slice of image by row and column index
	 *
	 * @param int $imageWidth    Width of result image
	 * @param int $imageHeight   Height of result image
	 * @param int $column        Column index (numeration begin from zero)
	 * @param int $row           Row index (numeration begin from zero)
	 * @param int $columnsCount  Count columns
	 * @param int $rowsCount     Count rows
	 * @param int $fields        self::RESIZE_WITHOUT_FIELDS or self::RESIZE_WITH_FIELDS
	 *
	 * @throws FSImageException
	 * @return string
	 */
	public function getSlice($imageWidth, $imageHeight,
							 $column, $row,
							 $columnsCount = 5, $rowsCount = 5,
							 $fields = self::RESIZE_WITHOUT_FIELDS) {
		$handle = $this->getSliceHandle($imageWidth, $imageHeight, $column, $row, $columnsCount, $rowsCount, $fields);

		switch ($this->getImageType()) {
			case IMAGETYPE_JPEG:
			case IMAGETYPE_JPEG2000:
				return $this->_toJpeg($handle);
			case IMAGETYPE_PNG:
				return $this->_toPng($handle);
			case IMAGETYPE_GIF:
				return $this->_toGif($handle);
		}

		throw new FSImageException('Unsupported image type');
	}
}

class FSImageException extends CException {

}
