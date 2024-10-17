<?php
$config = require 'config.php';
require_once('vendor/autoload.php');

use PhpXmlRpc\Client;
use PhpXmlRpc\Request;
use PhpXmlRpc\Value;

// Set up the Odoo server details
$url_common = $config['url_common'];
$url_object = $config['url_object'];
$db = $config['db'];
$username = $config['username'];
$password = $config['password'];
$odoo_url = $config['odoo_url'];
// Create XML-RPC client for authentication
$client_common = new Client($url_common);
$client_object = new PhpXmlRpc\Client($url_object);
// Create an authentication request
$authRequest = new Request('authenticate', [
    new Value($db, 'string'),          
    new Value($username, 'string'),    
    new Value($password, 'string'),    
    new Value([], 'struct')            
]);

// Send the authentication request
$response = $client_common->send($authRequest);

if (!$response->faultCode()) {
    $uid = $response->value()->scalarval();

    if (!is_numeric($uid)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID returned']);
        exit();
    }

    // Step 2: Get product data from POST request
    $product_data = $_POST;

   /*  if (empty($product_data['product_name']) || empty($product_data['product_id'])) {
        echo json_encode(['status' => 'error', 'message' => 'Product name and ID are required']);
        exit();
    } */

    // Define the product name and ID from the input
    $productName = $product_data['product_name'];
    $productId = $product_data['product_id'];

    // Step 3: Search for the product in Odoo by default_code (or name if needed)
    //$client_object = new PhpXmlRpc\Client($url_object);
    $models = new PhpXmlRpc\Client("$odoo_url/xmlrpc/2/object");
    $search_domain = array(); // No specific search domain for products unless needed

    // Define fields to read for the products
    $fields = array(
        new PhpXmlRpc\Value('id', "string"),
        new PhpXmlRpc\Value('name', "string"),
        new PhpXmlRpc\Value('default_code', "string"),  // Product SKU
        new PhpXmlRpc\Value('list_price', "string"),    // Sale price
        new PhpXmlRpc\Value('qty_available', "string"), // Quantity on hand
    );
    
    $search_msg = new PhpXmlRpc\Request('execute_kw', array(
        new PhpXmlRpc\Value($db, "string"),
        new PhpXmlRpc\Value($uid, "int"),
        new PhpXmlRpc\Value($password, "string"),
        new PhpXmlRpc\Value('product.product', "string"), // Odoo model for products
        new PhpXmlRpc\Value('search_read', "string"),
        new PhpXmlRpc\Value(array(new PhpXmlRpc\Value($search_domain, "array")), "array"),
        new PhpXmlRpc\Value(array('fields' => new PhpXmlRpc\Value($fields, "array")), "struct")
    ));

    $product_result = $models->send($search_msg);
    if ($product_result->faultCode()) {
        die('Error: ' . $product_result->faultString());
    }

    // Step 3: Insert Product Data into MySQL
    $products = $product_result->value()->scalarval();
	$formatted_products = [];
    foreach ($products as $product) {
        $product_struct = $product->scalarval(); // Get the struct array
        $product_id = $product_struct['id']->scalarval();
        $product_name = $product_struct['name']->scalarval();

        // Add to formatted array
        $formatted_products[] = [
            'id' => $product_id,
            'name' => $product_name
        ];
    }
	$existing_product = array_search($productName, array_column($formatted_products, 'name'));
	if ($existing_product !== false) {
        echo json_encode(['status' => 'skipped', 'message' => 'product already exists']);
        exit();
    }
	
    // Step 5: Prepare data for new product creation
	$productData = [
		'name' => new Value($productName, 'string'),
		'list_price' => new Value($product_data['price'], 'double'),
		'default_code' => new Value($productId, 'string'),
		'sale_ok' => new Value(true, 'boolean'),
		'active' => new Value($product_data['status'] == 'A', 'boolean'),
		'description' => isset($product_data['full_description']) ? 
			new Value($product_data['full_description'], 'string') : 
			new Value('', 'string'),
		'categ_id' => isset($product_data['main_category']) && !empty($product_data['main_category']) ? 
			new Value($product_data['main_category'], 'int') : 
			new Value(1, 'int'), // Default to category ID 1 if not provided
		'qty_available' => isset($product_data['quantity']) ? 
			new Value($product_data['quantity'], 'double') : 
			new Value(0, 'double'),
		'weight' => isset($product_data['weight']) ? 
			new Value($product_data['weight'], 'double') : 
			new Value(0.0, 'double'),
	];
    $productStruct = new Value($productData, 'struct');
	// Step 6: Create the product if it doesn't exist
	$createRequest = new Request('execute_kw', [
		new Value($db, 'string'),               // Database name
		new Value($uid, 'int'),                 // User ID
		new Value($password, 'string'),         // Password
		new Value('product.product', 'string'), // Model
		new Value('create', 'string'),          // Method
		new Value([$productStruct], 'array')      // Product data wrapped as an array
	]);

	$createResponse = $client_object->send($createRequest);

	if (!$createResponse->faultCode()) {
		$newProductId = $createResponse->value()->scalarval();
		echo json_encode(['status' => 'success', 'product_id' => $newProductId]);
	} else {
		echo json_encode(['status' => 'error', 'message' => $createResponse->faultString()]);
	}

}