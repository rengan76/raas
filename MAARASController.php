<?php

namespace Classes\controllers;

use Classes\Base\CustomerValidation;
use Classes\Database\DatabaseFactory;
use Classes\AWS\awsS3;
use Couchbase\Exception;
use PDO;
use GuzzleHttp\Client;
use Webpatser\Uuid\Uuid;

include_once(dirname(__FILE__) . '/../../config.php');

class MAARASController
{

    private static $tableName = TABLE_NAME;

    public static function customerValidation($res)
    {

        $originalArray = $res;
        $accountNo = ($res['AccountNumber']) ? $res['AccountNumber'] : $originalArray['accountNumber'];
        $lastName = ($res['lastname']) ? $res['lastname'] : '';
        $address = ($res['normalizedAddress']) ? $res['normalizedAddress'] : '';
        $physcialAddress = ($originalArray['address']) ? $originalArray['address'] : '';
        $zipCode = ($originalArray['zipcode']) ? $originalArray['zipcode'] : '';

        $resp = self::getNormalizedAddress($physcialAddress, $zipCode);
        $newAddress = $resp[0]['delivery_line_1'] . ' ' . ($resp[0]['delivery_line_2'] ? $resp[0]['delivery_line_2'] . '' : '') . $resp[0]['last_line'];
       // $physcialAddress = $resp[0]['delivery_line_1'] . ' ' . ($resp[0]['delivery_line_2'] ? $resp[0]['delivery_line_2'] . '' : '');

        error_log(print_r($orginalArray, true), 3, dirname(__FILE__) . "/../../logs/RAAS.log");
        error_log(print_r($physicalAddress, true), 3, dirname(__FILE__) . "/../../logs/RAAS.log");
        error_log(print_r($newaddress, true), 3, dirname(__FILE__) . "/../../logs/RAAS.log");
        
        $data = (array)self::set($lastName, $newAddress, $accountNo, $physcialAddress, $zipCode);

        error_log("Data from validated customer\n", 3, dirname(__FILE__) . "/../../logs/RAAS.log");
        error_log(print_r($data, true), 3, dirname(__FILE__) . "/../../logs/RAAS.log");

        $accountNo = ($res['AccountNumber']) ? $res['AccountNumber'] : $data['AccountNumber'];

        $sql = "SELECT * FROM " . self::$tableName . " WHERE AccountNumber=:id";
        $sth = DatabaseFactory::RDSConnection()->prepare($sql);
        $sth->bindParam(":id", $accountNo, PDO::PARAM_STR);
        $sth->execute();
        $customerInfo = (array)$sth->fetchObject();
        $customerData = json_encode($customerInfo);

        //print_r($resp);
        //  print_r($customerInfo);

        error_log("Data from Normaized Smarty address\n", 3, dirname(__FILE__) . "/../../logs/RAAS.log");
        error_log(print_r($res, true), 3, dirname(__FILE__) . "/../../logs/RAAS.log");

        $newRes = [
            'accountNumber' => $accountNo,
            'program' => $originalArray['program'],
            'token' => $originalArray['token'],
            'sms' => $originalArray['sms'],
            'normalizedAddress' => $data['Normalized_Address'],
            'lastname' => $data['lastname'],
            'CustomerInfo' => $customerData,
            'CustomerId' => $data['CustomerId']
        ];

        // print_r($newRes);

        if (isset($newRes['CustomerId']) && $newRes['CustomerId'] != '') {
            return self::postData($newRes);
        } else {

            return [
                "Message" => "No Match Found",
                "status" => 0,
            ];
        }

    }


    public static function getNormalizedAddress($add, $zip)
    {
        $client = new Client();
        $url = SMARTY_STREET_URL . 'street=' . $add . '&zipcode=' . $zip;

        try {
            $response = $client->get($url);
        } catch (Exception $e) {
            echo $e;
        }

        $res = $response->getBody()->getContents();
        $res = json_decode($res, true);

        return $res;

    }

