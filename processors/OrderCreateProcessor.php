<?php

include_once( dirname(__FILE__) . "/../includes/Log.php");
include_once( dirname(__FILE__) . "/../includes/Twig/Autoloader.php");
include_once( dirname(__FILE__) . "/FileProcessor.php");

class OrderCreateProcessor extends FileProcessor {

  private $twig;

  public function __construct($script) {
    parent::__construct($script);
    $this->fetchJobs();

	  Twig_Autoloader::register();
    $loader = new Twig_Loader_Filesystem(dirname(__FILE__) . "/../templates");
	  $this->twig = new Twig_Environment($loader, array( ));
  }

  public function processJob($job) {
    $success = true;
    $message = json_decode( $job['message'], true );

    Log::debug("Order to create: ".$message['file']);

    $content = file_get_contents($message['file']);
    $content = json_decode($content, true);

    $customer_request = $this->twig->render('customer.xml', $content);

    // submit customer
    $response = Helper::soapCommandOracle('customer', $customer_request);
    if ($response) {
        parent::updateJobStatus($job['id'], "Customer submitted to Oracle");

        // fetch ebs ids from xml response
        $doc = new DOMDocument();
        $doc->loadXML($response);
        $parent = $doc->documentElement;
        $body = $parent->getElementsByTagName('Body')->item(0);
        $out = $parent->getElementsByTagName('OutputParameters')->item(0);
        $contacts = $out->getElementsByTagName('X_CUST_CONTACT_TBL')->item(0)->getElementsByTagName('X_CUST_CONTACT_TBL_ITEM');
        $addresses = $out->getElementsByTagName('X_CUST_ADDRESS_TBL')->item(0)->getElementsByTagName('X_CUST_ADDRESS_TBL_ITEM');

        $content['ebs'] = array(
            "billing"   => array(
                "oracleebs_contact_party_id"  => $contacts->item(0)->getElementsByTagName('X_EBS_CONTACT_PARTY_ID')->item(0)->nodeValue,
                "oracleebs_bill_to_site_id"   => $addresses->item(0)->getElementsByTagName('X_EBS_BILL_SITE_USE_ID')->item(0)->nodeValue
            ),
            "shipping"   => array(
                "oracleebs_contact_party_id" => $contacts->item(1)->getElementsByTagName('X_EBS_CONTACT_PARTY_ID')->item(0)->nodeValue,
                "oracleebs_ship_to_site_id"  => $addresses->item(1)->getElementsByTagName('X_EBS_SHIP_SITE_USE_ID')->item(0)->nodeValue
            ),
            "oracleebs_cust_account_id" => $out->getElementsByTagName('X_EBS_CUST_ACCOUNT_ID')->item(0)->nodeValue
        );

        $order_request = $this->twig->render('order.xml', $content);

        // submit order
        $response = Helper::soapCommandOracle('order', $order_request);

        if ($response) {
          parent::updateJobStatus($job['id'], "Order submitted to Oracle");


        } else {
          $success = false;
        }
    } else {
      $success = false;
    }


    return $success;
  }

}

?>
