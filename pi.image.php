<?php
class Plugin_image extends Plugin {

  var $meta = array(
    'name'       => 'Image',
    'version'    => '0.9',
    'author'     => 'Mubashar Iqbal',
    'author_url' => 'http://mubashariqbal.com'
  );

  public function index() {
    $src            = $this->fetch_param('src', null);
    $dim            = $this->fetch_param('dim', null);
    $quality        = $this->fetch_param('quality', '100');

    if ($src == null) {
      return '';
    } else {
      $pos = stripos($src, "/");
      if ($pos !== false) {
        if ($pos == 0) {
          $src = substr($src, 1);
        }
      }
    }

    if ($dim == null) {
      return '';
    }
    $arr = $this->generate_style($src, $dim, $quality);
    return $arr;
  }

  private function generate_style($file, $dim, $quality) {
    $file_name = basename($file);
    $dir = dirname($file);
    $new_file = "_cache/".$dir."/".$this->url_safe($dim)."-".$file_name;

    if (!file_exists($file)) {
      return null;
    }

    if ( ! file_exists($new_file) || Statamic_helper::is_file_newer($file, $new_file)) {

      if ( ! is_dir(dirname($new_file))) {
        mkdir(dirname($new_file), 0777, true);
      }

      $image    = new Image($file);
      $resize   = true;
      list($width, $height, $fix, $crop, $vh) = $this->parse_dim($dim);
      if ($fix) {
        $dimension = Image::NONE;
      } else {
        $dimension = Image::WIDTH;
        $wd = $image->width  - $width;
        $hd = $image->height - $height;
        $pp = ($image->width / $image->height) * $hd;
        if ($vh) {
          if ($vh == 'v') {
            $dimension = Image::HEIGHT;
            if (abs($wd) < abs($hd)) {
              $crop = true;
            }
          } elseif ($vh == 'h') {
            $dimension = Image::WIDTH;
            if ($wd < 0) {
              //$resize = false;
            } else {
            }
          }
        } else {
          if ($crop) {
            if ((($wd >= $hd) && ($pp < $wd)) || (($wd < 1) && ($pp < $wd))) {
              $dimension = Image::HEIGHT;
            }
          } else {
            if ((($wd <= $hd) && ($pp > $wd)) || (($wd > 1) && ($pp > $wd))) {
              $dimension = Image::HEIGHT;
            }
          }
        }
      }
      if ($resize) {
        $image->resize($width, $height, $dimension);
      }
      if ($crop) {
        $image->crop($width, $height, null);
      }
      $image->save($new_file, $quality);
    }

    $info = getimagesize($new_file);
    
    $ret = array();
    $ret['width'] = $info[0];
    $ret['height'] = $info[1];
    $ret['url'] = "/".$new_file;

    return $ret;
  }

  protected function url_safe($str)
  {
    $patterns = array();
    $patterns[0] = '/#/';
    $patterns[1] = '/!/';
    $patterns[2] = '/\(/';
    $patterns[3] = '/\)/';
    $patterns[4] = '/</';
    $patterns[5] = '/>/';
    $replacements = array();
    $replacements[0] = 'F';
    $replacements[1] = 'E';
    $replacements[2] = 'R1';
    $replacements[3] = 'R2';
    $replacements[4] = 'A1';
    $replacements[5] = 'A2';
    $clean = preg_replace($patterns, $replacements, $str);
    $clean = preg_replace("/[^a-zA-Z0-9\/_|+ -]/", '-', $clean);
    $clean = strtolower(ltrim($clean, '-'));
    $clean = preg_replace("/[\/_|+ -]+/", '-', $clean);

    return $clean;
  }

