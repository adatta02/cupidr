<?php

error_reporting(E_ALL);

class RenderImage {
  
  private $jsonConfigs = [];
  
  public function __construct(){
    $this->jsonConfigs = json_decode(file_get_contents(dirname(__FILE__) . "/../bin/templates.json"), true);  
  }

  public function getTextColor( $im, $template ){
    $default = imagecolorallocate($im, 0, 0, 0);
    $config = $this->getConfig( $template );
    
    if( !$config || !array_key_exists("color", $config) ){
      return $default;
    }
    
    $color = str_replace("#", "", $config["color"]);        
    
    $r = hexdec($color[0] . $color[1]);
    $g = hexdec($color[2] . $color[3]);
    $b = hexdec($color[4] . $color[5]);
    
    return imagecolorallocate($im, $r, $g, $b);
  }  
  
  public function getPasteCoords($template){
    $default = [40, 40];
    $config = $this->getConfig( $template );
    
    if( !$config ){
      return $default;
    }
    
    if( array_key_exists("left", $config) ){
      $default[0] += (str_replace("px", "", $config["left"]) * 2.7) - 100;
      $default[0] = $default[0] < 0 ? 40 : $default[0];
    }
        
    if( array_key_exists("top", $config) ){
      $default[1] += (str_replace("px", "", $config["top"]) * 2.7) - 100;
      $default[1] = $default[1] < 0 ? 40 : $default[1];
    }    
    
    return $default;
  }  
  
  public function getNumchars($template){
    $default = 45;    
    $config = $this->getConfig( $template );
    
    if( !$config || !array_key_exists("max-width", $config) ){
      return $default;
    }
    
    return ceil( str_replace("px", "", $config["max-width"]) * .06 );
  }
  
  public function getConfig($template){
        
    foreach( $this->jsonConfigs as $cfg ){
      if( $cfg["template"] == $template ){
        return $cfg["styles"];
      }
    }    
    
    return null;
  }
  
  
  public function render($params = []){
    
    $template = $params["selected-template"];
    
    $numChars = $this->getNumchars($template);    
    $lines = str_split($params["added-text"], $numChars);
        
    $font = dirname(__FILE__) . "/../bin/OpenSans-ExtraBold.ttf";
    $templateImg = imagecreatefromjpeg( dirname(__FILE__) . "/templates/" . $template );
    
    $im = imagecreatetruecolor(1875, 1275);
    
    $textColor = $this->getTextColor( $im, $template );    
    $transparentWhite = imagecolorallocatealpha($im, 0, 0, 0, 90);
    
    imagesavealpha($im, true);
    imagefill($im, 0, 0, $transparentWhite);
    
    $widths = [];
    $height = 0;
    
    foreach( $lines as $text ){
      $coords = imagettftext($im, 60, 0, 50, $height + 100, $textColor, $font, $text);
    
      $height = $coords[3]; 
      $widths[] = $coords[2] + 60;
    }
    
    $height += 60;
    $width = max($widths);
    
    $pasteCoords = $this->getPasteCoords($template);           
    
    imagecopy($templateImg, $im, $pasteCoords[0], $pasteCoords[1], 0, 0, $width, $height);    
    
    header('Content-Type: image/png');
    imagepng($templateImg);
    imagedestroy($templateImg);    
    
  }
  
}

$template = "farva.jpg";
$text = "I love you like a fat kid loves cake...I love you like a fat kid loves cake..";
$text = "I love you!";

$rm = new RenderImage();
$rm->render( ["added-text" => $text, "selected-template" => $template] );