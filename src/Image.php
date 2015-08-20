<?php
namespace Imagecow;

use Imagecow\Utils\Dimmensions;

/**
 * Abstract core class extended by the available libraries (GD, Imagick)
 */
class Image
{
    const LIB_GD = 'Gd';
    const LIB_IMAGICK = 'Imagick';

    const CROP_ENTROPY = 'Entropy';
    const CROP_BALANCED = 'Balanced';

    protected $image;
    protected $filename;

    /**
     * Static function to create a new Imagecow instance from an image file or string
     *
     * @param string $image   The name of the image file or binary string
     * @param string $library The name of the image library to use (Gd or Imagick). If it's not defined, detects automatically the library to use.
     *
     * @return Image The Imagecow instance
     */
    public static function create($image, $library = null)
    {
        //check if it's a binary string
        if (!ctype_print($image)) {
            return static::createFromString($image, $library);
        }

        return static::createFromFile($image, $library);
    }

    /**
     * Static function to create a new Imagecow instance from an image file
     *
     * @param string $image   The path of the file
     * @param string $library The name of the image library to use (Gd or Imagick). If it's not defined, detects automatically the library to use.
     *
     * @return Image
     */
    public static function createFromFile($image, $library = null)
    {
        $class = self::getLibraryClass($library);

        return new static($class::createFromFile($image), $image);
    }

    /**
     * Static function to create a new Imagecow instance from a binary string
     *
     * @param string $string  The string of the image
     * @param string $library The name of the image library to use (Gd or Imagick). If it's not defined, detects automatically the library to use.
     *
     * @return Image
     */
    public static function createFromString($string, $library = null)
    {
        $class = self::getLibraryClass($library);

        return new static($class::createFromString($string));
    }

    /**
     * Static function to filter the transform operations according to client properties
     * Useful to generate responsive images
     *
     * @param string $client_properties The cookie value generated by Imagecow.js scripts with the client dimmensions.
     * @param string $operations        The operations to transform the image
     *
     * @return string The operations that matches with the client properties.
     */
    public static function getResponsiveOperations($client_properties, $operations)
    {
        list($width, $height, $speed) = explode(',', $client_properties);

        $width = intval($width);
        $height = intval($height);
        $transform = array();

        foreach (explode(';', $operations) as $operation) {
            if (!preg_match('/^(.+):(.+)$/', $operation, $matches)) {
                if (!empty($operation)) {
                    $transform[] = $operation;
                }
                continue;
            }

            if (self::clientMatch($matches[1], $width, $height, $speed)) {
                $transform[] = $matches[2];
            }
        }

        return implode('|', $transform);
    }

    /**
     * Check whether the client match with a selector
     *
     * @param string  $selector The operations selector
     * @param integer $width    The client width
     * @param integer $height   The client height
     * @param string  $speed    The client speed
     *
     * @return boolean
     */
    private static function clientMatch($selector, $width, $height, $speed)
    {
        foreach (explode(',', $selector) as $rule) {
            $rule = explode('=', $rule, 2);
            $value = intval($rule[1]);

            switch ($rule[0]) {
                case 'max-width':
                    if ($width > $value) {
                        return false;
                    }
                    break;

                case 'min-width':
                    if ($width < $value) {
                        return false;
                    }
                    break;

                case 'width':
                    if ($width != $value) {
                        return false;
                    }
                    break;

                case 'max-height':
                    if ($height > $value) {
                        return false;
                    }
                    break;

                case 'min-height':
                    if ($height < $value) {
                        return false;
                    }
                    break;

                case 'height':
                    if ($height != $value) {
                        return false;
                    }
                    break;
            }
        }

        return true;
    }

    /**
     * Constructor.
     *
     * @param Libs\LibInterface $image
     * @param string            $filename Original filename (used to overwrite)
     */
    public function __construct(Libs\LibInterface $image, $filename = null)
    {
        $this->image = $image;
        $this->filename = $filename;

        if ($this->isAnimatedGif()) {
            $this->image->setAnimated(true);
        }
    }

    public function getImage()
    {
        return $this->image->getImage();
    }

    public function setImage($image)
    {
        return $this->image->setImage($image);
    }

    /**
     * Inverts the image vertically
     *
     * @return self
     */
    public function flip()
    {
        $this->image->flip();

        return $this;
    }

    /**
     * Inverts the image horizontally
     *
     * @return self
     */
    public function flop()
    {
        $this->image->flop();

        return $this;
    }

    /**
     * Saves the image in a file
     *
     * @param string $filename Name of the file where the image will be saved. If it's not defined, The original file will be overwritten.
     *
     * @return self
     */
    public function save($filename = null)
    {
        $this->image->save($filename ?: $this->filename);

        return $this;
    }