  protected function parse_dim($dimensions) {
    # why not make this easier to use? some more parameters?
    $fix  = false;
    $crop = false;
    $vh   = false;
    switch (substr($dimensions, -1)) {
      case '#':
        $crop       = true;
        $dimensions = substr($dimensions, 0, (strlen($dimensions)-1));
        break;
      case '!':
        $fix        = true;
        $dimensions = substr($dimensions, 0, (strlen($dimensions)-1));
        break;
      case '<':
        $vh = "h";
        $dimensions = substr($dimensions, 0, (strlen($dimensions)-1));
        break;
      case '>':
        $vh = "v";
        $dimensions = substr($dimensions, 0, (strlen($dimensions)-1));
        break;
      case '(':
        $crop = true;
        $vh = "h";
        $dimensions = substr($dimensions, 0, (strlen($dimensions)-1));
        break;
      case ')':
        $crop = true;
        $vh = "v";
        $dimensions = substr($dimensions, 0, (strlen($dimensions)-1));
        break;
    }
    $dim    = explode("x", $dimensions);
    $width  = (isset($dim[0]) && $dim[0] != '') ? $dim[0] : 0;
    $height = (isset($dim[1]) && $dim[1] != '') ? $dim[1] : 0;
    return array($width, $height, $fix, $crop, $vh);
  }


}


/**
 * Image manipulation support. Allows images to be resized, cropped, etc.
 * Based on Kohana Image Class 
 * @license    http://kohanaphp.com/license
 */

class Image {

  // Resizing contraints
  const NONE    = 0x01;
  const WIDTH   = 0x02;
  const HEIGHT  = 0x03;
  const AUTO    = 0x04;
  const INVERSE = 0x05;

  // Flipping directions
  const HORIZONTAL = 0x11;
  const VERTICAL   = 0x12;

  /**
   * @var  string  image file path
   */
  public $file;

  /**
   * @var  integer  image width
   */
  public $width;

  /**
   * @var  integer  image height
   */
  public $height;

  /**
   * @var  integer  one of the IMAGETYPE_* constants
   */
  public $type;

  /**
   * Loads information about the image. Will throw an exception if the image
   * does not exist or is not an image.
   *
   * @param   string   image file path
   * @return  void
   * @throws  Exception
   */
  public function __construct($file)
  {
    try
    {
      // Get the real path to the file
      $file = realpath($file);

      // Get the image information
      $info = getimagesize($file);
    }
    catch (Exception $e)
    {
      // Ignore all errors while reading the image
    }

    if (empty($file) OR empty($info))
    {
      // throw new Exception('Not an image or invalid image: :file',
      //   array(':file' => Kohana::debug_path($file)));
      return '';
    }

    // Store the image information
    $this->file   = $file;
    $this->width  = $info[0];
    $this->height = $info[1];
    $this->type   = $info[2];
    $this->mime   = image_type_to_mime_type($this->type);

    // Set the image creation function name
    switch ($this->type)
    {
      case IMAGETYPE_JPEG:
        $create = 'imagecreatefromjpeg';
      break;
      case IMAGETYPE_GIF:
        $create = 'imagecreatefromgif';
      break;
      case IMAGETYPE_PNG:
        $create = 'imagecreatefrompng';
      break;
    }

    if ( ! isset($create) OR ! function_exists($create))
    {
      // throw new Exception('Installed GD does not support :type images',
      //   array(':type' => image_type_to_extension($this->type, FALSE)));
    }

    // Save function for future use
    $this->_create_function = $create;

    // Save filename for lazy loading
    $this->_image = $this->file;
  }

  // Is GD bundled or separate?
  protected static $_bundled;

  /**
   * Checks if GD is enabled and bundled. Bundled GD is required for some
   * methods to work. Exceptions will be thrown from those methods when GD is
   * not bundled.
   *
   * @return  boolean
   */
  public static function check()
  {
    if ( ! function_exists('gd_info'))
    {
      //throw new Exception('GD is either not installed or not enabled, check your configuration');
    }

    if (defined('GD_BUNDLED'))
    {
      // Get the version via a constant, available in PHP 5.
      Image_GD::$_bundled = GD_BUNDLED;
    }
    else
    {
      // Get the version information
      $info = gd_info();

      // Extract the bundled status
      Image_GD::$_bundled = (bool) preg_match('/\bbundled\b/i', $info['GD Version']);
    }

    if (defined('GD_VERSION'))
    {
      // Get the version via a constant, available in PHP 5.2.4+
      $version = GD_VERSION;
    }
    else
    {
      // Get the version information
      $info = gd_info();

      // Extract the version number
      preg_match('/\d+\.\d+(?:\.\d+)?/', $info['GD Version'], $matches);

      // Get the major version
      $version = $matches[0];
    }

    if ( ! version_compare($version, '2.0.1', '>='))
    {
      // throw new Exception('Image_GD requires GD version :required or greater, you have :version',
      //   array('required' => '2.0.1', ':version' => $version));
    }

    return Image_GD::$_checked = TRUE;
  }

