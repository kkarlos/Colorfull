<?php



namespace Keyes\Utils;

use Nette,
	Andweb;


/**
 * Color object
 */
class Color
{
	/**
	 * Red part
	 * @var integer
	 */
	protected $r = 0;
	/**
	 * Green part
	 * @var integer
	 */
	protected $g = 0;
	/**
	 * Blue part
	 * @var integer
	 */
	protected $b = 0;

	/**
	 * Flags that says which part of color we are working with
	 */
	const RED   = 0b100;
	const GREEN = 0b010;
	const BLUE  = 0b001;

	protected static $colorMap = [
		'r' => self::RED,
		'g' => self::GREEN, 
		'b' => self::BLUE,
	];

	protected static $minDifference = 500;
	protected static $minBrightness = 125;
	protected static $minLuminosity = 5;
	protected static $minDistance   = 250;

	protected static $defaultContrastousColors = [
		[0,0,0],
		[255,255,255],
	];

	public function __construct($r, $g, $b)
	{
		$this->setRed($r);
		$this->setGreen($g);
		$this->setBlue($b);
	}

	/**
	 * Create color object from hexadecimal representattion
	 * @param  string $hex Hexadecmial color code]
	 * @return self
	 */
	public static function fromHex($hex)
	{
		if(Andweb\Utils\Strings::first($hex) == '#')
			$hex = Andweb\Utils\Strings::after($hex, '#');
		list($r, $g, $b) = str_split($hex, 2);
		return new static(hexdec($r), hexdec($g), hexdec($b));
	}

	/**
	 * Creates color object from color name
	 * @param  string $name 
	 * @return self
	 */
	public static function fromName($name)
	{
		$name = String::lower($name);
		if(!array_key_exists($name, self::$namedColorsMap))
			throw new Nette\InvalidArgumentException(sprintf("Undefined color name '%s'", $name));
		list($r, $g, $b) = Arrays::get(self::$namedColorsMap, $name);
		return new static($r, $g, $b);
	}

	/**
	 * Calculates brightness
	 * @param  integer $r red part
	 * @param  integer $g green part
	 * @param  integer $b blue part
	 * @return float
	 */
	public static function getBrightness($r = 0, $g = 0, $b = 0)
	{
		return (299 * $r + 587 * $g + 114 * $b) / 1000;
	}

	/**
	 * Calculates luminosity
	 * @param  integer $r 
	 * @param  integer $g 
	 * @param  integer $b 
	 * @return float;
	 */
	public static function getLuminosity($r = 0, $g = 0, $b = 0)
	{
		return 0.2126 * pow($r/255, 2.2) + 
			   0.7152 * pow($g/255, 2.2) +
			   0.0722 * pow($b/255, 2.2);
	}

	/**
	 * Exports color to hexadecimal code
	 * @return string 
	 */
	public function toHex()
	{
		return sprintf('#%s%s%s', Strings::padLeft(dechex($this->r), 2, '0'), Strings::padLeft(dechex($this->g), 2, '0'), Strings::padLeft(dechex($this->b), 2, '0'));
	}

	/**
	 * Lightens color or its part
	 * @param  float $percent 
	 * @param  int 	 $mode    
	 * @return self
	 */
	public function lighten($percent, $mode = self::RED + self::GREEN + self::BLUE)
	{
		foreach(self::$colorMap as $c => $colorMode)
		{
			if($mode & $colorMode)
				$this->lightenColor($percent, $c);
		}
		return $this;
	}

	/**
	 * Darkens color or its part
	 * @param  float $percent 
	 * @param  int $mode    
	 * @return self          
	 */
	public function darken($percent, $mode = self::RED + self::GREEN + self::BLUE)
	{
		foreach(self::$colorMap as $c => $colorMode)
		{
			if($mode & $colorMode)
				$this->darkenColor($percent, $c);
		}
		return $this;
	}