    /**
     * Gets the image data in a string
     *
     * @return string The image data
     */
    public function getString()
    {
        return $this->image->getString();
    }

    /**
     * Gets the mime type of the image
     *
     * @return string The mime type
     */
    public function getMimeType()
    {
        return $this->image->getMimeType();
    }

    /**
     * Gets the width of the image
     *
     * @return integer The width in pixels
     */
    public function getWidth()
    {
        return $this->image->getWidth();
    }

    /**
     * Gets the height of the image
     *
     * @return integer The height in pixels
     */
    public function getHeight()
    {
        return $this->image->getHeight();
    }

    /**
     * Converts the image to other format
     *
     * @param string $format The new format: png, jpg, gif
     *
     * @return self
     */
    public function format($format)
    {
        $this->image->format($format);

        return $this;
    }

    /**
     * Resizes the image maintaining the proportion (A 800x600 image resized to 400x400 becomes to 400x300)
     *
     * @param integer|string $width   The max width of the image. It can be a number (pixels) or percentaje
     * @param integer|string $height  The max height of the image. It can be a number (pixels) or percentaje
     * @param boolean|null   $enlarge
     * @param boolean        $cover
     *
     * @return self
     */
    public function resize($width, $height = 0, $enlarge = false, $cover = false)
    {
        $imageWidth = $this->getWidth();
        $imageHeight = $this->getHeight();

        $width = Dimmensions::getIntegerValue($width, $imageWidth);
        $height = Dimmensions::getIntegerValue($height, $imageHeight);

        list($width, $height) = Dimmensions::getResizeDimmensions($imageWidth, $imageHeight, $width, $height, $cover);

        if (($width === $imageWidth) || (!$enlarge && $width > $imageWidth)) {
            return $this;
        }

        $this->image->resize($width, $height);

        return $this;
    }

    /**
     * Crops the image
     *
     * @param integer|string $width  The new width of the image. It can be a number (pixels) or percentaje
     * @param integer|string $height The new height of the image. It can be a number (pixels) or percentaje
     * @param integer|string $x      The "x" position to crop. It can be number (pixels), percentaje, [left, center, right] or one of the Image::CROP_* constants
     * @param integer|string $y      The "y" position to crop. It can be number (pixels), percentaje or [top, middle, bottom]
     *
     * @return self
     */
    public function crop($width, $height, $x = 'center', $y = 'middle')
    {
        $imageWidth = $this->getWidth();
        $imageHeight = $this->getHeight();

        $width = Dimmensions::getIntegerValue($width, $imageWidth);
        $height = Dimmensions::getIntegerValue($height, $imageHeight);

        switch ($x) {
            case Image::CROP_BALANCED:
            case Image::CROP_ENTROPY:
                list($x, $y) = $this->image->getCropOffsets($width, $height, $x);
                break;
        }

        $x = Dimmensions::getPositionValue($x, $width, $imageWidth);
        $y = Dimmensions::getPositionValue($y, $height, $imageHeight);

        $this->image->crop($width, $height, $x, $y);

        return $this;
    }

    /**
     * Adjust the image to the given dimmensions. Resizes and crops the image maintaining the proportions.
     *
     * @param integer|string $width   The new width in number (pixels) or percentaje
     * @param integer|string $height  The new height in number (pixels) or percentaje
     * @param integer|string $x       The "x" position to crop. It can be number (pixels), percentaje, [left, center, right] or one of the Image::CROP_* constants
     * @param integer|string $y       The "y" position to crop. It can be number (pixels), percentaje or [top, middle, bottom]
     * @param boolean        $enlarge
     *
     * @return self
     */
    public function resizeCrop($width, $height, $x = 'center', $y = 'middle', $enlarge = false)
    {
        $this->resize($width, $height, $enlarge, true);
        $this->crop($width, $height, $x, $y);

        return $this;
    }

    /**
     * Rotates the image
     *
     * @param integer $angle Rotation angle in degrees (anticlockwise)
     *
     * @return self
     */
    public function rotate($angle)
    {
        if (($angle = intval($angle)) !== 0) {
            $this->image->rotate($angle);
        }

        return $this;
    }

    /**
     * Define the image compression quality for jpg images
     *
     * @param integer $quality The quality (from 0 to 100)
     *
     * @return self
     */
    public function setCompressionQuality($quality)
    {
        $quality = intval($quality);

        if ($quality < 0) {
            $quality = 0;
        } elseif ($quality > 100) {
            $quality = 100;
        }

        $this->image->setCompressionQuality($quality);

        return $this;
    }