  // Temporary image resource
  protected $_image;

  // Function name to open Image
  protected $_create_function;


  /**
   * Destroys the loaded image to free up resources.
   *
   * @return  void
   */
  public function __destruct()
  {
    if (is_resource($this->_image))
    {
      // Free all resources
      imagedestroy($this->_image);
    }
  }




  /**
   * Resize the image to the given size. Either the width or the height can
   * be omitted and the image will be resized proportionally.
   *
   *     // Resize to 200 pixels on the shortest side
   *     $image->resize(200, 200);
   *
   *     // Resize to 200x200 pixels, keeping aspect ratio
   *     $image->resize(200, 200, Image::INVERSE);
   *
   *     // Resize to 500 pixel width, keeping aspect ratio
   *     $image->resize(500, NULL);
   *
   *     // Resize to 500 pixel height, keeping aspect ratio
   *     $image->resize(NULL, 500);
   *
   *     // Resize to 200x500 pixels, ignoring aspect ratio
   *     $image->resize(200, 500, Image::NONE);
   *
   * @param   integer  new width
   * @param   integer  new height
   * @param   integer  master dimension
   * @return  $this
   * @uses    Image::_do_resize
   */
  public function resize($width = NULL, $height = NULL, $master = NULL)
  {
    if ($master === NULL)
    {
      // Choose the master dimension automatically
      $master = Image::AUTO;
    }
    // Image::WIDTH and Image::HEIGHT depricated. You can use it in old projects,
    // but in new you must pass empty value for non-master dimension
    elseif ($master == Image::WIDTH AND ! empty($width))
    {
      $master = Image::AUTO;

      // Set empty height for backvard compatibility
      $height = NULL;
    }
    elseif ($master == Image::HEIGHT AND ! empty($height))
    {
      $master = Image::AUTO;

      // Set empty width for backvard compatibility
      $width = NULL;
    }

    if (empty($width))
    {
      if ($master === Image::NONE)
      {
        // Use the current width
        $width = $this->width;
      }
      else
      {
        // If width not set, master will be height
        $master = Image::HEIGHT;
      }
    }

    if (empty($height))
    {
      if ($master === Image::NONE)
      {
        // Use the current height
        $height = $this->height;
      }
      else
      {
        // If height not set, master will be width
        $master = Image::WIDTH;
      }
    }

    switch ($master)
    {
      case Image::AUTO:
        // Choose direction with the greatest reduction ratio
        $master = ($this->width / $width) > ($this->height / $height) ? Image::WIDTH : Image::HEIGHT;
      break;
      case Image::INVERSE:
        // Choose direction with the minimum reduction ratio
        $master = ($this->width / $width) > ($this->height / $height) ? Image::HEIGHT : Image::WIDTH;
      break;
    }

    switch ($master)
    {
      case Image::WIDTH:
        // Recalculate the height based on the width proportions
        $height = $this->height * $width / $this->width;
      break;
      case Image::HEIGHT:
        // Recalculate the width based on the height proportions
        $width = $this->width * $height / $this->height;
      break;
    }

    // Convert the width and height to integers
    $width  = round($width);
    $height = round($height);

    $this->_do_resize($width, $height);

    return $this;
  }

