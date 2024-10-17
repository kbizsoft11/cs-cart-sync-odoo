<?php
$config = require 'config.php';
require_once('vendor/autoload.php'); 

// Use the correct namespaces
use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;

$url_common = $config['url_common'];
$url_object = $config['url_object']; // Object endpoint for execute_kw
$db = $config['db'];
$username = $config['username'];
$password = $config['password'];
$odoo_url = $config['odoo_url'];

$client_common = new Client($url_common);

$authRequest = new Request('authenticate', [
    new Value($db, 'string'),
    new Value($username, 'string'),
    new Value($password, 'string'),
    new Value([], 'struct') 
]);

$response = $client_common->send($authRequest);
if (!$response->faultCode()) {
    $uid = $response->value()->scalarval();
    if (!is_numeric($uid)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID returned']);
        exit();
    }
    
    $customer_data = $_POST;
    if (empty($customer_data['user_data']['firstname']) || empty($customer_data['user_data']['lastname']) || empty($customer_data['user_data']['email'])) {
        echo json_encode(['status' => 'error', 'message' => 'Customer first name, last name, and email are required']);
        exit();
    }
    
    $full_name = $customer_data['user_data']['firstname'] . ' ' . $customer_data['user_data']['lastname'];
	$contactEmail = $customer_data['user_data']['email'];
	$models = new PhpXmlRpc\Client("$odoo_url/xmlrpc/2/object");
    $search_domain = array(); 
    $fields = array(
        new PhpXmlRpc\Value('email', "string"),  
    );
	
	$search_msg = new PhpXmlRpc\Request('execute_kw', array(
        new PhpXmlRpc\Value($db, "string"),
        new PhpXmlRpc\Value($uid, "int"),
        new PhpXmlRpc\Value($password, "string"),
        new PhpXmlRpc\Value('res.partner', "string"), 
        new PhpXmlRpc\Value('search_read', "string"),
        new PhpXmlRpc\Value(array(new PhpXmlRpc\Value($search_domain, "array")), "array"),
        new PhpXmlRpc\Value(array('fields' => new PhpXmlRpc\Value($fields, "array")), "struct")
    ));

    $contacts_result = $models->send($search_msg);
    if ($contacts_result->faultCode()) {
        die('Error: ' . $contacts_result->faultString());
    }
    $contacts = $contacts_result->value()->scalarval();
	$formatted_contacts = [];
    foreach ($contacts as $contact) {
       $email = $contact->structmem('email')->scalarval();
       $formatted_contacts[] = [
            'email' => $email
        ];
    }
	$existing_contact = array_search($contactEmail, array_column($formatted_contacts, 'email'));
	if($existing_contact !== false) {
        echo json_encode(['status' => 'skipped', 'message' => 'contact already exists']);
        exit();
    }
    //$stateId = getStateIdByName($customer_data['user_data']['s_state']); // Fetch state ID
    $customerData = [
			new Value([
				'name' => new Value($full_name, 'string'),
				'email' => new Value($customer_data['user_data']['email'], 'string'),
				'active' => new Value(true, 'boolean'),
				'customer_rank' => new Value(1, 'int'),
				'street' => new Value($customer_data['user_data']['s_address'], 'string'),
				'city' => new Value($customer_data['user_data']['s_city'], 'string'),
				//'state_id' => new Value($stateId, 'int'), // Ensure this is an integer ID
				'zip' => new Value($customer_data['user_data']['s_zipcode'], 'string'),
			], 'struct')
	];
    // Prepare the create request on the object endpoint
    $client_object = new Client($url_object);
    $createRequest = new Request('execute_kw', [
        new Value($db, 'string'),
        new Value($uid, 'int'),
        new Value($password, 'string'),
        new Value('res.partner', 'string'), // Model for customers (res.partner)
        new Value('create', 'string'),  // Method name as string
        new Value($customerData, 'array')  // Customer data wrapped in an array
    ]);

    $createResponse = $client_object->send($createRequest);
    if (!$createResponse->faultCode()) {
        $partnerId = $createResponse->value()->scalarval();
        echo json_encode(['status' => 'success', 'partner_id' => $partnerId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $createResponse->faultString()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => $response->faultString()]);
}





