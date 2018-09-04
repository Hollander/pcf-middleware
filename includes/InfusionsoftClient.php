<?php

include_once( dirname(__FILE__) . "/Config.php");
include_once( dirname(__FILE__) . "/Log.php");
include_once( dirname(__FILE__) . "/Helper.php");
include_once( dirname(__FILE__) . "/Data.php");

class InfusionsoftClient {

    var $crm;
    var $token;
    var $user;

    public function __construct($options = null) {
      $this->crm = Connections::Instance()->getInfusionsoftConnection();

      // get token from db
      $token = Data::selectFirst( 'config', ['value'], ['id' => 'infusionsoft.token'] );
      if ($token) {
        // refresh token
        $this->crm->setToken(unserialize($token['value']));
        $this->crm->refreshAccessToken();
        $token = $this->crm->getToken();

        // save new token in db
        Data::update( 'config', ['value' => serialize($token)], ['id' => 'infusionsoft.token'] );

        $this->user = Config::Instance()->settings['infusionsoft']['user'];
      }
      else {
        Log::error("Infusionsoft token missing from db config");
      }
    }

    public function getContactByEmail($email) {
      $contacts = $this->crm->contacts()->where('email', $email)->get();
      echo(print_r($contacts,true)."<hr/>");
      if ($contacts && !empty($contacts) && array_key_exists('items', $contacts) && !empty($contacts['items'])) {
        return $contacts['items'][0];
      }
      else {
        Log::commit('infusionsoft', "Contact does not exist for $email");
        return false;
      }
    }

    public function createOrUpdateContact($email, $data) {
      $payload = [
        'email_addresses' => [
          [
            'email' => $email,
            'field' => 'EMAIL1'
          ]
        ],
        'opt_in_reason' => "Customer opted-in through website",
        'duplicate_option' => 'Email'
      ];
      foreach ($data as $key => $value) {
        $payload[$key] = $value;
      }

      $contact = $this->crm->contacts()->create($payload,true);
      return $contact;
    }

    public function addNoteToContact($contact, $title, $note) {
      $_note = $this->crm->notes()->create([
         'body' => $note,
         'title' => $title,
         'contact_id' => $contact,
         'user_id' => $this->user
      ]);
      return $_note;
    }

}

?>