  /**
   * Crop an image to the given size. Either the width or the height can be
   * omitted and the current width or height will be used.
   *
   * If no offset is specified, the center of the axis will be used.
   * If an offset of TRUE is specified, the bottom of the axis will be used.
   *
   *     // Crop the image to 200x200 pixels, from the center
   *     $image->crop(200, 200);
   *
   * @param   integer  new width
   * @param   integer  new height
   * @param   mixed    offset from the left
   * @param   mixed    offset from the top
   * @return  $this
   * @uses    Image::_do_crop
   */
  public function crop($width, $height, $offset_x = NULL, $offset_y = NULL)
  {
    if ($width > $this->width)
    {
      // Use the current width
      $width = $this->width;
    }

    if ($height > $this->height)
    {
      // Use the current height
      $height = $this->height;
    }

    if ($offset_x === NULL)
    {
      // Center the X offset
      $offset_x = round(($this->width - $width) / 2);
    }
    elseif ($offset_x === TRUE)
    {
      // Bottom the X offset
      $offset_x = $this->width - $width;
    }
    elseif ($offset_x < 0)
    {
      // Set the X offset from the right
      $offset_x = $this->width - $width + $offset_x;
    }

    if ($offset_y === NULL)
    {
      // Center the Y offset
      $offset_y = round(($this->height - $height) / 2);
    }
    elseif ($offset_y === TRUE)
    {
      // Bottom the Y offset
      $offset_y = $this->height - $height;
    }
    elseif ($offset_y < 0)
    {
      // Set the Y offset from the bottom
      $offset_y = $this->height - $height + $offset_y;
    }

    // Determine the maximum possible width and height
    $max_width  = $this->width  - $offset_x;
    $max_height = $this->height - $offset_y;

    if ($width > $max_width)
    {
      // Use the maximum available width
      $width = $max_width;
    }

    if ($height > $max_height)
    {
      // Use the maximum available height
      $height = $max_height;
    }

    $this->_do_crop($width, $height, $offset_x, $offset_y);

    return $this;
  }

  /**
   * Rotate the image by a given amount.
   *
   *     // Rotate 45 degrees clockwise
   *     $image->rotate(45);
   *
   *     // Rotate 90% counter-clockwise
   *     $image->rotate(-90);
   *
   * @param   integer   degrees to rotate: -360-360
   * @return  $this
   * @uses    Image::_do_rotate
   */
  public function rotate($degrees)
  {
    // Make the degrees an integer
    $degrees = (int) $degrees;

    if ($degrees > 180)
    {
      do
      {
        // Keep subtracting full circles until the degrees have normalized
        $degrees -= 360;
      }
      while($degrees > 180);
    }

    if ($degrees < -180)
    {
      do
      {
        // Keep adding full circles until the degrees have normalized
        $degrees += 360;
      }
      while($degrees < -180);
    }

    $this->_do_rotate($degrees);

    return $this;
  }

  /**
   * Flip the image along the horizontal or vertical axis.
   *
   *     // Flip the image from top to bottom
   *     $image->flip(Image::HORIZONTAL);
   *
   *     // Flip the image from left to right
   *     $image->flip(Image::VERTICAL);
   *
   * @param   integer  direction: Image::HORIZONTAL, Image::VERTICAL
   * @return  $this
   * @uses    Image::_do_flip
   */
  public function flip($direction)
  {
    if ($direction !== Image::HORIZONTAL)
    {
      // Flip vertically
      $direction = Image::VERTICAL;
    }

    $this->_do_flip($direction);

    return $this;
  }

  /**
   * Sharpen the image by a given amount.
   *
   *     // Sharpen the image by 20%
   *     $image->sharpen(20);
   *
   * @param   integer  amount to sharpen: 1-100
   * @return  $this
   * @uses    Image::_do_sharpen
   */
  public function sharpen($amount)
  {
    // The amount must be in the range of 1 to 100
    $amount = min(max($amount, 1), 100);

    $this->_do_sharpen($amount);

    return $this;
  }

  /**
   * Add a reflection to an image. The most opaque part of the reflection
   * will be equal to the opacity setting and fade out to full transparent.
   * Alpha transparency is preserved.
   *
   *     // Create a 50 pixel reflection that fades from 0-100% opacity
   *     $image->reflection(50);
   *
   *     // Create a 50 pixel reflection that fades from 100-0% opacity
   *     $image->reflection(50, 100, TRUE);
   *
   *     // Create a 50 pixel reflection that fades from 0-60% opacity
   *     $image->reflection(50, 60, TRUE);
   *
   * [!!] By default, the reflection will be go from transparent at the top
   * to opaque at the bottom.
   *
   * @param   integer   reflection height
   * @param   integer   reflection opacity: 0-100
   * @param   boolean   TRUE to fade in, FALSE to fade out
   * @return  $this
   * @uses    Image::_do_reflection
   */
  public function reflection($height = NULL, $opacity = 100, $fade_in = FALSE)
  {
    if ($height === NULL OR $height > $this->height)
    {
      // Use the current height
      $height = $this->height;
    }

    // The opacity must be in the range of 0 to 100
    $opacity = min(max($opacity, 0), 100);

    $this->_do_reflection($height, $opacity, $fade_in);

    return $this;
  }