    /**
     * Set a default background color used to fill in some transformation functions
     *
     * @param array $background The color in rgb, for example: array(0,127,34)
     *
     * @return self
     */
    public function setBackground(array $background)
    {
        $this->image->setBackground($background);

        return $this;
    }

    /**
     * Reads the EXIF data from a JPEG and returns an associative array
     * (requires the exif PHP extension enabled)
     *
     * @param null|string $key
     *
     * @return null|array
     */
    public function getExifData($key = null)
    {
        if ($this->filename !== null && ($this->getMimeType() === 'image/jpeg')) {
            $exif = exif_read_data($this->filename);

            if ($key !== null) {
                return isset($exif[$key]) ? $exif[$key] : null;
            }

            return $exif;
        }
    }

    /**
     * Transform the image executing various operations of crop, resize, resizeCrop and format
     *
     * @param string $operations The string with all operations separated by "|".
     *
     * @return self
     */
    public function transform($operations = '')
    {
        if (!$operations) {
            return $this;
        }

        $operations = self::parseOperations($operations);

        foreach ($operations as $operation) {
            switch (strtolower($operation['function'])) {
                case 'crop':
                case 'resizecrop':
                    if (isset($operation['params'][2])) {
                        switch ($operation['params'][2]) {
                            case 'CROP_ENTROPY':
                                $operation['params'][2] = Image::CROP_ENTROPY;
                                break;

                            case 'CROP_BALANCED':
                                $operation['params'][2] = Image::CROP_BALANCED;
                                break;
                        }
                    }
                    break;
            }

            call_user_func_array(array($this, $operation['function']), $operation['params']);
        }

        return $this;
    }

    /**
     * Send the HTTP header with the content-type, output the image data and die.
     */
    public function show()
    {
        if (($string = $this->getString()) && ($mimetype = $this->getMimeType())) {
            header('Content-Type: '.$mimetype);
            die($string);
        }
    }

    /**
     * Auto-rotate the image according with its exif data
     * Taken from: http://php.net/manual/en/function.exif-read-data.php#76964
     *
     * @return self
     */
    public function autoRotate()
    {
        switch ($this->getExifData('Orientation')) {
            case 2:
                $this->flop();
                break;

            case 3:
                $this->rotate(180);
                break;

            case 4:
                $this->flip();
                break;

            case 5:
                $this->flip()->rotate(-90);
                break;

            case 6:
                $this->rotate(90);
                break;

            case 7:
                $this->flop()->rotate(-90);
                break;

            case 8:
                $this->rotate(90);
                break;
        }

        return $this;
    }

    /**
     * Check whether the image is an animated gif
     *
     * Copied from: https://github.com/Sybio/GifFrameExtractor/blob/master/src/GifFrameExtractor/GifFrameExtractor.php#L181
     *
     * @return boolean
     */
    protected function isAnimatedGif()
    {
        if (($this->getMimeType() !== 'image/gif') || $this->filename !== null || !($fh = @fopen($this->filename, 'rb'))) {
            return false;
        }

        $count = 0;

        while (!feof($fh) && $count < 2) {
            $chunk = fread($fh, 1024 * 100); //read 100kb at a time
            $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
        }

        fclose($fh);

        return ($count > 1);
    }

    /**
     * Converts a string with operations in an array
     *
     * @param string $operations The operations string
     *
     * @return array
     */
    private static function parseOperations($operations)
    {
        $valid_operations = array('resize', 'resizeCrop', 'crop', 'format');
        $operations = explode('|', str_replace(' ', '', $operations));
        $return = array();

        foreach ($operations as $operations) {
            $params = explode(',', $operations);
            $function = trim(array_shift($params));

            if (!in_array($function, $valid_operations)) {
                throw new ImageException("The transform function '{$function}' is not valid");
            }

            $return[] = array(
                'function' => $function,
                'params' => $params,
            );
        }

        return $return;
    }

    /**
     * Checks the library to use and returns its class
     *
     * @param string $library The library name (Gd, Imagick)
     *
     * @throws ImageException if the image library does not exists.
     *
     * @return string
     */
    public static function getLibraryClass($library)
    {
        if (!$library) {
            $library = Libs\Imagick::checkCompatibility() ? self::LIB_IMAGICK : self::LIB_GD;
        }

        $class = 'Imagecow\\Libs\\'.$library;

        if (!class_exists($class)) {
            throw new ImageException('The image library is not valid');
        }

        if (!$class::checkCompatibility()) {
            throw new ImageException("The image library '$library' is not installed in this computer");
        }

        return $class;
    }
}
