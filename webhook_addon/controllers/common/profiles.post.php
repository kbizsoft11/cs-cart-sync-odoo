<?php
/***************************************************************************
*                                                                          *
*   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
*                                                                          *
* This  is  commercial  software,  only  users  who have purchased a valid *
* license  and  accept  to the terms of the  License Agreement can install *
* and use this program.                                                    *
*                                                                          *
****************************************************************************
* PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
* "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
****************************************************************************/

if (!defined('BOOTSTRAP')) { die('Access denied'); }

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Capture the POST data
    $postData = $_POST;

    // Specify the URL to send data to
    $url = 'https://155e-2405-201-5004-780f-c08-c8e2-2872-3fda.ngrok-free.app/webhook_product/GetCustomer.php';
    
    // Initialize cURL session
    $ch = curl_init($url);

    // Convert POST data to URL-encoded query string
    $postDataEncoded = http_build_query($postData);

    // Set cURL options
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $postDataEncoded);  // Send the POST data

    // Execute the cURL request and capture the response
    $response = curl_exec($ch);

    // Check for errors in cURL execution
    if ($response === false) {
        echo "cURL Error: " . curl_error($ch);
    } else {
        //echo "Response from server: $response";  // Print the response from the server
    }

    // Close the cURL session
    curl_close($ch);


    // Your existing logic here...
    if ($mode == 'update' && !empty(Tygh::$app['session']['auth']['user_id'])) {
        $data['birthday'] = db_get_field("SELECT birthday FROM ?:users WHERE user_id = ?i", Tygh::$app['session']['auth']['user_id']);
        fn_age_verification_check_age($data);
    }

}

