<?php

$collection = [
    "info" => [
        "name" => "Diagram Coffee API",
        "schema" => "https://schema.getpostman.com/json/collection/v2.1.0/collection.json",
        "description" => "Postman collection for Diagram Coffee API based on available endpoints and test cases."
    ],
    "variable" => [
        [
            "key" => "base_url",
            "value" => "http://localhost:8000/api",
            "type" => "string"
        ],
        [
            "key" => "token",
            "value" => "",
            "type" => "string"
        ]
    ],
    "item" => []
];

// Helper function to create items
function createRequest($name, $method, $url, $headers = [], $body = null, $auth = false) {
    global $collection;
    $request = [
        "name" => $name,
        "request" => [
            "method" => $method,
            "header" => [
                [
                    "key" => "Accept",
                    "value" => "application/json",
                    "type" => "text"
                ]
            ],
            "url" => [
                "raw" => "{{base_url}}" . $url,
                "host" => ["{{base_url}}"],
                "path" => array_values(array_filter(explode("/", explode("?", $url)[0]))),
                "query" => []
            ]
        ],
        "response" => []
    ];
    
    // Parse query parameters
    $urlParts = explode("?", $url);
    if (count($urlParts) > 1) {
        $queryParams = explode("&", $urlParts[1]);
        foreach ($queryParams as $param) {
            $kv = explode("=", $param);
            $request["request"]["url"]["query"][] = [
                "key" => $kv[0],
                "value" => isset($kv[1]) ? $kv[1] : ""
            ];
        }
    } else {
        unset($request["request"]["url"]["query"]);
    }

    if ($auth) {
        $request["request"]["auth"] = [
            "type" => "bearer",
            "bearer" => [
                [
                    "key" => "token",
                    "value" => "{{token}}",
                    "type" => "string"
                ]
            ]
        ];
    }

    foreach ($headers as $k => $v) {
        $request["request"]["header"][] = [
            "key" => $k,
            "value" => $v,
            "type" => "text"
        ];
    }

    if ($body) {
        $request["request"]["body"] = [
            "mode" => "raw",
            "raw" => json_encode($body, JSON_PRETTY_PRINT),
            "options" => [
                "raw" => [
                    "language" => "json"
                ]
            ]
        ];
    }

    return $request;
}

function createFolder($name, $items) {
    return [
        "name" => $name,
        "item" => $items
    ];
}

// 1. Auth
$authFolder = createFolder("1. Auth", [
    createRequest("Register", "POST", "/register", [], ["name" => "Test User", "email" => "test@example.com", "password" => "@Password123", "password_confirmation" => "@Password123"]),
    createRequest("Login", "POST", "/login", [], ["email" => "test@example.com", "password" => "@Password123"]),
    createRequest("Logout", "POST", "/logout", [], null, true),
    createRequest("Forgot Password", "POST", "/forgot-password", [], ["email" => "test@example.com"]),
    createRequest("Reset Password", "POST", "/reset-password", [], ["token" => "RESET_TOKEN_HERE", "email" => "test@example.com", "password" => "@NewPassword123", "password_confirmation" => "@NewPassword123"]),
    createRequest("Verify Email", "GET", "/email/verify/1/HASH_HERE"),
    createRequest("Resend Verification", "POST", "/email/resend", [], null, true),
]);

// 2. Profile
$profileFolder = createFolder("2. Profile", [
    createRequest("Get User Profile", "GET", "/user", [], null, true),
    createRequest("Update Profile", "PUT", "/user/profile", [], ["name" => "Updated Name"], true),
]);

