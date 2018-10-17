<?php

namespace Classes\AWS;

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class awsS3
{

    private $bucket = 'raas-assets-dev';
    private $keyname = 'email-template/PSENEST.html';
    public $s3;
    CONST FROM_EMAIL = 'rramanujam@efi.org';
    CONST TO_SUBJECT = 'MARAAS instant rebate offer';
    private $emailVars =[
        '%FIRSTNAME%' => 'firstname',
        '%LASTNAME%' => 'lastname',
        '%COUPONCODE%'=> 'couponCode',
        '%URL%' => 'rebateUrl1'
    ];

    public function __construct()
    {

        $this->s3 = new S3Client([
            'version' => 'latest',
            'region' => 'us-east-1'
        ]);
    }

    public function getData()
    {

        try {
            $result = $this->s3->getObject([
                'Bucket' => $this->bucket,
                'Key' => $this->keyname
            ]);

            header("Content-Type: {$result['ContentType']}");
            return $result['Body'];
        } catch (S3Exception $e) {
            echo $e->getMessage() . PHP_EOL;
        }

    }

    public function sendMail($res, $contents)
    {
        $firstname = ($res['firstName']) ? $res['firstName'] :$res['firstname'];
        $lastname = ($res['lastName']) ? $res['lastName'] :$res['lastname'];

        $contents = str_replace("%FIRSTNAME%", $firstname, $contents);
        $contents = str_replace("%LASTNAME%", $lastname, $contents);
        $contents = str_replace("%URL%", $res['rebateUrl1'], $contents);
        $contents = str_replace("%COUPONCODE%", $res['couponCode'], $contents);

        $to = self::FROM_EMAIL;
        $subject = self::TO_SUBJECT;
        $message = $contents;
        $headers = 'From:'.self::FROM_EMAIL.'' . "\r\n" .
            'Reply-To: '.self::FROM_EMAIL . "\r\nContent-type: text/html";

      //  mail($to, $subject, $message, $headers);
       
    }

}

