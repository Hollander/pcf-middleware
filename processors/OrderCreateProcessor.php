<?php

include_once( dirname(__FILE__) . "/../includes/Log.php");
include_once( dirname(__FILE__) . "/FileProcessor.php");

class OrderCreateProcessor extends FileProcessor {

  public function __construct($script) {
    parent::__construct($script);
    $this->fetchJobs();
  }

  public function processJob($job) {
    $success = false;
    $message = json_decode( $job['message'], true );

    Log::debug("Order to create: ".$message['file']);

    $content = file_get_contents($message['file']);
    $xml = simplexml_load_string($content);
    $data = Helper::toArray($xml);

    $_orders = [];

    foreach($data['order'] as $order) {
      $_orders[] = [
        'id' => $order['current-order-no'],
        'date' => $order['order-date'],
        'email' => $order['customer']['customer-email'],
        'payload' => json_encode($order)
      ];
    }

    print_r($_orders);


    // IF contact exists with email
    //   FALSE: create contact
    //   TRUE: Fetch contact notes
    // IF note exists for order #
    //   FALSE: create note
    //   TRUE: Do nothing

    // submit order
    // $response = Helper::soapCommandOracle('order', $order_request);
    //
    // if ($response) {
    //   parent::updateJobStatus($job['id'], "Order submitted to Oracle");
    // } else {
    //   $success = false;
    // }

    return $success;
  }

}

?>
