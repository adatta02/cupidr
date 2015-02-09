<?php

require_once __DIR__.'/../vendor/autoload.php';
require_once __DIR__.'/../config.php';

$app = new Silex\Application();

$app["debug"] = true;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.path' => __DIR__.'/../views',
));

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SessionServiceProvider());

$app->get('/', function () use ($app) {

    $images = [];
    foreach( glob(__DIR__ . "/templates/*") as $fn ){

        if( strpos($fn, "_small.jpg") !== false ){
            continue;
        }

        $thumbnail = basename(str_replace(".jpg", "_small.jpg", $fn));
        $images[] = ["image" => basename($fn), "thumb" => $thumbnail];
    }

    $chunkedArrays = array_chunk($images, 2);
    $templateJson = file_get_contents( dirname(__FILE__) . "/../bin/templates.json" );
    
    return $app['twig']->render('index.html.twig', ["images" => $chunkedArrays, "templateJson" => $templateJson]);
})->bind("homepage");

$app->get('/submit', function () use ($app) {
    return $app['twig']->render('done.html.twig');
});

$app->post('/submit', function () use ($app) {

    $data = $app['request']->request->all();
    $pdo = new \PDO(Config::$PDO_CONFIG["dsn"], Config::$PDO_CONFIG["username"], Config::$PDO_CONFIG["password"]);
    
    $stmt = $pdo->prepare("INSERT INTO card (email, form_fields, is_sent) VALUES (:email, :fields, false)");
    $stmt->execute(["email" => $data["email"], "fields" => json_encode($data)]);
    
    return $app->redirect('/submit');
})->bind("submit_postcard");

$app->run();