	public function getContrastousColor(array $colors = [])
	{
		if(count($colors) == 0)
			$colors = self::$defaultContrastousColors;
		$lastColor = $colors[0];
		$lastCoeff = 0;
		foreach($colors as $color)
		{
			$coeff = $this->contrastCoefficient($color[0], $color[1], $color[2]);
			if($coeff > $lastCoeff)
			{
				$lastColor = $color;
				$lastCoeff = $coeff;
			}
		}
		return new static($lastColor[0], $lastColor[1], $lastColor[2]);
	}

	/**
	 * Set red part of color
	 * @param int $c 
	 */
	protected function setRed($c)
	{
		$this->setSaveColor($c, 'r');
	}

	/**
	 * Set green part of color
	 * @param int $c 
	 */
	protected function setGreen($c)
	{
		$this->setSaveColor($c, 'g');
	}

	/**
	 * Set blue part of color
	 * @param int $c 
	 */
	protected function setBlue($c)
	{
		$this->setSaveColor($c, 'b');
	}

	/**
	 * Counts difference
	 * @param  integer $r red part 
	 * @param  integer $g green part
	 * @param  integer $b blue part
	 * @return integer
	 */
	protected function countColorDifference($r = 0, $g = 0, $b = 0)
	{
		return max($r, $this->r) - min($r, $this->r) + 
			   max($g, $this->g) - min($g, $this->g) + 
			   max($b, $this->b) - min($b, $this->b);
	}

	/**
	 * Calculates difference between two brightnesses
	 * @param  integer $r 
	 * @param  integer $g 
	 * @param  integer $b 
	 * @return float
	 */
	protected function countBrightnessDifference($r = 0, $g = 0, $b = 0)
	{
		$brightness1 = static::getBrightness($r, $g, $b);
		$brightness2 = static::getBrightness($this->r, $this->g, $this->b);
		return abs($brightness1 - $brightness2);
	}

	/**
	 * Calculates luminosity difference
	 * @param  integer $r 
	 * @param  integer $g 
	 * @param  integer $b 
	 * @return float
	 */
	protected function luminosityDifference($r = 0, $g = 0, $b = 0)
	{
		$luminosity1 = static::getLuminosity($r, $g, $b);
		$luminosity2 = static::getLuminosity($this->r, $this->g, $this->b);
		if($luminosity1 > $luminosity2)
			return ($luminosity1 + 0.05) / ($luminosity2 + 0.05);
		return ($luminosity2 + 0.05) / ($luminosity1 + 0.05);
	}

	/**
	 * Calculates pythagorean distance between two colors
	 * @param  integer $r 
	 * @param  integer $g 
	 * @param  integer $b 
	 * @return float
	 */
	protected function pythagoreanDistance($r = 0, $g = 0, $b = 0)
	{
		$rd = $r - $this->r;
		$gd = $g - $this->g;
		$bd = $b - $this->b;

		return sqrt($rd * $rd + $gd * $gd + $bd * $bd);
	}

	/**
	 * Calculates contrast coefficient
	 * @return float
	 */
	protected function contrastCoefficient($r = 0, $g = 0, $b = 0)
	{
		return ((($this->countColorDifference($r, $g, $b) / self::$minDifference) >= 1 ) ? 1 : 0	)
			 + ((($this->countBrightnessDifference($r, $g, $b) / self::$minBrightness) >= 1) ? 1 : 0)
			 + ((($this->luminosityDifference($r, $g, $b) / self::$minLuminosity) >= 1) ? 1 : 0)
			 * ((($this->pythagoreanDistance($r, $g, $b) / self::$minDistance) >= 1) ? 1 : 0);
	}



	/**
	 * Lighten color component by name
	 * @param  float $percent 
	 * @param  string $name    
	 * @return self
	 */
	protected function lightenColor($percent, $name = 'r')
	{
		$amount = $this->getPctColorAmount($percent);
		$color = $this->getColor($name);
		$this->setSaveColor($color + $amount, $name);
		return $this;
	}

