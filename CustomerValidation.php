<?php

namespace Classes\Base;

use Classes\Database\DatabaseFactory;
use Classes\AWS\awsS3;
use Couchbase\Exception;
use PDO;
use GuzzleHttp\Client;
use Webpatser\Uuid\Uuid;

include_once(dirname(__FILE__) . '/../../config.php');


class CustomerValidation
{

    private $lastName;
    private $NormalizedAddress;
    private $AccountNumber;
    private $zipCode;
    private $physcialAddress;
    private $tableName = TABLE_NAME;

    public function __construct($lastName = '', $NormalizedAddress = '', $AccountNumber = '', $physcialAddress = '', $zipCode = '')
    {
        $this->lastName = $lastName;
        $this->NormalizedAddress = $NormalizedAddress;
        $this->AccountNumber = $AccountNumber;
        $this->zipCode = $zipCode;
        $this->physcialAddress = $physcialAddress;
    }

    public function validate()
    {

        if (!empty($this->AccountNumer) || $this->AccountNumber != '') {

            error_log("Account Matched\n", 3, dirname(__FILE__) . "/../../logs/RAAS.log");

            $sql = "select distinct AccountNumber, CustomerId, LastName, Normalized_Address , ServiceType
                    from $this->tableName
                    where AccountNumber=:accountNumber and ServiceType='ELE'";


            error_log($sql, 3, dirname(__FILE__) . "/../../logs/RAAS.log");


            $sth = DatabaseFactory::RDSConnection()->prepare($sql);
            $sth->bindParam(":accountNumber", $this->AccountNumber, PDO::PARAM_STR);
            $sth->execute();
            $customer = $sth->fetchObject();

        } else {

            error_log("PhysicalAddresses Matched\n", 3, dirname(__FILE__) . "/../../logs/RAAS.log");

            error_log("address:".$this->physcialAddress."\n\n", 3, dirname(__FILE__) . "/../../logs/RAAS.log");
            error_log("lname".$this->lastName."\n\n", 3, dirname(__FILE__) . "/../../logs/RAAS.log");
            
	    $sql = "select distinct AccountNumber, CustomerId, LastName, max(CustomerId), Normalized_Address , ServiceType
                            from  $this->tableName
                            where LastName=:lastName 
                            and concat_ws(' ',PhysicalAddress1,PhysicalAddress2)=:physcialAddress 
                            and PhysicalZip=:zipcode
                            and ServiceType='ELE'
                            order by CustomerId desc";

            $sth = DatabaseFactory::RDSConnection()->prepare($sql);
            $sth->bindParam(":lastName", $this->lastName, PDO::PARAM_STR);
            $sth->bindParam(":physcialAddress", $this->physcialAddress, PDO::PARAM_STR);
            $sth->bindParam(":zipcode", $this->zipCode, PDO::PARAM_STR);
            $sth->execute();
            $customer = $sth->fetchObject();

            error_log($sql, 3, dirname(__FILE__) . "/../../logs/RAAS.log");

            if ($customer->CustomerId == '' && $this->NormalizedAddress != '') {

                error_log("NormalizedAddress Matched\n", 3, dirname(__FILE__) . "/../../logs/RAAS.log");

                $sql = "select distinct AccountNumber, CustomerId, LastName, max(CustomerId), Normalized_Address , ServiceType
                            from  $this->tableName
                            where LastName=:lastName 
                            and Normalized_Address=:normalizedAddress 
                            and ServiceType='ELE'
                            and Normalized_Address!=''
                            order by CustomerId desc";

                error_log($sql, 3, dirname(__FILE__) . "/../../logs/RAAS.log");

                $sth = DatabaseFactory::RDSConnection()->prepare($sql);
                $sth->bindParam(":lastName", $this->lastName, PDO::PARAM_STR);
                $sth->bindParam(":normalizedAddress", $this->NormalizedAddress, PDO::PARAM_STR);
                $sth->execute();
                $customer = $sth->fetchObject();

            }
        }


        error_log(print_r($customer, true), 3, dirname(__FILE__) . "/../../logs/RAAS.log");

        if ($customer) {
            return $customer;
        }
    }
}