  /**
   * Add a watermark to an image with a specified opacity. Alpha transparency
   * will be preserved.
   *
   * If no offset is specified, the center of the axis will be used.
   * If an offset of TRUE is specified, the bottom of the axis will be used.
   *
   *     // Add a watermark to the bottom right of the image
   *     $mark = Image::factory('upload/watermark.png');
   *     $image->watermark($mark, TRUE, TRUE);
   *
   * @param   object   watermark Image instance
   * @param   integer  offset from the left
   * @param   integer  offset from the top
   * @param   integer  opacity of watermark: 1-100
   * @return  $this
   * @uses    Image::_do_watermark
   */
  public function watermark(Image $watermark, $offset_x = NULL, $offset_y = NULL, $opacity = 100)
  {
    if ($offset_x === NULL)
    {
      // Center the X offset
      $offset_x = round(($this->width - $watermark->width) / 2);
    }
    elseif ($offset_x === TRUE)
    {
      // Bottom the X offset
      $offset_x = $this->width - $watermark->width;
    }
    elseif ($offset_x < 0)
    {
      // Set the X offset from the right
      $offset_x = $this->width - $watermark->width + $offset_x;
    }

    if ($offset_y === NULL)
    {
      // Center the Y offset
      $offset_y = round(($this->height - $watermark->height) / 2);
    }
    elseif ($offset_y === TRUE)
    {
      // Bottom the Y offset
      $offset_y = $this->height - $watermark->height;
    }
    elseif ($offset_y < 0)
    {
      // Set the Y offset from the bottom
      $offset_y = $this->height - $watermark->height + $offset_y;
    }

    // The opacity must be in the range of 1 to 100
    $opacity = min(max($opacity, 1), 100);

    $this->_do_watermark($watermark, $offset_x, $offset_y, $opacity);

    return $this;
  }

  /**
   * Set the background color of an image. This is only useful for images
   * with alpha transparency.
   *
   *     // Make the image background black
   *     $image->background('#000');
   *
   *     // Make the image background black with 50% opacity
   *     $image->background('#000', 50);
   *
   * @param   string   hexadecimal color value
   * @param   integer  background opacity: 0-100
   * @return  $this
   * @uses    Image::_do_background
   */
  public function background($color, $opacity = 100)
  {
    if ($color[0] === '#')
    {
      // Remove the pound
      $color = substr($color, 1);
    }

    if (strlen($color) === 3)
    {
      // Convert shorthand into longhand hex notation
      $color = preg_replace('/./', '$0$0', $color);
    }

    // Convert the hex into RGB values
    list ($r, $g, $b) = array_map('hexdec', str_split($color, 2));

    // The opacity must be in the range of 0 to 100
    $opacity = min(max($opacity, 0), 100);

    $this->_do_background($r, $g, $b, $opacity);

    return $this;
  }

  /**
   * Save the image. If the filename is omitted, the original image will
   * be overwritten.
   *
   *     // Save the image as a PNG
   *     $image->save('saved/cool.png');
   *
   *     // Overwrite the original image
   *     $image->save();
   *
   * [!!] If the file exists, but is not writable, an exception will be thrown.
   *
   * [!!] If the file does not exist, and the directory is not writable, an
   * exception will be thrown.
   *
   * @param   string   new image path
   * @param   integer  quality of image: 1-100
   * @return  boolean
   * @uses    Image::_save
   * @throws  Exception
   */
  public function save($file = NULL, $quality = 100)
  {
    if ($file === NULL)
    {
      // Overwrite the file
      $file = $this->file;
    }

    if (is_file($file))
    {
      if ( ! is_writable($file))
      {
        throw new Exception('File must be writable');
      }
    }
    else
    {
      // Get the directory of the file
      $directory = realpath(pathinfo($file, PATHINFO_DIRNAME));

      if ( ! is_dir($directory) OR ! is_writable($directory))
      {
        throw new Exception('Directory must be writable');
      }
    }

    // The quality must be in the range of 1 to 100
    $quality = min(max($quality, 1), 100);

    return $this->_do_save($file, $quality);
  }