	/**
	 * Darken color components by name
	 * @param  float $percent
	 * @param  string $name  
	 * @return self
	 */
	protected function darkenColor($percent, $name = 'r')
	{
		$amount = $this->getPctColorAmount($percent);
		$color = $this->getColor($name);
		$this->setSaveColor($color - $amount, $name);
		return $this;
	}

	/**
	 * Get color
	 * @param  [type] $percent [description]
	 * @return [type]          [description]
	 */
	protected function getPctColorAmount($percent)
	{
		return ($percent * 2.55);
	}

	/**
	 * Sets color part
	 * @param int $c    
	 * @param string $name 
	 */
	private function setColor($c, $name = 'r')
	{
		if($c >= 0 || $c <= 255)
			$this->$name = $c;
		else
			throw new Nette\ArgumentOutOfRangeException('Color is out of range');
	}

	/**
	 * Gets color component
	 * @param  string $name 
	 * @return int       
	 */
	private function getColor($name)
	{
		return $this->$name;
	}

	/**
	 * Under/overflow safe color assign
	 * @param int $c    
	 * @param string $name
	 */
	protected function setSaveColor($c, $name = 'r')
	{
		$this->setColor(max(0, min(255, (int)$c)), $name);
	}

	protected static $namedColorsMap = [
		'aliceblue'            => [240,248,255],
		'antiquewhite'         => [250,235,215],
		'aqua'                 => [0,255,255],
		'aquamarine'           => [127,255,212],
		'azure'                => [240,255,255],
		'beige'                => [245,245,220],
		'bisque'               => [255,228,196],
		'black'                => [0,0,0],
		'blanchedalmond'       => [255,235,205],
		'blue'                 => [0,0,255],
		'blueviolet'           => [138,43,226],
		'brown'                => [165,42,42],
		'burlywood'            => [222,184,135],
		'cadetblue'            => [95,158,160],
		'chartreuse'           => [127,255,0],
		'chocolate'            => [210,105,30],
		'coral'                => [255,127,80],
		'cornflowerblue'       => [100,149,237],
		'cornsilk'             => [255,248,220],
		'crimson'              => [220,20,60],
		'cyan'                 => [0,255,255],
		'darkblue'             => [0,0,139],
		'darkcyan'             => [0,139,139],
		'darkgoldenrod'        => [184,134,11],
		'darkgray'             => [169,169,169],
		'darkgrey'             => [169,169,169],
		'darkgreen'            => [0,100,0],
		'darkkhaki'            => [189,183,107],
		'darkmagenta'          => [139,0,139],
		'darkolivegreen'       => [85,107,47],
		'darkorange'           => [255,140,0],
		'darkorchid'           => [153,50,204],
		'darkred'              => [139,0,0],
		'darksalmon'           => [233,150,122],
		'darkseagreen'         => [143,188,143],
		'darkslateblue'        => [72,61,139],
		'darkslategray'        => [47,79,79],
		'darkslategrey'        => [47,79,79],
		'darkturquoise'        => [0,206,209],
		'darkviolet'           => [148,0,211],
		'deeppink'             => [255,20,147],
		'deepskyblue'          => [0,191,255],
		'dimgray'              => [105,105,105],
		'dimgrey'              => [105,105,105],
		'dodgerblue'           => [30,144,255],
		'firebrick'            => [178,34,34],
		'floralwhite'          => [255,250,240],
		'forestgreen'          => [34,139,34],
		'fuchsia'              => [255,0,255],
		'gainsboro'            => [220,220,220],
		'ghostwhite'           => [248,248,255],
		'gold'                 => [255,215,0],
		'goldenrod'            => [218,165,32],
		'gray'                 => [128,128,128],
		'grey'                 => [128,128,128],
		'green'                => [0,128,0],
		'greenyellow'          => [173,255,47],
		'honeydew'             => [240,255,240],
		'hotpink'              => [255,105,180],
		'indianred'            => [205,92,92],
		'indigo'               => [75,0,130],
		'ivory'                => [255,255,240],
		'khaki'                => [240,230,140],
		'lavender'             => [230,230,250],
		'lavenderblush'        => [255,240,245],
		'lawngreen'            => [124,252,0],
		'lemonchiffon'         => [255,250,205],
		'lightblue'            => [173,216,230],
		'lightcoral'           => [240,128,128],
		'lightcyan'            => [224,255,255],
		'lightgoldenrodyellow' => [250,250,210],
		'lightgray'            => [211,211,211],
		'lightgrey'            => [211,211,211],
		'lightgreen'           => [144,238,144],
		'lightpink'            => [255,182,193],
		'lightsalmon'          => [255,160,122],
		'lightseagreen'        => [32,178,170],
		'lightskyblue'         => [135,206,250],
		'lightslategray'       => [119,136,153],
		'lightslategrey'       => [119,136,153],
		'lightsteelblue'       => [176,196,222],
		'lightyellow'          => [255,255,224],
		'lime'                 => [0,255,0],
		'limegreen'            => [50,205,50],
		'linen'                => [250,240,230],
		'magenta'              => [255,0,255],
		'maroon'               => [128,0,0],
		'mediumaquamarine'     => [102,205,170],
		'mediumblue'           => [0,0,205],
		'mediumorchid'         => [186,85,211],
		'mediumpurple'         => [147,112,219],
		'mediumseagreen'       => [60,179,113],
		'mediumslateblue'      => [123,104,238],
		'mediumspringgreen'    => [0,250,154],
		'mediumturquoise'      => [72,209,204],
		'mediumvioletred'      => [199,21,133],
		'midnightblue'         => [25,25,112],
		'mintcream'            => [245,255,250],
		'mistyrose'            => [255,228,225],
		'moccasin'             => [255,228,181],
		'navajowhite'          => [255,222,173],
		'navy'                 => [0,0,128],
		'oldlace'              => [253,245,230],
		'olive'                => [128,128,0],
		'olivedrab'            => [107,142,35],
		'orange'               => [255,165,0],
		'orangered'            => [255,69,0],
		'orchid'               => [218,112,214],
		'palegoldenrod'        => [238,232,170],
		'palegreen'            => [152,251,152],
		'paleturquoise'        => [175,238,238],
		'palevioletred'        => [219,112,147],
		'papayawhip'           => [255,239,213],
		'peachpuff'            => [255,218,185],
		'peru'                 => [205,133,63],
		'pink'                 => [255,192,203],
		'plum'                 => [221,160,221],
		'powderblue'           => [176,224,230],
		'purple'               => [128,0,128],
		'rebeccapurple'        => [102,51,153],
		'red'                  => [255,0,0],
		'rosybrown'            => [188,143,143],
		'royalblue'            => [65,105,225],
		'saddlebrown'          => [139,69,19],
		'salmon'               => [250,128,114],
		'sandybrown'           => [244,164,96],
		'seagreen'             => [46,139,87],
		'seashell'             => [255,245,238],
		'sienna'               => [160,82,45],
		'silver'               => [192,192,192],
		'skyblue'              => [135,206,235],
		'slateblue'            => [106,90,205],
		'slategray'            => [112,128,144],
		'slategrey'            => [112,128,144],
		'snow'                 => [255,250,250],
		'springgreen'          => [0,255,127],
		'steelblue'            => [70,130,180],
		'tan'                  => [210,180,140],
		'teal'                 => [0,128,128],
		'thistle'              => [216,191,216],
		'tomato'               => [255,99,71],
		'turquoise'            => [64,224,208],
		'violet'               => [238,130,238],
		'wheat'                => [245,222,179],
		'white'                => [255,255,255],
		'whitesmoke'           => [245,245,245],
		'yellow'               => [255,255,0],
		'yellowgreen'          => [154,205,50],
	];

}