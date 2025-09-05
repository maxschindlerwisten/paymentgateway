<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Dotenv\Dotenv;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\RotatingFileHandler;
use GoFresh\Services\AirtableService;
use GoFresh\Services\CartService;
use GoFresh\Services\OrderService;
use GoFresh\Services\PaymentService;
use GoFresh\Controllers\ProductsController;
use GoFresh\Controllers\CartController;
use GoFresh\Controllers\CheckoutController;

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__ . '/../config');
$dotenv->load();

// Set up logging
$logger = new Logger('GoFreshAPI');
$logLevel = $_ENV['LOG_LEVEL'] ?? 'info';
$logPath = $_ENV['LOG_PATH'] ?? './logs/';

// Create logs directory if it doesn't exist
if (!is_dir($logPath)) {
    mkdir($logPath, 0755, true);
}

$logger->pushHandler(new RotatingFileHandler($logPath . 'api.log', 30, $logLevel));
$logger->pushHandler(new StreamHandler('php://stderr', Logger::ERROR));

// CORS headers for frontend integration
header('Access-Control-Allow-Origin: ' . ($_ENV['FRONTEND_URL'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Initialize services
try {
    $airtableService = new AirtableService(
        $logger,
        $_ENV['AIRTABLE_API_KEY'],
        $_ENV['AIRTABLE_BASE_ID'],
        $_ENV['AIRTABLE_PRODUCTS_TABLE'] ?? 'Products'
    );

    $cartService = new CartService($logger);

    $orderService = new OrderService($logger, './data');

    $paymentService = new PaymentService(
        $logger,
        $_ENV['CSOB_MERCHANT_ID'],
        $_ENV['CSOB_API_URL'],
        $_ENV['CSOB_PRIVATE_KEY_PATH'],
        $_ENV['CSOB_PUBLIC_KEY_PATH'],
        $_ENV['CSOB_PRIVATE_KEY_PASSWORD'] ?? null,
        $_ENV['CSOB_PUBLIC_KEY_PASSWORD'] ?? null
    );

    // Initialize controllers
    $productsController = new ProductsController($airtableService, $logger);
    $cartController = new CartController($cartService, $airtableService, $logger);
    $checkoutController = new CheckoutController(
        $cartService,
        $orderService,
        $paymentService,
        $airtableService,
        $logger,
        $_ENV['FRONTEND_URL']
    );

    // Initialize cart session
    $cartController->initializeCartSession();

} catch (Exception $e) {
    $logger->error('Service initialization failed', ['error' => $e->getMessage()]);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Service initialization failed',
        'message' => $_ENV['APP_DEBUG'] ? $e->getMessage() : 'Internal server error'
    ]);
    exit;
}

// Simple router
$requestUri = $_SERVER['REQUEST_URI'];
$requestMethod = $_SERVER['REQUEST_METHOD'];

// Remove query string and normalize path
$path = parse_url($requestUri, PHP_URL_PATH);
$path = rtrim($path, '/');

// API routes
$routes = [
    // Products endpoints
    'GET:/api/products' => [$productsController, 'getAllProducts'],
    'GET:/api/products/search' => [$productsController, 'searchProducts'],
    'GET:/api/categories' => [$productsController, 'getCategories'],
    
    // Cart endpoints
    'GET:/api/cart' => [$cartController, 'getCart'],
    'POST:/api/cart/add' => [$cartController, 'addToCart'],
    'PUT:/api/cart/update' => [$cartController, 'updateCart'],
    'DELETE:/api/cart/clear' => [$cartController, 'clearCart'],
    'POST:/api/cart/validate' => [$cartController, 'validateCart'],
    'POST:/api/cart/bulk-add' => [$cartController, 'bulkAddToCart'],
    
    // Checkout endpoints
    'POST:/api/checkout/validate' => [$checkoutController, 'validateCheckout'],
    'POST:/api/checkout/create-order' => [$checkoutController, 'createOrder'],
    'POST:/api/checkout/payment-return' => [$checkoutController, 'handlePaymentReturn'],
    'GET:/api/checkout/shipping-rates' => [$checkoutController, 'getShippingRates'],
];

// Dynamic routes (with parameters)
$dynamicRoutes = [
    // Products with ID
    'GET:/api/products/' => function($id) use ($productsController) {
        return $productsController->getProduct($id);
    },
    
    // Cart remove product
    'DELETE:/api/cart/remove/' => function($productId) use ($cartController) {
        return $cartController->removeFromCart($productId);
    },
    
    // Payment status
    'GET:/api/checkout/payment-status/' => function($payId) use ($checkoutController) {
        return $checkoutController->getPaymentStatus($payId);
    }
];

try {
    // Check exact route match first
    $routeKey = $requestMethod . ':' . $path;
    
    if (isset($routes[$routeKey])) {
        [$controller, $method] = $routes[$routeKey];
        $result = $controller->$method();
    } else {
        // Check dynamic routes
        $matched = false;
        
        foreach ($dynamicRoutes as $pattern => $handler) {
            $patternMethod = explode(':', $pattern)[0];
            $patternPath = explode(':', $pattern)[1];
            
            if ($requestMethod === $patternMethod && strpos($path, $patternPath) === 0) {
                $parameter = substr($path, strlen($patternPath));
                if (!empty($parameter)) {
                    $result = $handler($parameter);
                    $matched = true;
                    break;
                }
            }
        }
        
        if (!$matched) {
            // Route not found
            http_response_code(404);
            $result = [
                'success' => false,
                'error' => 'Route not found',
                'message' => 'The requested endpoint does not exist'
            ];
        }
    }

    // Save cart session after successful request
    if (isset($result['success']) && $result['success']) {
        $cartController->saveCartSession();
    }

    // Set appropriate HTTP status code
    if (isset($result['success'])) {
        http_response_code($result['success'] ? 200 : 400);
    }

    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (Exception $e) {
    $logger->error('API request failed', [
        'path' => $path,
        'method' => $requestMethod,
        'error' => $e->getMessage()
    ]);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $_ENV['APP_DEBUG'] ? $e->getMessage() : 'An unexpected error occurred'
    ]);
}