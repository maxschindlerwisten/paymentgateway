<?php

/**
 * Test script to verify Go-Fresh backend setup
 * Run this script to check if all services are properly configured
 */

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use GoFresh\Services\AirtableService;
use GoFresh\Services\CartService;
use GoFresh\Services\OrderService;
use GoFresh\Services\PaymentService;

echo "Go-Fresh E-commerce Backend Test\n";
echo "================================\n\n";

// Load environment variables
try {
    $dotenv = Dotenv::createImmutable(__DIR__ . '/config');
    $dotenv->load();
    echo "✓ Environment variables loaded\n";
} catch (Exception $e) {
    echo "✗ Failed to load environment variables: " . $e->getMessage() . "\n";
    echo "Make sure to copy config/.env.example to config/.env and configure it\n";
    exit(1);
}

// Set up logging
$logger = new Logger('GoFreshTest');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::INFO));

echo "✓ Logger initialized\n";

// Test required environment variables
$requiredEnvVars = [
    'AIRTABLE_API_KEY',
    'AIRTABLE_BASE_ID',
    'CSOB_MERCHANT_ID',
    'CSOB_API_URL'
];

$missingVars = [];
foreach ($requiredEnvVars as $var) {
    if (empty($_ENV[$var])) {
        $missingVars[] = $var;
    }
}

if (!empty($missingVars)) {
    echo "✗ Missing required environment variables:\n";
    foreach ($missingVars as $var) {
        echo "  - $var\n";
    }
    echo "\nPlease configure these in config/.env file\n";
    exit(1);
}

echo "✓ Required environment variables present\n";

// Test Airtable Service
echo "\nTesting Airtable Integration...\n";
try {
    $airtableService = new AirtableService(
        $logger,
        $_ENV['AIRTABLE_API_KEY'],
        $_ENV['AIRTABLE_BASE_ID'],
        $_ENV['AIRTABLE_PRODUCTS_TABLE'] ?? 'Products'
    );

    // Try to fetch a few products
    $productsData = $airtableService->getAllProducts(5);
    
    if (isset($productsData['records']) && count($productsData['records']) > 0) {
        echo "✓ Airtable connection successful\n";
        echo "  Found " . count($productsData['records']) . " products\n";
        
        // Show first product as example
        $firstProduct = $productsData['records'][0];
        $productName = $firstProduct['fields']['Name'] ?? 'Unknown';
        echo "  Example product: $productName\n";
    } else {
        echo "⚠ Airtable connected but no products found\n";
        echo "  Make sure your Airtable base has a Products table with data\n";
    }
} catch (Exception $e) {
    echo "✗ Airtable connection failed: " . $e->getMessage() . "\n";
    echo "  Check your AIRTABLE_API_KEY and AIRTABLE_BASE_ID\n";
}

// Test Cart Service
echo "\nTesting Cart Service...\n";
try {
    $cartService = new CartService($logger);
    
    // Test basic cart operations
    $cartService->initializeCart();
    echo "✓ Cart service initialized\n";
    
    // Add a test product
    $testProductData = [
        'name' => 'Test Product',
        'price' => 99.50,
        'image' => 'test.jpg'
    ];
    
    $cartService->addProduct('test-product-1', 2, $testProductData);
    $cartSummary = $cartService->getCartSummary();
    
    if ($cartSummary['itemCount'] === 1 && $cartSummary['totalItems'] === 2) {
        echo "✓ Cart operations working correctly\n";
        echo "  Cart total: " . $cartSummary['total'] . " CZK\n";
    } else {
        echo "✗ Cart operations failed\n";
    }
    
    $cartService->clearCart();
} catch (Exception $e) {
    echo "✗ Cart service failed: " . $e->getMessage() . "\n";
}

// Test Order Service
echo "\nTesting Order Service...\n";
try {
    $orderService = new OrderService($logger, './data');
    
    // Create test order
    $testOrderData = [
        'totalAmount' => 199.50,
        'currency' => 'CZK',
        'customerInfo' => [
            'email' => 'test@example.com',
            'firstName' => 'Test',
            'lastName' => 'Customer'
        ],
        'cartItems' => [
            [
                'productId' => 'test-product',
                'name' => 'Test Product',
                'price' => 99.50,
                'quantity' => 2
            ]
        ]
    ];
    
    $order = $orderService->createOrder($testOrderData);
    
    if (isset($order['id']) && isset($order['orderNo'])) {
        echo "✓ Order service working correctly\n";
        echo "  Created order: " . $order['orderNo'] . "\n";
        
        // Test order retrieval
        $retrievedOrder = $orderService->getOrder($order['id']);
        if ($retrievedOrder) {
            echo "✓ Order retrieval working\n";
        }
    } else {
        echo "✗ Order creation failed\n";
    }
} catch (Exception $e) {
    echo "✗ Order service failed: " . $e->getMessage() . "\n";
}

// Test Payment Service (basic initialization)
echo "\nTesting Payment Service...\n";
try {
    $paymentService = new PaymentService(
        $logger,
        $_ENV['CSOB_MERCHANT_ID'],
        $_ENV['CSOB_API_URL'],
        $_ENV['CSOB_PRIVATE_KEY_PATH'],
        $_ENV['CSOB_PUBLIC_KEY_PATH'],
        $_ENV['CSOB_PRIVATE_KEY_PASSWORD'] ?? null,
        $_ENV['CSOB_PUBLIC_KEY_PASSWORD'] ?? null
    );
    
    echo "✓ Payment service initialized\n";
    
    // Test connection (if keys are available)
    if (file_exists($_ENV['CSOB_PRIVATE_KEY_PATH']) && file_exists($_ENV['CSOB_PUBLIC_KEY_PATH'])) {
        $connectionTest = $paymentService->testConnection();
        if ($connectionTest) {
            echo "✓ ČSOB payment gateway connection successful\n";
        } else {
            echo "⚠ ČSOB payment gateway connection failed\n";
            echo "  This might be normal in test environment\n";
        }
    } else {
        echo "⚠ ČSOB key files not found\n";
        echo "  Place your merchant keys in config/ directory\n";
    }
} catch (Exception $e) {
    echo "✗ Payment service failed: " . $e->getMessage() . "\n";
}

// Test directory permissions
echo "\nTesting Directory Permissions...\n";

$directories = ['./data', './logs'];
foreach ($directories as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    if (is_writable($dir)) {
        echo "✓ $dir is writable\n";
    } else {
        echo "✗ $dir is not writable\n";
        echo "  Run: chmod -R 755 $dir\n";
    }
}

// Test file creation in data directory
try {
    $testFile = './data/test.txt';
    file_put_contents($testFile, 'test');
    
    if (file_exists($testFile)) {
        echo "✓ File creation in data directory works\n";
        unlink($testFile);
    }
} catch (Exception $e) {
    echo "✗ Cannot create files in data directory: " . $e->getMessage() . "\n";
}

echo "\n================================\n";
echo "Test completed!\n\n";

echo "Next steps:\n";
echo "1. Configure your web server to point to public/ directory\n";
echo "2. Set up SSL certificate for HTTPS\n";
echo "3. Configure CORS for your Framer frontend domain\n";
echo "4. Test API endpoints using the provided examples\n";
echo "5. Set up monitoring and backups for production\n\n";

echo "API will be available at: " . ($_ENV['APP_URL'] ?? 'https://api.go-fresh.com') . "\n";
echo "Frontend URL: " . ($_ENV['FRONTEND_URL'] ?? 'https://go-fresh.framer.website') . "\n\n";

echo "Happy coding! 🚀\n";