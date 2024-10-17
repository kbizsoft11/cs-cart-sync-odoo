<?php

/* error_reporting(E_ALL);
ini_set('display_errors', 1); */
$config = require __DIR__ . '/../config.php';
$rawData = file_get_contents('php://input');

if (!empty($rawData)) {
    $data = json_decode($rawData, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        if (isset($data['source']) && $data['source'] === 'cs-cart') {
            exit();
        }

        $customerName = $data['display_name'] ?? 'No Name';
        $email = $data['email'] ?? null;  // Set to null if email is not provided

        // Check if email exists and is not empty
        if (empty($email)) {
            echo json_encode(['status' => 'error', 'message' => 'Email field is missing or empty']);
            exit();
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email format: ' . $email]);
            exit(); 
        }

        $api_url = $config['api_url_customer'];  // Fetch only customers
        $api_email = $config['api_email'];  
	    $api_key = $config['api_key'];

        // Step 1: Fetch existing customers to check if the email already exists
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode($api_email . ':' . $api_key)
        ]);
        curl_setopt($ch, CURLOPT_HTTPGET, true); // Use GET request to fetch users

        $existingUsersResponse = curl_exec($ch);

        if ($existingUsersResponse === false) {
            echo json_encode(['status' => 'error', 'message' => 'cURL error fetching users: ' . curl_error($ch)]);
            curl_close($ch);
            exit();
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($httpCode != 200) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Error fetching users: HTTP ' . $httpCode,
                'response' => $existingUsersResponse // Include response body for debugging
            ]);
            curl_close($ch);
            exit();
        }

        $existingUsers = json_decode($existingUsersResponse, true);
        curl_close($ch);

        // Step 2: Check if the customer email already exists in CS-Cart
        $emailExists = false;
        if (isset($existingUsers['users'])) {
            foreach ($existingUsers['users'] as $user) {
                if (isset($user['email']) && $user['email'] === $email) {
                    $emailExists = true;
                    break;
                }
            }
        }

        if ($emailExists) {
            echo json_encode(['status' => 'skipped', 'message' => 'Customer with this email already exists in CS-Cart']);
            exit();
        }

        // Step 3: If the email does not exist, proceed to create a new customer
        $payload = json_encode([
            'email' => $email,
            'user_type' => 'C', // Make sure this field is included when creating a customer
            'company_id' => 1,  // Example company ID
            'status' => 'A',    // Active user status
            'firstname' => $customerName,
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
            echo json_encode(['status' => 'error', 'message' => 'cURL error: ' . curl_error($ch)]);
        } else {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode == 200 || 201) {
                echo json_encode(['status' => 'success', 'response' => json_decode($response, true)]);
            } else {
                echo json_encode(['status' => 'error', 'message' => 'Received HTTP status code ' . $httpCode, 'response' => $response]);
            }
        }

        curl_close($ch);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error decoding JSON: ' . json_last_error_msg()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No data received']);
}
