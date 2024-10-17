<?php
if (!defined('BOOTSTRAP')) { die('Access denied'); }

// Register hooks
fn_register_hooks(
    'update_product_post',
    'create_user_post', 
    'update_user_post',
    'update_category_post',
    'profile_updated'    
);