  /**
   * Render the image and return the binary string.
   *
   *     // Render the image at 50% quality
   *     $data = $image->render(NULL, 50);
   *
   *     // Render the image as a PNG
   *     $data = $image->render('png');
   *
   * @param   string   image type to return: png, jpg, gif, etc
   * @param   integer  quality of image: 1-100
   * @return  string
   * @uses    Image::_do_render
   */
  public function render($type = NULL, $quality = 100)
  {
    if ($type === NULL)
    {
      // Use the current image type
      $type = image_type_to_extension($this->type, FALSE);
    }

    return $this->_do_render($type, $quality);
  }



  /**
   * Loads an image into GD.
   *
   * @return  void
   */
  protected function _load_image()
  {
    if ( ! is_resource($this->_image))
    {
      // Gets create function
      $create = $this->_create_function;

      // Open the temporary image
      $this->_image = $create($this->file);

      // Preserve transparency when saving
      imagesavealpha($this->_image, TRUE);
    }
  }

  protected function _do_resize($width, $height)
  {
    // Presize width and height
    $pre_width = $this->width;
    $pre_height = $this->height;

    // Loads image if not yet loaded
    $this->_load_image();

    // Test if we can do a resize without resampling to speed up the final resize
    if ($width > ($this->width / 2) AND $height > ($this->height / 2))
    {
      // The maximum reduction is 10% greater than the final size
      $reduction_width  = round($width  * 1.1);
      $reduction_height = round($height * 1.1);

      while ($pre_width / 2 > $reduction_width AND $pre_height / 2 > $reduction_height)
      {
        // Reduce the size using an O(2n) algorithm, until it reaches the maximum reduction
        $pre_width /= 2;
        $pre_height /= 2;
      }

      // Create the temporary image to copy to
      $image = $this->_create($pre_width, $pre_height);

      if (imagecopyresized($image, $this->_image, 0, 0, 0, 0, $pre_width, $pre_height, $this->width, $this->height))
      {
        // Swap the new image for the old one
        imagedestroy($this->_image);
        $this->_image = $image;
      }
    }

    // Create the temporary image to copy to
    $image = $this->_create($width, $height);

    // Execute the resize
    if (imagecopyresampled($image, $this->_image, 0, 0, 0, 0, $width, $height, $pre_width, $pre_height))
    {
      // Swap the new image for the old one
      imagedestroy($this->_image);
      $this->_image = $image;

      // Reset the width and height
      $this->width  = imagesx($image);
      $this->height = imagesy($image);
    }
  }

  protected function _do_crop($width, $height, $offset_x, $offset_y)
  {
    // Create the temporary image to copy to
    $image = $this->_create($width, $height);

    // Loads image if not yet loaded
    $this->_load_image();

    // Execute the crop
    if (imagecopyresampled($image, $this->_image, 0, 0, $offset_x, $offset_y, $width, $height, $width, $height))
    {
      // Swap the new image for the old one
      imagedestroy($this->_image);
      $this->_image = $image;

      // Reset the width and height
      $this->width  = imagesx($image);
      $this->height = imagesy($image);
    }
  }

  protected function _do_rotate($degrees)
  {
    if ( ! Image_GD::$_bundled)
    {
      throw new Exception('This method requires :function, which is only available in the bundled version of GD',
        array(':function' => 'imagerotate'));
    }

    // Loads image if not yet loaded
    $this->_load_image();

    // Transparent black will be used as the background for the uncovered region
    $transparent = imagecolorallocatealpha($this->_image, 0, 0, 0, 127);

    // Rotate, setting the transparent color
    $image = imagerotate($this->_image, 360 - $degrees, $transparent, 1);

    // Save the alpha of the rotated image
    imagesavealpha($image, TRUE);

    // Get the width and height of the rotated image
    $width  = imagesx($image);
    $height = imagesy($image);

    if (imagecopymerge($this->_image, $image, 0, 0, 0, 0, $width, $height, 100))
    {
      // Swap the new image for the old one
      imagedestroy($this->_image);
      $this->_image = $image;

      // Reset the width and height
      $this->width  = $width;
      $this->height = $height;
    }
  }

