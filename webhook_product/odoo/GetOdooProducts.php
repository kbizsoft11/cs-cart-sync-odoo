<?php
// Retrieve the raw data from the webhook
$config = require __DIR__ . '/../config.php';
$rawData = file_get_contents('php://input');

if (!empty($rawData)) {
    $data = json_decode($rawData, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status' => 'error', 'message' => 'Error decoding JSON: ' . json_last_error_msg()]);
        exit();
    }
    /* if (isset($data['source']) && $data['source'] === 'cs-cart') {
        exit();
    } */
    $productName = $data['display_name'] ?? ''; 
    $productId = $data['_id'] ?? null; 
    $categoryId = $data['categ_id'] ?? 1; 
    $status = 'A';
    $description = $data['description_sale'] ?? ''; 
    $price = $data['list_price'] ?? 0; 
    if (empty($productName)) {
        echo json_encode(['status' => 'error', 'message' => 'Product name is required.']);
        exit();
    }
    $api_url = $config['api_url_product'];
    $api_email = $config['api_email'];  
	$api_key = $config['api_key']; 

    function checkProductExists($productName, $api_url, $api_email, $api_key) {
       $search_url = $api_url . '?q=' . urlencode($productName);
        error_log("Searching for product at: $search_url");
        $ch = curl_init($search_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($api_email . ':' . $api_key)
        ]);
        $response = curl_exec($ch);
        if ($response === false) {
            error_log('cURL error: ' . curl_error($ch));
            return false;
        }
        $responseData = json_decode($response, true);
		curl_close($ch);
        error_log("Search response: " . print_r($responseData, true));
        if (!empty($responseData['products']) && count($responseData['products']) > 0) {
            return true;
        }

        return false; 
    }
    if (checkProductExists($productName, $api_url, $api_email, $api_key)) {
        echo json_encode(['status' => 'error', 'message' => 'Product already exists in CS-Cart.']);
        exit();
    }
    $payload = json_encode([
        'product' => $productName,    
        'price' => $price,            
        'description' => $description, 
        'category_id' => $categoryId, 
        'quantity' => 1,              
        'weight' => 0,                
        'source' => 'odoo',
        'status' => $status,          
    ]);
    error_log("Payload for new product: $payload");
    $ch = curl_init($api_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Basic ' . base64_encode($api_email . ':' . $api_key)
    ]);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    if ($response === false) {
        echo json_encode(['status' => 'error', 'message' => 'cURL error: ' . curl_error($ch)]);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 200 || $httpCode == 201) {
            echo json_encode(['status' => 'success', 'response' => json_decode($response, true)]);
        } else {
            error_log("HTTP Code: $httpCode, Response: $response");
            echo json_encode(['status' => 'error', 'message' => 'Received HTTP status code ' . $httpCode, 'response' => $response]);
        }
    }
    curl_close($ch);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
}
?>
