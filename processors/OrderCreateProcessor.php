<?php

include_once( dirname(__FILE__) . "/../includes/Log.php");
include_once( dirname(__FILE__) . "/../includes/InfusionsoftClient.php");
include_once( dirname(__FILE__) . "/FileProcessor.php");

class OrderCreateProcessor extends FileProcessor {

  private $infusionsoft;

  public function __construct($script) {
    parent::__construct($script);
    $this->infusionsoft = new InfusionsoftClient();
    $this->fetchJobs();
  }

  public function processJob($job) {
    $success = true;
    $message = json_decode( $job['message'], true );

    Log::debug("Orders to create: ".$message['file']);

    $content = file_get_contents($message['file']);
    $xml = simplexml_load_string($content);
    $data = Helper::toArray($xml);

    if ($data['order'] && array_key_exists('current-order-no', $data['order'])) {
      $this->processOrder($data['order']);
    }
    else {
      foreach($data['order'] as $order) {
        $this->processOrder($order);
      }
    }

    parent::updateJobStatus($job['id'], $success ? "Orders submitted to Infusionsoft" : "Something went wrong submitting orders to Infusionsoft");
    return $success;
  }

  private function processOrder($order) {
    $this->createNote(
      $this->getContact($order),
      $this->buildOrder($order),
      $order
    );
  }

  private function buildOrder($order) {
    $_order = [
      'id' => $order['current-order-no'],
      'date' => $order['order-date'],
      'email' => $order['customer']['customer-email'],
      'payload' => [
        "Order ID: {$order['current-order-no']}, Customer ID: {$order['customer']['customer-no']}",
        "Order Date: ".date('M-d-Y', strtotime($order['order-date'])),
        "Shipped: {$order['shipments']['shipment']['status']['shipping-status']}",
        "Product Total: {$order['totals']['merchandize-total']['gross-price']}",
        "Shipping: {$order['totals']['shipping-total']['gross-price']}",
        "Product + Shipping Total: {$order['totals']['order-total']['gross-price']}"
      ]
    ];
    $_order['payload'] = array_merge($_order['payload'], $this->getItems($order));

    return $_order;
  }

  private function getItems($order) {
    if ($order['product-lineitems']['product-lineitem'] && array_key_exists('product-id', $order['product-lineitems']['product-lineitem'])) {
      return $this->getItem($order['product-lineitems']['product-lineitem']);
    }
    else {
      $_items = [];
      foreach($order['product-lineitems']['product-lineitem'] as $_item) {
        $_items[] = "\n".implode("\n", $this->getItem($_item))."\n";
      }
      return $_items;
    }
  }

  private function getItem($item) {
    return [
      "Item: {$item['product-id']}: {$item['product-name']}",
      "Quantity Ordered: {$item['quantity']}, Quantity Shipped: {$item['shipping-lineitem']['quantity']}",
      "Unit Price: {$item['base-price']}, Item Total: {$item['gross-price']}",
      array_key_exists('custom-attributes', $item) ? "Custom attributes: ".json_encode($item['custom-attributes']) : ""
    ];
  }

  private function getContact($order) {
    return $this->infusionsoft->createOrUpdateContact($order['customer']['customer-email'], [
      'given_name' => $order['customer']['billing-address']['first-name'],
      'family_name' => $order['customer']['billing-address']['last-name'],
      'addresses' => [
        [
          'field' => 'BILLING',
          'country_code' => ($order['customer']['billing-address']['country-code'] == "US" ? "USA" : $order['customer']['billing-address']['country-code']),
          'line1' => $order['customer']['billing-address']['address1'],
          'locality' => $order['customer']['billing-address']['city'],
          'region' => Helper::getStateNameWithAbbreviation($order['customer']['billing-address']['state-code']),
          'postal_code' => $order['customer']['billing-address']['postal-code']
        ]
      ]
    ]);
  }

  private function createNote($_contact, $_order, $order) {
    try {
      $note = $this->infusionsoft->addNoteToContact(
        $_contact->id,
        "Order #{$_order['id']} placed on ".date('M-d-Y', strtotime($order['order-date'])),
        implode("\n",$_order['payload'])."\n\n\n".json_encode($order)
      );
      if (!$note) {
        Log::commit('infusionsoft', "Error creating note for contact: {$_order['email']}");
      }
    } catch (Exception $e) {
      Log::error($e->getMessage());
    }
  }

}

?>
