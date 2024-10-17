<?php

$config = require 'config.php';

require_once('vendor/autoload.php'); // Ensure you're using Composer's autoload

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
// Initialize XML-RPC client
$client_common = new Client($url_common);
$client_object = new PhpXmlRpc\Client($url_object); // For object-based interactions
$models = new PhpXmlRpc\Client("$odoo_url/xmlrpc/2/object");

// Step 1: Authenticate the user
$authRequest = new Request('authenticate', [
    new Value($db, 'string'),
    new Value($username, 'string'),
    new Value($password, 'string'),
    new Value(new stdClass(), 'struct') // Empty struct
]);

$response = $client_common->send($authRequest);

// Check if the authentication was successful
if (!$response->faultCode()) {
    $uid = $response->value()->scalarval(); // Get the user ID

    if (!is_numeric($uid)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid user ID returned']);
        exit();
    }

    // Step 2: Get category data from POST request
    $category_data = $_POST;
    if (empty($category_data['category_name'])) {
        echo json_encode(['status' => 'error', 'message' => 'Category name is required']);
        exit();
    }

    // Define the category name from the input
    $categoryName = $category_data['category_name'];
    
    // Define fields to read for the product categories
    $fields = array(
        new PhpXmlRpc\Value('name', "string"),  // Only category name
    );
    
    // Step 3: Search for the category in Odoo using 'search_read'
    $search_msg = new PhpXmlRpc\Request('execute_kw', array(
        new PhpXmlRpc\Value($db, "string"),
        new PhpXmlRpc\Value($uid, "int"),
        new PhpXmlRpc\Value($password, "string"),
        new PhpXmlRpc\Value('product.category', "string"), // Odoo model for product categories
        new PhpXmlRpc\Value('search_read', "string"),
        new PhpXmlRpc\Value(array(new PhpXmlRpc\Value(array(), "array")), "array"), // Empty search domain
        new PhpXmlRpc\Value(array('fields' => new PhpXmlRpc\Value($fields, "array")), "struct")
    ));

    // Send the search request to fetch the category
    $category_result = $models->send($search_msg);
    $categories = $category_result->value()->scalarval();

    // Initialize an array to store formatted categories
    $formatted_categories = [];
    foreach ($categories as $category) {
        $category_struct = $category->scalarval(); // Get the struct array
        $category_id = $category_struct['id']->scalarval();
        $category_name = $category_struct['name']->scalarval();

        // Add to formatted array
        $formatted_categories[] = [
            'id' => $category_id,
            'name' => $category_name
        ];
    }

    // Check if the category already exists in the formatted categories
    $existing_category = array_search($categoryName, array_column($formatted_categories, 'name'));
    
	if ($existing_category !== false) {
        // Category already exists
        echo json_encode(['status' => 'skipped', 'message' => 'Category already exists']);
        exit();
    }

    // Step 4: Create a new category if it doesn't exist
    $categoryData = [
        'name' => $categoryName // Field name and value
    ];

    // Create the category wrapped as a struct
   // Step 4: Create a new category if it doesn't exist
	$categoryData = [
		'name' => new Value($categoryName, 'string') // Field name and value wrapped as Value
	];

	// Create the category wrapped as a struct
	$categoryStruct = new Value($categoryData, 'struct');

	// Create the category
	$createRequest = new Request('execute_kw', [
		new Value($db, 'string'),
		new Value($uid, 'int'),
		new Value($password, 'string'),
		new Value('product.category', 'string'),
		new Value('create', 'string'),
		new Value([$categoryStruct], 'array') // Pass the struct in an array
	]);

	// Send the creation request
	$createResponse = $client_object->send($createRequest);

    // Check if category creation was successful
    if (!$createResponse->faultCode()) {
        $categoryId = $createResponse->value()->scalarval();
        echo json_encode(['status' => 'success', 'category_id' => $categoryId]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $createResponse->faultString()]);
    }

} else {
    echo json_encode(['status' => 'error', 'message' => $response->faultString()]);
}