    public static function set($lastName, $NormalizedAddress, $AccountNumber, $physcialAddress, $zipCode)
    {

        $customer = new CustomerValidation($lastName, $NormalizedAddress, $AccountNumber, $physcialAddress, $zipCode);
        $validate = $customer->validate();
        if ($validate) {
            return $validate;
        } else {
            return null;
        }
    }


    public static function postData($res)
    {

        $cn_service_type = "G";
        $flag = '';

        if (isset($res['heatingType']) && $res['heatingType'] === 'Electric') {
            $cn_service_type = "E";
        }

        $accountNumber = ($res['accountNumber']) ? $res['accountNumber'] : '';
        $lname = ($res['lastname']) ? $res['lastname'] : '';
        $lastName = ($res['lastName']) ? $res['lastName'] : $lname;
        $token = ($res['token']) ? $res['token'] : '';
        $sms = ($res['sms']) ? $res['sms'] : '';
        $customer_data = ($res['CustomerInfo']) ? $res['CustomerInfo'] : '';
        $customerId = ($res['CustomerId']) ? $res['CustomerId'] : '';


        $sqlWebsite = "SELECT website_id,name from websites where website_id=1";
        $sthw = DatabaseFactory::RDSConnection()->query($sqlWebsite);
        $roww = $sthw->fetchObject();

        $sqlp = "SELECT promotion_id from promotions where promotion_id=1";
        $sthp = DatabaseFactory::RDSConnection()->query($sqlp);
        $rowp = $sthp->fetchObject();

        $program = $rowp->promotion_id;

        if (isset($res['websiteId']) || $res['websiteId']) {
            $websiteId = $res['websiteId'];
        } else {
            $websiteId = $roww->website_id;
        }

        if ($res != '' && isset($res)) {

            $flag = 1;

            if (($cn_service_type == "G" and $res['heatingType'] == "Electric") OR
                ($cn_service_type == "E" and $res['heatingType'] == "Natural Gas")) {
                $status = "3";
            } else {

                $participation_count = 0;

                $sql = "select coupon_customer_participation.customer_id, websites.name, coupon_codes.coupon_code, 
                    promotions.discount_amount , coupon_customer_participation.uuid
                    from  coupon_customer_participation left join coupon_codes 
                    on coupon_codes.coupon_id = coupon_customer_participation.coupon_id
                    left join websites on coupon_customer_participation.website_id = websites.website_id 
                    left join promotions on promotions.promotion_id = coupon_customer_participation.program_id
                    where coupon_customer_participation.account_number =:cn_account_no and coupon_customer_participation.website_id = :websiteID";

                $sth1 = DatabaseFactory::RDSConnection()->prepare($sql);
                $sth1->bindValue(":cn_account_no", $accountNumber);
                $sth1->bindValue(":websiteID", $websiteId);
                $sth1->execute();
                $participateRows = $sth1->fetchObject();

                if ($participateRows) {
                    foreach ($participateRows as $row) {
                        $participation_count += 1;
                    }
                }

                if ($participation_count > 0) {

                    $finalRes = [
                        "status" => "2",
                        "uuid" => $participateRows->uuid,
                        "rebateUrl1" => "",
                        "rebateUrl2" => "",
                        // "couponCode" => substr($participateRows->coupon_code, strpos($participateRows->coupon_code, '47'), 15),
                        "couponCode" => $participateRows->coupon_code,
                        "token" => $token,
                        "program" => $program,
                        "rebateDiscountAmount" => $participateRows->discount_amount,
                        "sms" => $sms,
                        "Message" => "Coupon Already Issued"
                    ];
                } else {

                    $uuidSql = "SELECT uuid() as uid";
                    $sthuuid = DatabaseFactory::RDSConnection()->query($uuidSql);
                    $row = $sthuuid->fetchObject();

                    $countCouponsSql = "select count(*) as count from coupon_codes where is_issued =0";
                    $csth = DatabaseFactory::RDSConnection()->query($countCouponsSql);
                    $crow = $csth->fetchObject();
                    $count = $crow->count;

                    if ($count > 0) {

                        $insQuery = "INSERT INTO coupon_customer_participation (website_id,account_number,coupon_id, uuid, program_id, customer_data, customer_id) 
                                        VALUES (:websiteId, :cn_account_nbr,(SELECT n.coupon_id FROM coupon_codes n
                                                                                WHERE n.website_id=:websiteId
                                                                                AND n.coupon_id 
                                                                                NOT IN (SELECT p.coupon_id 
                                                                                        FROM coupon_customer_participation p) LIMIT 1), 
                                                                            :uid, :program, :customerData, :customerId)";

                        $sth2 = DatabaseFactory::RDSConnection()->prepare($insQuery);
                        $sth2->bindParam(":cn_account_nbr", $accountNumber, PDO::PARAM_STR);
                        $sth2->bindParam(":websiteId", $websiteId, PDO::PARAM_STR);
                        $sth2->bindParam(":uid", $row->uid);
                        $sth2->bindParam(":program", $program);
                        $sth2->bindParam(":customerData", $customer_data);
                        $sth2->bindParam(':customerId', $customerId);
                        $sth2->execute();

                        $findQuery1 = "select coupon_customer_participation.account_number, websites.name, coupon_codes.coupon_code, 
                                    promotions.discount_amount , coupon_customer_participation.uuid,
                                    promotions.target_url1, promotions.target_url2
                                    from  coupon_customer_participation left join coupon_codes 
                                    on coupon_codes.coupon_id = coupon_customer_participation.coupon_id
                                    left join websites on coupon_customer_participation.website_id = websites.website_id 
                                    left join promotions on promotions.promotion_id = coupon_customer_participation.program_id
                                    where coupon_customer_participation.account_number=:cn_account_nbr LIMIT 1";

                        $sth3 = DatabaseFactory::RDSConnection()->prepare($findQuery1);
                        $sth3->bindParam(":cn_account_nbr", $accountNumber, PDO::PARAM_STR);
                        $sth3->execute();
                        $rows = $sth3->fetchObject();

                        $dt = date('Y-m-d H:i:s');
                        $updateSql = "UPDATE coupon_codes set is_issued = 1 where coupon_code =:coupon_code";
                        $upSth = DatabaseFactory::RDSConnection()->prepare($updateSql);
                        $upSth->bindParam(':coupon_code', $rows->coupon_code);
                        //$upSth->bindParm(':dt', $dt);
                        $upSth->execute();

                        if ($rows) {

                            $finalRes = [
                                "rebateUrl1" => $rows->target_url1,
                                "rebateUrl2" => $rows->target_url2,
                                //"couponCode" => substr($rows->coupon_code, strpos($rows->coupon_code, '47'), 15),
                                "couponCode" => $rows->coupon_code,
                                "rebateDiscountAmount" => $rows->discount_amount,
                                "token" => $token,
                                "status" => "200",
                                //"coupon" => substr($rows->coupon_code, strpos($rows->coupon_code, '47'), 15),
                                "coupon" => $rows->coupon_code,
                                "program" => $program,
                                "sms" => $sms,
                                "uuid" => $row->uid

                            ];

                        }

                    } else {

                        $finalRes = [
                            "rebateUrl1" => "",
                            "rebateUrl2" => "",
                            "couponCode" => "",
                            "rebateDiscountAmount" => "",
                            "token" => "",
                            "status" => "3",
                            "coupon" => "",
                            "program" => "",
                            "sms" => "",
                            "uuid" => "",
                            "Message" => "Coupons Ran Out"

                        ];
                    }

                }

            }


        } else {

            $finalRes = [
                "status" => "1",
                "rebateUrl1" => "http://www.efi.org/",
                "rebateUrl2" => "http://www.efi.org",
                "couponCode" => md5("2018-001")
            ];

        }

        return $finalRes;
    }
}