  protected function _do_flip($direction)
  {
    // Create the flipped image
    $flipped = $this->_create($this->width, $this->height);

    // Loads image if not yet loaded
    $this->_load_image();

    if ($direction === Image::HORIZONTAL)
    {
      for ($x = 0; $x < $this->width; $x++)
      {
        // Flip each row from top to bottom
        imagecopy($flipped, $this->_image, $x, 0, $this->width - $x - 1, 0, 1, $this->height);
      }
    }
    else
    {
      for ($y = 0; $y < $this->height; $y++)
      {
        // Flip each column from left to right
        imagecopy($flipped, $this->_image, 0, $y, 0, $this->height - $y - 1, $this->width, 1);
      }
    }

    // Swap the new image for the old one
    imagedestroy($this->_image);
    $this->_image = $flipped;

    // Reset the width and height
    $this->width  = imagesx($flipped);
    $this->height = imagesy($flipped);
  }

  protected function _do_sharpen($amount)
  {
    if ( ! Image_GD::$_bundled)
    {
      throw new Exception('This method requires :function, which is only available in the bundled version of GD',
        array(':function' => 'imageconvolution'));
    }

    // Loads image if not yet loaded
    $this->_load_image();

    // Amount should be in the range of 18-10
    $amount = round(abs(-18 + ($amount * 0.08)), 2);

    // Gaussian blur matrix
    $matrix = array
    (
      array(-1,   -1,    -1),
      array(-1, $amount, -1),
      array(-1,   -1,    -1),
    );

    // Perform the sharpen
    if (imageconvolution($this->_image, $matrix, $amount - 8, 0))
    {
      // Reset the width and height
      $this->width  = imagesx($this->_image);
      $this->height = imagesy($this->_image);
    }
  }

  protected function _do_reflection($height, $opacity, $fade_in)
  {
    if ( ! Image_GD::$_bundled)
    {
      throw new Exception('This method requires :function, which is only available in the bundled version of GD',
        array(':function' => 'imagefilter'));
    }

    // Loads image if not yet loaded
    $this->_load_image();

    // Convert an opacity range of 0-100 to 127-0
    $opacity = round(abs(($opacity * 127 / 100) - 127));

    if ($opacity < 127)
    {
      // Calculate the opacity stepping
      $stepping = (127 - $opacity) / $height;
    }
    else
    {
      // Avoid a "divide by zero" error
      $stepping = 127 / $height;
    }

    // Create the reflection image
    $reflection = $this->_create($this->width, $this->height + $height);

    // Copy the image to the reflection
    imagecopy($reflection, $this->_image, 0, 0, 0, 0, $this->width, $this->height);

    for ($offset = 0; $height >= $offset; $offset++)
    {
      // Read the next line down
      $src_y = $this->height - $offset - 1;

      // Place the line at the bottom of the reflection
      $dst_y = $this->height + $offset;

      if ($fade_in === TRUE)
      {
        // Start with the most transparent line first
        $dst_opacity = round($opacity + ($stepping * ($height - $offset)));
      }
      else
      {
        // Start with the most opaque line first
        $dst_opacity = round($opacity + ($stepping * $offset));
      }

      // Create a single line of the image
      $line = $this->_create($this->width, 1);

      // Copy a single line from the current image into the line
      imagecopy($line, $this->_image, 0, 0, 0, $src_y, $this->width, 1);

      // Colorize the line to add the correct alpha level
      imagefilter($line, IMG_FILTER_COLORIZE, 0, 0, 0, $dst_opacity);

      // Copy a the line into the reflection
      imagecopy($reflection, $line, 0, $dst_y, 0, 0, $this->width, 1);
    }

    // Swap the new image for the old one
    imagedestroy($this->_image);
    $this->_image = $reflection;

    // Reset the width and height
    $this->width  = imagesx($reflection);
    $this->height = imagesy($reflection);
  }

  protected function _do_watermark(Image $watermark, $offset_x, $offset_y, $opacity)
  {
    if ( ! Image_GD::$_bundled)
    {
      throw new Exception('This method requires :function, which is only available in the bundled version of GD',
        array(':function' => 'imagelayereffect'));
    }

    // Loads image if not yet loaded
    $this->_load_image();

    // Create the watermark image resource
    $overlay = imagecreatefromstring($watermark->render());

    // Get the width and height of the watermark
    $width  = imagesx($overlay);
    $height = imagesy($overlay);

    if ($opacity < 100)
    {
      // Convert an opacity range of 0-100 to 127-0
      $opacity = round(abs(($opacity * 127 / 100) - 127));

      // Allocate transparent white
      $color = imagecolorallocatealpha($overlay, 255, 255, 255, $opacity);

      // The transparent image will overlay the watermark
      imagelayereffect($overlay, IMG_EFFECT_OVERLAY);

      // Fill the background with transparent white
      imagefilledrectangle($overlay, 0, 0, $width, $height, $color);
    }

    // Alpha blending must be enabled on the background!
    imagealphablending($this->_image, TRUE);

    if (imagecopy($this->_image, $overlay, $offset_x, $offset_y, 0, 0, $width, $height))
    {
      // Destroy the overlay image
      imagedestroy($overlay);
    }
  }