// 3. Branches
$branchFolder = createFolder("3. Branches", [
    createRequest("Public: Get Active Branches", "GET", "/branches"),
    createRequest("Public: Get Branch Details", "GET", "/branches/1"),
    createRequest("Admin: Get All Branches", "GET", "/admin/branches", [], null, true),
    createRequest("Admin: Create Branch", "POST", "/admin/branches", [], ["name" => "New Branch", "address" => "Address 1", "phone" => "08123456789", "status" => "active", "opening_time" => "08:00", "closing_time" => "22:00"], true),
    createRequest("Admin: Update Branch", "PUT", "/admin/branches/1", [], ["name" => "Updated Branch", "status" => "inactive"], true),
    createRequest("Admin: Delete Branch", "DELETE", "/admin/branches/1", [], null, true),
]);

// 4. Categories
$categoryFolder = createFolder("4. Categories", [
    createRequest("Public: Get Categories", "GET", "/categories"),
    createRequest("Public: Get Category Details", "GET", "/categories/1"),
    createRequest("Admin: Create Category", "POST", "/admin/categories", [], ["name" => "New Category", "description" => "Desc", "sort_order" => 1], true),
    createRequest("Admin: Update Category", "PUT", "/admin/categories/1", [], ["name" => "Updated Category"], true),
    createRequest("Admin: Delete Category", "DELETE", "/admin/categories/1", [], null, true),
]);

// 5. Menu Items
$menuFolder = createFolder("5. Menu Items", [
    createRequest("Public: Get Menu Items", "GET", "/menu-items"),
    createRequest("Public: Get Menu Item Details", "GET", "/menu-items/1"),
    // Note: create menu with image technically needs form-data, using raw JSON as placeholder here
    createRequest("Admin: Create Menu Item", "POST", "/admin/menu-items", [], ["category_id" => 1, "name" => "New Menu", "base_price" => 15000, "is_active" => true], true),
    createRequest("Admin: Update Menu Item", "PUT", "/admin/menu-items/1", [], ["name" => "Updated Menu", "base_price" => 16000], true),
    createRequest("Admin: Delete Menu Item", "DELETE", "/admin/menu-items/1", [], null, true),
]);

// 6. Branch Menu
$branchMenuFolder = createFolder("6. Branch Menus", [
    createRequest("Public: Get Menus for Branch", "GET", "/branches/1/menus"),
    createRequest("Public: Get Menu Detail at Branch", "GET", "/branches/1/menus/1"),
]);

// 7. Orders
$orderFolder = createFolder("7. Orders", [
    createRequest("Create Order (Customer/Guest)", "POST", "/orders", [], [
        "branch_id" => 1,
        "order_type" => "dine_in",
        "table_number" => "12",
        "payment_method" => "cash",
        "guest_name" => "Guest User",
        "items" => [
            [
                "menu_item_id" => 1,
                "quantity" => 2
            ]
        ]
    ], true),
    createRequest("Get My Orders", "GET", "/orders", [], null, true),
    createRequest("Get Order Detail", "GET", "/orders/1", [], null, true),
    createRequest("Cancel Order", "POST", "/orders/1/cancel", [], null, true),
    createRequest("Guest Status Check", "GET", "/orders/status/ORD-12345"),
]);

// 8. Vouchers
$voucherFolder = createFolder("8. Vouchers", [
    createRequest("Get Active Vouchers", "GET", "/vouchers", [], null, true),
    createRequest("Exchange Points for Voucher", "POST", "/vouchers/exchange", [], ["voucher_id" => 1], true),
    createRequest("Get My Vouchers", "GET", "/vouchers/my-vouchers", [], null, true),
    createRequest("Admin: Create Voucher", "POST", "/admin/vouchers", [], ["name" => "Diskon 10k", "code" => "DISC10", "discount_amount" => 10000, "min_transaction_amount" => 50000, "points_required" => 10, "is_active" => true], true),
    createRequest("Admin: Update Voucher", "PUT", "/admin/vouchers/1", [], ["is_active" => false], true),
    createRequest("Admin: Delete Voucher", "DELETE", "/admin/vouchers/1", [], null, true),
]);

