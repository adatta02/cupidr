<?php

error_reporting(E_ALL);

class ImageTool {
  
  private $jsonConfigs = [];
  private $API_KEY;
  public static $ADDRESS_KEYS = ["name", "address_line1", "address_line2", 
                                 "address_city", "address_state", "address_zip"];
  
  public function __construct($apiKey){
    $this->API_KEY = $apiKey;
    $this->jsonConfigs = json_decode(file_get_contents(dirname(__FILE__) . "/templates.json"), true);  
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
    $default = [80, 80];
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
    $default = 40;    
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
  
  
  public function render($params = [], $filename = null){
    
    $template = $params["selected-template"];
    
    $numChars = $this->getNumchars($template);

    $lines = [];
    $eachLine = explode("<br>", $params["added-text"]);    
        
    foreach( $eachLine as $el ){
    	
    	$words = explode(" ", $el);
    	$totalChars = 0;
    	$chunked = [];
    	
    	foreach( $words as $wr ){
    		
    		if( $totalChars < $numChars && ($totalChars + strlen($wr)) < $numChars ){
    			$totalChars += strlen($wr) + 1;
    			$chunked[] = $wr;
    		}else{
    			$lines[] = join(" " , $chunked);
    			$chunked = [$wr];
    			$totalChars = 0;    			    			
    		}
    		    		
    	}

    	$lines[] = join(" " , $chunked);  	
    }
    
    $font = dirname(__FILE__) . "/OpenSans-ExtraBold.ttf";
    $templateImg = imagecreatefromjpeg( dirname(__FILE__) . "/../web/templates/" . $template );
    
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
    
    if( $filename === null ){
      header('Content-Type: image/png');
      imagepng($templateImg);
    }else{
      imagepng($templateImg, $filename);
      chmod($filename, 0755);
    }
        
    imagedestroy($templateImg);    
  }
  
  
  public function sendPostcard( $to, $from, $urls ){
    
    $params = array_merge( ["template" => 1, "full_bleed" => 1], $urls );
    $addressKeys = ["to" => $to, "from" => $from];
    
    foreach( $addressKeys as $addrKey => $addrVars ){
          
      foreach( self::$ADDRESS_KEYS as $key ){      
        if( array_key_exists($key, $addrVars) ){
          $params[ $addrKey . "[" . $key . "]" ] = $addrVars[$key];
        }              
      }
      
    }        
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.lob.com/v1/postcards/");
    curl_setopt($ch, CURLOPT_USERPWD, $this->API_KEY);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $output = curl_exec($ch);
    $result = json_decode( $output, true );
    
    curl_close($ch);
    
    return $result;
  }    
  
}