  protected function _do_background($r, $g, $b, $opacity)
  {
    // Loads image if not yet loaded
    $this->_load_image();

    // Convert an opacity range of 0-100 to 127-0
    $opacity = round(abs(($opacity * 127 / 100) - 127));

    // Create a new background
    $background = $this->_create($this->width, $this->height);

    // Allocate the color
    $color = imagecolorallocatealpha($background, $r, $g, $b, $opacity);

    // Fill the image with white
    imagefilledrectangle($background, 0, 0, $this->width, $this->height, $color);

    // Alpha blending must be enabled on the background!
    imagealphablending($background, TRUE);

    // Copy the image onto a white background to remove all transparency
    if (imagecopy($background, $this->_image, 0, 0, 0, 0, $this->width, $this->height))
    {
      // Swap the new image for the old one
      imagedestroy($this->_image);
      $this->_image = $background;
    }
  }

  protected function _do_save($file, $quality)
  {
    // Loads image if not yet loaded
    $this->_load_image();

    // Get the extension of the file
    $extension = pathinfo($file, PATHINFO_EXTENSION);

    // Get the save function and IMAGETYPE
    list($save, $type) = $this->_save_function($extension, $quality);

    // Save the image to a file
    $status = isset($quality) ? $save($this->_image, $file, $quality) : $save($this->_image, $file);

    if ($status === TRUE AND $type !== $this->type)
    {
      // Reset the image type and mime type
      $this->type = $type;
      $this->mime = image_type_to_mime_type($type);
    }

    return TRUE;
  }

  protected function _do_render($type, $quality)
  {
    // Loads image if not yet loaded
    $this->_load_image();

    // Get the save function and IMAGETYPE
    list($save, $type) = $this->_save_function($type, $quality);

    // Capture the output
    ob_start();

    // Render the image
    $status = isset($quality) ? $save($this->_image, NULL, $quality) : $save($this->_image, NULL);

    if ($status === TRUE AND $type !== $this->type)
    {
      // Reset the image type and mime type
      $this->type = $type;
      $this->mime = image_type_to_mime_type($type);
    }

    return ob_get_clean();
  }

  /**
   * Get the GD saving function and image type for this extension.
   * Also normalizes the quality setting
   *
   * @param   string   image type: png, jpg, etc
   * @param   integer  image quality
   * @return  array    save function, IMAGETYPE_* constant
   * @throws  Exception
   */
  protected function _save_function($extension, & $quality)
  {
    switch (strtolower($extension))
    {
      case 'jpg':
      case 'jpeg':
        // Save a JPG file
        $save = 'imagejpeg';
        $type = IMAGETYPE_JPEG;
      break;
      case 'gif':
        // Save a GIF file
        $save = 'imagegif';
        $type = IMAGETYPE_GIF;

        // GIFs do not a quality setting
        $quality = NULL;
      break;
      case 'png':
        // Save a PNG file
        $save = 'imagepng';
        $type = IMAGETYPE_PNG;

        // Use a compression level of 9 (does not affect quality!)
        $quality = 9;
      break;
      default:
        throw new Exception('Installed GD does not support :type images',
          array(':type' => $extension));
      break;
    }

    return array($save, $type);
  }

  /**
   * Create an empty image with the given width and height.
   *
   * @param   integer   image width
   * @param   integer   image height
   * @return  resource
   */
  protected function _create($width, $height)
  {
    // Create an empty image
    $image = imagecreatetruecolor($width, $height);

    // Do not apply alpha blending
    imagealphablending($image, FALSE);

    // Save alpha levels
    imagesavealpha($image, TRUE);

    return $image;
  }

} // End Image
