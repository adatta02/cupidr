<?php

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application();

$app["debug"] = false;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());
$app->register(new Silex\Provider\SwiftmailerServiceProvider());

$encoder = new Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder();
$password = $encoder->encodePassword(Config::$ADMIN_PASSWORD, '');

$app->register(new Silex\Provider\SecurityServiceProvider(), array(
		'security.firewalls' => array(
		'admin' => array(
				'pattern' => '^/approve',
				'http' => true,
				'users' => array(
					'admin' => array('ROLE_ADMIN', $password),
				),),
		)		 
));

$app->get('/', function () use ($app) {

    $images = [];
    foreach( glob(__DIR__ . "/templates/*") as $fn ){

        if( strpos($fn, "_small.jpg") !== false || strpos($fn, "postcard_back.png") !== false ){
            continue;
        }

        $thumbnail = basename(str_replace(".jpg", "_small.jpg", $fn));
        $images[] = ["image" => basename($fn), "thumb" => $thumbnail];
    }

    $chunkedArrays = array_chunk($images, 2);
    $templateJson = file_get_contents( dirname(__FILE__) . "/../bin/templates.json" );
        
    return $app['twig']->render('index.html.twig', ["images" => $chunkedArrays, "templateJson" => $templateJson]);
})->bind("homepage");

$app->get('/submitted/{fname}', function ($fname) use ($app) {
    $url = $app['url_generator']->generate("view", ["fname" => $fname]);
    return $app['twig']->render('done.html.twig', ["url" => $url]);
})->bind("submitted");

$app->get('/view/{fname}', function ($fname) use ($app) {        
    return $app['twig']->render('view.html.twig', ["image" => $fname]);
})->bind("view");

$app->get('/approve/{fname}', function ($fname) use ($app) {

    $pdo = new \PDO(Config::$PDO_CONFIG["dsn"], Config::$PDO_CONFIG["username"], Config::$PDO_CONFIG["password"]);
    
    $stmt = $pdo->prepare("SELECT * FROM card WHERE filename = :filename");
    $stmt->execute(["filename" => $fname]);
    
    $rows = $stmt->fetchAll();

    if(count($rows) == 0){
      die("Card not found?");
    }
    
    if($rows[0]["is_sent"]){
    	die("This card has been sent.");
    }
    
    $urls = ["front" => "http://cupidr.setfive.com/rendered/" . $fname, 
             "back" => "http://cupidr.setfive.com/templates/postcard_back.png"];
    
    $formData = json_decode($rows[0]["form_fields"], true);
        
    $rm = new ImageTool(Config::$LOB_LIVE_KEY);
    $result = $rm->sendPostcard($formData["address"]["to"], $formData["address"]["from"], $urls);  
   
    if( count($result["errors"]) == 0 ){
    	$stmt = $pdo->prepare("UPDATE card SET lob_result = :result, is_sent = true WHERE filename = :filename");
    	$stmt->execute(["filename" => $fname, "result" => json_encode($result)]);
    }      
   
    return $app['twig']->render("approve.html.twig", ["result" => print_r($result, true)]);
})->bind("approve");

$app->get('/render/{fname}', function ($fname) use ($app) {
	
	$pdo = new \PDO(Config::$PDO_CONFIG["dsn"], Config::$PDO_CONFIG["username"], Config::$PDO_CONFIG["password"]);
	
	$stmt = $pdo->prepare("SELECT * FROM card WHERE filename = :filename");
	$stmt->execute(["filename" => $fname]);
	
	$rows = $stmt->fetchAll();	
	
	if(count($rows) == 0){
		die("Card not found?");
	}
		
	$filename = dirname(__FILE__) . "/rendered/" . $rows[0]["filename"];
	$data = json_decode($rows[0]["form_fields"], true);
	
	$rm = new ImageTool();
	$rm->render( ["added-text" => $data["added-text"], "selected-template" => $data["selected-template"]], $filename );

	header('Content-Type: image/png');
	return $app->sendFile( $filename );
});

$app->post('/submit', function () use ($app) {

    $data = $app['request']->request->all();
    
    $fname = sha1(uniqid("", true) . rand(0, 100)) . ".png";
    $filename = dirname(__FILE__) . "/rendered/" . $fname;
    
    $rm = new ImageTool();
    $rm->render( ["added-text" => $data["added-text"], "selected-template" => $data["selected-template"]], $filename );    
    
    $pdo = new \PDO(Config::$PDO_CONFIG["dsn"], Config::$PDO_CONFIG["username"], Config::$PDO_CONFIG["password"]);
    
    $stmt = $pdo->prepare("INSERT INTO card (email, form_fields, filename, is_sent) VALUES (:email, :fields, :filename, false)");
    $stmt->execute(["email" => $data["email"], "filename" => $fname, "fields" => json_encode($data)]);
    
    $viewUrl = $app['url_generator']->generate("view", ["fname" => $fname], true);
    $approveUrl = $app['url_generator']->generate("approve", ["fname" => $fname], true);
    
$email = "Hi there-

We've queued your card! We'll review it and send it within the next few hours.

Want to relive your masterpiece? You can view it online at $viewUrl

xoxo

-The cupidr team";
    
    $userMessage = \Swift_Message::newInstance()
                ->setSubject('Cupidr: Your Valentines day postcard')
                ->setFrom( ['cupidr@setfive.com' => 'The cupidr team'] )
                ->setTo( [ $data["email"] ] )
                ->setBody( $email );                           
    
    $email = "Hey team,
    
View URL: $viewUrl
Approve URL: $approveUrl
Form:\n" . print_r($data, true);

    $message = \Swift_Message::newInstance()
                ->setSubject('Cupidr: Approve card')
                ->setFrom( ['cupidr@setfive.com' => 'The cupidr team'] )
                ->setTo( [ "contact@setfive.com" ] )
                ->setBody( $email );

    if(1){
      $app['mailer']->send($userMessage);
      $app['mailer']->send($message);    
    }
    
    $url = $app['url_generator']->generate("submitted", ["fname" => $fname]);    
    return $app->redirect($url);
    
})->bind("submit_postcard");

$app->run();