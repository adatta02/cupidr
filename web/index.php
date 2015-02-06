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
    return $app['twig']->render('index.html.twig');
})->bind("homepage");

$app->get('/submit', function () use ($app) {
    return $app['twig']->render('done.html.twig');
});

$app->post('/submit', function () use ($app) {

    $data = $app['request']->request->all();

    print_r($data);
    die();

    return $app->redirect('/submit');
})->bind("submit_postcard");

$app->run();