// 9. Banners
$bannerFolder = createFolder("9. Banners", [
    createRequest("Public: Get Active Banners", "GET", "/banners"),
    createRequest("Admin: Get All Banners", "GET", "/admin/banners", [], null, true),
    // Note: banner creation with image technically needs form-data
    createRequest("Admin: Create Banner", "POST", "/admin/banners", [], ["title" => "Promo", "is_active" => true], true),
    createRequest("Admin: Update Banner", "PUT", "/admin/banners/1", [], ["title" => "Updated Promo"], true),
    createRequest("Admin: Delete Banner", "DELETE", "/admin/banners/1", [], null, true),
]);

// 10. Admin Management
$adminMgmtFolder = createFolder("10. Admin Management", [
    createRequest("List Admins", "GET", "/admin/admins", [], null, true),
    createRequest("Show Admin", "GET", "/admin/admins/1", [], null, true),
    createRequest("Admins by Branch", "GET", "/admin/branches/1/admins", [], null, true),
    createRequest("Create Admin", "POST", "/admin/admins", [], ["name" => "New Admin", "email" => "admin@test.com", "password" => "@Password123", "password_confirmation" => "@Password123", "branch_id" => 1], true),
    createRequest("Update Admin", "PUT", "/admin/admins/1", [], ["name" => "Updated Admin"], true),
    createRequest("Delete Admin", "DELETE", "/admin/admins/1", [], null, true),
]);

// 11. Stock Management
$stockFolder = createFolder("11. Stock Management", [
    createRequest("Get Branch Stock", "GET", "/admin/branches/1/stock", [], null, true),
    createRequest("Update Menu Stock/Promo at Branch", "PUT", "/admin/branches/1/menu-items/1/stock", [], ["stock" => 50, "is_available" => true, "is_promo_active" => true, "discount_type" => "percentage", "discount_percentage" => 10], true),
    createRequest("Assign Menu to Branch", "POST", "/admin/branches/1/menu-items/1", [], null, true),
    createRequest("Unassign Menu from Branch", "DELETE", "/admin/branches/1/menu-items/1", [], null, true),
]);

// 12. Admin Order Management
$adminOrderFolder = createFolder("12. Admin Order Management", [
    createRequest("List Orders", "GET", "/admin/orders?status=pending", [], null, true),
    createRequest("Show Order", "GET", "/admin/orders/1", [], null, true),
    createRequest("Update Order Status", "PUT", "/admin/orders/1/status", [], ["status" => "preparing"], true),
    createRequest("Confirm Cash Payment", "POST", "/admin/orders/1/confirm-cash", [], null, true),
]);

// 13. Statistics
$statFolder = createFolder("13. Statistics", [
    createRequest("Get Statistics", "GET", "/admin/statistics?days=7", [], null, true),
]);

// 14. Recommendations
$recFolder = createFolder("14. Recommendations", [
    createRequest("Get Recommendations", "GET", "/recommendations?branch_id=1", [], null, true),
]);

// 15. Webhooks
$webhookFolder = createFolder("15. Webhooks", [
    createRequest("Xendit Webhook", "POST", "/webhooks/xendit", ["x-callback-token" => "YOUR_TOKEN"], ["external_id" => "ORD-123", "status" => "PAID"]),
]);

// 16. Internal API
$internalFolder = createFolder("16. Internal API", [
    createRequest("Export Transactions", "GET", "/internal/transactions", ["X-API-KEY" => "YOUR_INTERNAL_KEY"]),
]);

$collection["item"] = [
    $authFolder,
    $profileFolder,
    $branchFolder,
    $categoryFolder,
    $menuFolder,
    $branchMenuFolder,
    $orderFolder,
    $voucherFolder,
    $bannerFolder,
    $adminMgmtFolder,
    $stockFolder,
    $adminOrderFolder,
    $statFolder,
    $recFolder,
    $webhookFolder,
    $internalFolder
];

$outputFile = __DIR__ . '/Diagram_Coffee_Postman_Collection.json';
file_put_contents($outputFile, json_encode($collection, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "Postman collection successfully generated at: " . $outputFile . "\n";
