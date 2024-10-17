<?php


//ini_set('display_errors', 1);
//error_reporting(E_ALL);

use Tygh\Registry;

// Function to trigger the webhook for products starts here
function fn_webhook_addon_update_product_post($product_data, $product_id, $lang_code, $create) {
    // Log to check if hook is triggered
    file_put_contents('webhook_log.txt', "Product Hook Triggered: Product ID - $product_id, Name - {$product_data['product']}\n", FILE_APPEND);

    if ($create) {
        // Call the function to trigger webhook if the product is newly created
        fn_webhook_addon_trigger_webhook($product_data, $product_id);
    }
}


function fn_webhook_addon_trigger_webhook($product_data, $product_id) {
    // Set the webhook URL (ngrok URL)
    $webhook_url = 'https://155e-2405-201-5004-780f-c08-c8e2-2872-3fda.ngrok-free.app/webhook_product/GetProduct.php';

    // Prepare the data to send
     $data = [
        'product_id' => $product_id,
        'product_name' => $product_data['product'],
        'price' => $product_data['price'],
        'description' => $product_data['full_description'], // Example: send product description
        'category_id' => !empty($product_data['main_category']) ? $product_data['main_category'] : 1, // 
        'quantity' => $product_data['amount'], // Example: send available quantity
        'weight' => $product_data['weight'], // Example: send weight of the product
        'timestamp' => time(),
        'status' => $product_data['status'], // Send product status
        'features' => isset($product_data['product_features']) ? json_encode($product_data['product_features']) : '', // Send product features if available
		'source' => 'cs-cart'
    ];

    // Send data to the webhook using cURL
    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    // Execute cURL request and handle response
    $response = curl_exec($ch);

    // Check for cURL errors
    if ($response === false) {
        file_put_contents('webhook_log.txt', "cURL Error: " . curl_error($ch) . "\n", FILE_APPEND);
    } else {
        // Log the response
        fn_log_event('webhook', 'product_created', ['response' => $response]);
    }
    
    curl_close($ch);
}


// Function to trigger the webhook for products ends here

// function fn_webhook_addon_update_user_post($user_data, $user_id, $lang_code, $create) {
//     file_put_contents('webhook_log.txt', "Function Start: User ID - $user_id\n", FILE_APPEND);

//     // Log data received
//     file_put_contents('webhook_log.txt', "Data: " . print_r($user_data, true) . "\n", FILE_APPEND);

//     if ($create) {
//         file_put_contents('webhook_log.txt', "Creating User Webhook Trigger\n", FILE_APPEND);
//         fn_webhook_addon_trigger_customer_webhook($user_data, $user_id);
//     }
// }

// // Function to trigger the webhook for customers
// function fn_webhook_addon_trigger_customer_webhook($user_data, $user_id) {
//     $url = 'https://cb93-2405-201-5004-780f-c865-9fcd-7e7b-19fd.ngrok-free.app/webhook_product/GetCustomer.php'; // Replace with your ngrok URL

//     // Prepare the data to send
//     $postData = [
//         'customer_id' => $user_id,
//         'customer_email' => $user_data['email'],
//         'customer_name' => trim($user_data['firstname'] . ' ' . $user_data['lastname']), // Ensure there's no extra space
//         'status' => $user_data['status'],
//         'timestamp' => time(),
//     ];

//     // Log the post data before sending
//     file_put_contents('webhook_log.txt', "Sending data: " . json_encode($postData) . "\n", FILE_APPEND);

//     // Use cURL to send the POST request
//     $ch = curl_init($url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData)); // Use http_build_query for proper URL encoding

//     // Execute the request
//     $response = curl_exec($ch);

//     // Check for cURL errors
//     if ($response === false) {
//         file_put_contents('webhook_log.txt', "cURL Error: " . curl_error($ch) . "\n", FILE_APPEND);
//     } else {
//         // Log the response
//         file_put_contents('webhook_log.txt', "Customer Webhook Response: $response\n", FILE_APPEND);
//     }
    
//     curl_close($ch);
// }

// Function to trigger webhook for customers ends here


// Function to trigger the webhook for categories starts here
function fn_webhook_addon_update_category_post($category_data, $category_id, $lang_code, $create) {
    // Log to check if hook is triggered
    file_put_contents('webhook_log.txt', "Category Hook Triggered: Category ID - $category_id, Name - {$category_data['category']}\n", FILE_APPEND);

    if ($create) {
        // Call the function to trigger webhook if the category is newly created
        fn_webhook_addon_trigger_category_webhook($category_data, $category_id);
    } else {
        // Optionally, handle updates if necessary
        // You can add conditions here to avoid triggering the webhook for certain updates
        // For example:
        // if ($category_data['status'] !== 'A') return; // Skip if not active
    }
}

function fn_webhook_addon_trigger_category_webhook($category_data, $category_id) {
    $webhook_url = 'https://155e-2405-201-5004-780f-c08-c8e2-2872-3fda.ngrok-free.app/webhook_product/GetCategory.php';
   
    $data = [
        'category_id' => $category_id,
        'category_name' => $category_data['category'],
        'timestamp' => time(),
        'status' => $category_data['status'], // Example: send status
        'source' => 'cs-cart' // Source to identify where the data is coming from
    ];

    // Add a check to prevent sending data if the source is 'odoo'
    // (This check is more relevant for your GetCategory.php script, but can be implemented here for safety)
    if (isset($category_data['source']) && $category_data['source'] === 'odoo') {
        return; // Skip if the source is Odoo to prevent loops
    }

    $ch = curl_init($webhook_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));

    $response = curl_exec($ch);

    if ($response === false) {
        file_put_contents('webhook_log.txt', "Category cURL Error: " . curl_error($ch) . "\n", FILE_APPEND);
    } else {
        fn_log_event('webhook', 'category_created', ['response' => $response]);
    }

    curl_close($ch);
}


// Function to trigger the webhook for categories ends here



