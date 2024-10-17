<?php 
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

    $categoryName =$data['complete_name'];
    $parentId = $data['parent_id'] ?? 0;  
    $status = 'A';  
    $description = $data['complete_name'] ?? '';
    $categoryId = $data['_id'] ?? null; 
    $api_url = $config['api_url_cat']; 
	$api_email = $config['api_email'];  
	$api_key = $config['api_key'];

	$allCategories = []; // To store all categories
	$page = 1; // Start with the first page
	$items_per_page = 100; // Number of categories per page (adjust as needed)

	do {
		// Append pagination parameters to the API URL
		$paginated_url = $api_url . '?items_per_page=' . $items_per_page . '&page=' . $page;

		// Initialize curl for each page
		$ch = curl_init($paginated_url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'Authorization: Basic ' . base64_encode($api_email . ':' . $api_key)
		]);
		curl_setopt($ch, CURLOPT_HTTPGET, true);
		
		$existingCategoriesResponse = curl_exec($ch);
		$categoriesData = json_decode($existingCategoriesResponse, true); // Decode response

		// If categories exist, merge them into the allCategories array
		if (!empty($categoriesData['categories'])) {
			$allCategories = array_merge($allCategories, $categoriesData['categories']);
		}

		// Check if more pages exist
		$hasMorePages = !empty($categoriesData['meta']['total_pages']) && $page < $categoriesData['meta']['total_pages'];
		$page++; // Move to next page

	} while ($hasMorePages);
    
    if ($existingCategoriesResponse === false) {
        echo json_encode(['status' => 'error', 'message' => 'cURL error fetching categories: ' . curl_error($ch)]);
        curl_close($ch);
        exit();
    }

    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode != 200) {
        echo json_encode(['status' => 'error', 'message' => 'Error fetching existing categories: HTTP ' . $httpCode]);
        curl_close($ch);
        exit();
    }
    
    $existingCategories = json_decode($existingCategoriesResponse, true);
    curl_close($ch);
	
	$forma_category = [];
    foreach ($existingCategories['categories'] as $category) {
		$category_name=$category['category'];
	
	    $forma_category[] = [
            'category' => $category_name
        ];
	   
    }
	
    $exist_category = array_search($categoryName, array_column($forma_category, 'category'));
    
     if ($exist_category !== false) {
        echo json_encode(['status' => 'skipped', 'message' => 'Category already exists in CS-Cart']);
        exit();
    }
    $payload = json_encode([
        'category' => $categoryName,
        'parent_id' => $parentId,
        'status' => $status,
        'description' => $description,
        'source' => 'odoo', 
        'original_id' => $categoryId
    ]);
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
        echo json_encode(['status' => 'error', 'message' => 'cURL error creating category: ' . curl_error($ch)]);
    } else {
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode == 200 || $httpCode == 201) {
            echo json_encode(['status' => 'success', 'response' => json_decode($response, true)]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Received HTTP status code ' . $httpCode, 'response' => $response]);
        }
    }

    curl_close($ch);
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
}
