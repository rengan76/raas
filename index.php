<?php

require '../vendor/autoload.php';

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Classes\Database\DatabaseFactory;
use Classes\AWS\awsS3;
use Classes\controllers\CustomerController;
use Classes\controllers\MAARASController;

$app = new \Slim\App([
    'settings' => [
        'displayErrorDetails' => true
    ]
]);

header("Access-Control-Allow-Origin: *");

//POST Method

$app->post('/validateCustomer', function (Request $request, Response $response, array $args) {

    $data = $request->getBody()->getContents();
    $res = json_decode($data, true);

    //error_log("You messed up!", 3, dirname(__FILE__)."/../logs/RAAS.log");

    if (isset($res) && $res['program'] === 'MADHRAAS') {
        $finalRes = MAARASController::customerValidation($res);
    } else {
        $finalRes = MAARASController::customerValidation($_POST);
    }

    return $this->response->withJson($finalRes, 200);


});


$app->get('/resetCoupons', function (Request $request, Response $response, array $args) {

    $sql = "update coupon_codes set is_issued=0";
    $sth = DatabaseFactory::RDSConnection()->query($sql);
    $sth->execute();

    $dsql = "truncate coupon_customer_participation";
    $dsth = DatabaseFactory::RDSConnection()->query($dsql);
    $dsth->execute();

    $res =[
      'status' => 1,
      'Message' => 'successfully resetted'
    ];

    return $this->response->withJson($res, 200);
});


/// Testing methods
/*
$app->get('/getFieldNames', function (Request $request, Response $response, array $args) {

    $q = DatabaseFactory::RDSConnection()->query("DESCRIBE CUSTOMERS");
    $fieldnames = $q->fetchAll();
    return $this->response->withJson($fieldnames, 200);
});


$app->get('/getAllCustomers', function ($id) use ($app) {
    $customers = CustomerController::getAllCustomers();
    return $this->response->withJson($customers, 200);
});


$app->get('/getCustomer/{id}', function ($request, $response, $args) {
    $customer = CustomerController::getCustomerByFirstName($args['id']);
    return $this->response->withJson($customer, 200);
});

$app->get('/deleteCustomer/{id}', function ($request, $response, $args) {
    $customer = CustomerController::deleteAccountNumber($args['id']);
    if ($customer == 'true') {
        return 'Successfully Deleted';
    } else {
        return 'Something went wrong';
    }
});


//Payload check from  nodejs
$app->post('/getAdressValidate', function ($request, $response, $args) {
    $data = $request->getBody()->getContents();
    $res = json_decode($data, true);

    if ($res) {
        $finalRes = CustomerController::guzzleGetFromCustomerValidationAPI($res);
    } else {
        $finalRes = CustomerController::guzzleGetFromCustomerValidationAPI($_POST);
    }

    return ($finalRes);
});
*/


$app->run();
