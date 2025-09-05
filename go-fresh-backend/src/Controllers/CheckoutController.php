<?php

namespace GoFresh\Controllers;

use GoFresh\Services\CartService;
use GoFresh\Services\OrderService;
use GoFresh\Services\PaymentService;
use GoFresh\Services\AirtableService;
use Monolog\Logger;

/**
 * Checkout API Controller
 * Handles order creation and payment processing for the Framer frontend
 */
class CheckoutController
{
    private CartService $cartService;
    private OrderService $orderService;
    private PaymentService $paymentService;
    private AirtableService $airtableService;
    private Logger $logger;
    private string $frontendUrl;

    public function __construct(
        CartService $cartService,
        OrderService $orderService,
        PaymentService $paymentService,
        AirtableService $airtableService,
        Logger $logger,
        string $frontendUrl
    ) {
        $this->cartService = $cartService;
        $this->orderService = $orderService;
        $this->paymentService = $paymentService;
        $this->airtableService = $airtableService;
        $this->logger = $logger;
        $this->frontendUrl = $frontendUrl;
    }

    /**
     * POST /api/checkout/validate - Validate checkout data before payment
     */
    public function validateCheckout(): array
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate cart
            if ($this->cartService->isEmpty()) {
                return [
                    'success' => false,
                    'error' => 'Cart is empty',
                    'message' => 'Please add items to your cart before checkout'
                ];
            }

            // Validate cart items against current product data
            $cartValidation = $this->cartService->validateCart($this->airtableService);
            
            if (!$cartValidation['isValid']) {
                return [
                    'success' => false,
                    'error' => 'Cart validation failed',
                    'message' => 'Some items in your cart are no longer available or prices have changed',
                    'data' => ['validation' => $cartValidation]
                ];
            }

            // Validate customer information
            $customerInfo = $input['customerInfo'] ?? [];
            $validationErrors = [];

            if (empty($customerInfo['email'])) {
                $validationErrors['email'] = 'Email is required';
            } elseif (!filter_var($customerInfo['email'], FILTER_VALIDATE_EMAIL)) {
                $validationErrors['email'] = 'Valid email is required';
            }

            if (empty($customerInfo['firstName'])) {
                $validationErrors['firstName'] = 'First name is required';
            }

            if (empty($customerInfo['lastName'])) {
                $validationErrors['lastName'] = 'Last name is required';
            }

            if (empty($customerInfo['phone'])) {
                $validationErrors['phone'] = 'Phone number is required';
            }

            // Validate shipping information
            $shippingInfo = $input['shippingInfo'] ?? [];
            
            if (empty($shippingInfo['address'])) {
                $validationErrors['address'] = 'Address is required';
            }

            if (empty($shippingInfo['city'])) {
                $validationErrors['city'] = 'City is required';
            }

            if (empty($shippingInfo['postalCode'])) {
                $validationErrors['postalCode'] = 'Postal code is required';
            }

            if (empty($shippingInfo['country'])) {
                $validationErrors['country'] = 'Country is required';
            }

            if (!empty($validationErrors)) {
                return [
                    'success' => false,
                    'error' => 'Validation failed',
                    'message' => 'Please correct the following errors',
                    'data' => ['validationErrors' => $validationErrors]
                ];
            }

            // Get cart summary
            $cartSummary = $this->cartService->getCartSummary();

            return [
                'success' => true,
                'message' => 'Checkout validation successful',
                'data' => [
                    'cart' => $cartSummary,
                    'validation' => [
                        'customerInfo' => 'valid',
                        'shippingInfo' => 'valid',
                        'cart' => 'valid'
                    ]
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Checkout validation failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Validation failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * POST /api/checkout/create-order - Create order and initialize payment
     */
    public function createOrder(): array
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Validate cart first
            if ($this->cartService->isEmpty()) {
                return [
                    'success' => false,
                    'error' => 'Cart is empty',
                    'message' => 'Please add items to your cart before checkout'
                ];
            }

            // Final cart validation
            $cartValidation = $this->cartService->validateCart($this->airtableService);
            if (!$cartValidation['isValid']) {
                return [
                    'success' => false,
                    'error' => 'Cart validation failed',
                    'message' => 'Some items in your cart are no longer available'
                ];
            }

            $cartSummary = $this->cartService->getCartSummary();
            
            // Create order
            $orderData = [
                'totalAmount' => $cartSummary['total'],
                'currency' => 'CZK',
                'customerInfo' => $input['customerInfo'] ?? [],
                'shippingInfo' => $input['shippingInfo'] ?? [],
                'billingInfo' => $input['billingInfo'] ?? $input['shippingInfo'] ?? [],
                'cartItems' => $cartSummary['items'],
                'paymentMethod' => $input['paymentMethod'] ?? 'card',
                'notes' => $input['notes'] ?? '',
                'metadata' => [
                    'source' => 'framer_frontend',
                    'userAgent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                    'ipAddress' => $_SERVER['REMOTE_ADDR'] ?? ''
                ]
            ];

            $order = $this->orderService->createOrder($orderData);

            // Prepare payment data
            $paymentData = [
                'orderNo' => $order['orderNo'],
                'totalAmount' => $order['totalAmount'],
                'currency' => $order['currency'],
                'cart' => $this->formatCartForPayment($cartSummary['items']),
                'returnUrl' => $this->frontendUrl . '/checkout/return',
                'customerInfo' => $order['customerInfo'],
                'language' => $input['language'] ?? 'cs',
                'merchantData' => json_encode([
                    'orderId' => $order['id'],
                    'customerEmail' => $order['customerInfo']['email'] ?? ''
                ])
            ];

            // Initialize payment with ČSOB
            $paymentResponse = $this->paymentService->initializePayment($paymentData);

            if (isset($paymentResponse['payId'])) {
                // Update order with payment ID
                $this->orderService->updateOrderPayment(
                    $order['id'],
                    $paymentResponse['payId'],
                    'initialized'
                );

                // Clear cart after successful order creation
                $this->cartService->clearCart();

                return [
                    'success' => true,
                    'message' => 'Order created successfully',
                    'data' => [
                        'order' => $order,
                        'payment' => [
                            'payId' => $paymentResponse['payId'],
                            'paymentUrl' => $paymentResponse['paymentUrl'] ?? null,
                            'status' => 'initialized'
                        ]
                    ]
                ];
            } else {
                // Payment initialization failed
                $this->orderService->updateOrderStatus($order['id'], 'failed');
                
                return [
                    'success' => false,
                    'error' => 'Payment initialization failed',
                    'message' => 'Unable to initialize payment. Please try again.',
                    'data' => ['order' => $order]
                ];
            }

        } catch (\Exception $e) {
            $this->logger->error('Order creation failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Order creation failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * POST /api/checkout/payment-return - Handle payment return from ČSOB
     */
    public function handlePaymentReturn(): array
    {
        try {
            $returnData = $_POST; // ČSOB sends POST data
            
            if (empty($returnData)) {
                // Fallback to GET for testing
                $returnData = $_GET;
            }

            if (empty($returnData['payId'])) {
                return [
                    'success' => false,
                    'error' => 'Missing payment ID',
                    'message' => 'Invalid payment return data'
                ];
            }

            // Process payment return
            $paymentResult = $this->paymentService->processPaymentReturn($returnData);
            
            // Get merchant data to find the order
            $merchantData = null;
            if (isset($returnData['merchantData'])) {
                $merchantData = json_decode(base64_decode($returnData['merchantData']), true);
            }

            $orderId = $merchantData['orderId'] ?? null;
            
            if ($orderId) {
                $order = $this->orderService->getOrder($orderId);
                
                if ($order) {
                    // Update order status based on payment result
                    $paymentStatus = $paymentResult['success'] ? 'paid' : 'failed';
                    $this->orderService->updateOrderPayment(
                        $orderId,
                        $returnData['payId'],
                        $paymentStatus
                    );

                    if ($paymentResult['success']) {
                        // Update product stock in Airtable
                        $this->updateProductStock($order['cartItems']);
                        
                        // Send confirmation email (if configured)
                        $this->sendOrderConfirmation($order);
                    }

                    return [
                        'success' => $paymentResult['success'],
                        'message' => $paymentResult['success'] ? 'Payment successful' : 'Payment failed',
                        'data' => [
                            'order' => $order,
                            'payment' => $paymentResult
                        ]
                    ];
                }
            }

            return [
                'success' => $paymentResult['success'],
                'message' => $paymentResult['success'] ? 'Payment successful' : 'Payment failed',
                'data' => ['payment' => $paymentResult]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Payment return handling failed', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Payment processing failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/checkout/payment-status/{payId} - Get payment status
     */
    public function getPaymentStatus(string $payId): array
    {
        try {
            $paymentStatus = $this->paymentService->getPaymentStatus($payId);
            
            return [
                'success' => true,
                'data' => ['payment' => $paymentStatus]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get payment status', [
                'payId' => $payId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to get payment status',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/checkout/shipping-rates - Get shipping rates (placeholder)
     */
    public function getShippingRates(): array
    {
        // This would typically integrate with shipping providers
        // For now, return static rates
        return [
            'success' => true,
            'data' => [
                'shippingRates' => [
                    [
                        'id' => 'standard',
                        'name' => 'Standard Delivery',
                        'description' => 'Delivery within 3-5 business days',
                        'price' => 99.00,
                        'currency' => 'CZK',
                        'estimatedDays' => 5
                    ],
                    [
                        'id' => 'express',
                        'name' => 'Express Delivery',
                        'description' => 'Next business day delivery',
                        'price' => 199.00,
                        'currency' => 'CZK',
                        'estimatedDays' => 1
                    ],
                    [
                        'id' => 'pickup',
                        'name' => 'Store Pickup',
                        'description' => 'Pick up from our store',
                        'price' => 0.00,
                        'currency' => 'CZK',
                        'estimatedDays' => 0
                    ]
                ]
            ]
        ];
    }

    /**
     * Format cart items for payment gateway
     */
    private function formatCartForPayment(array $cartItems): array
    {
        $formattedCart = [];
        
        foreach ($cartItems as $item) {
            $formattedCart[] = [
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'amount' => $item['price'] * $item['quantity']
            ];
        }
        
        return $formattedCart;
    }

    /**
     * Update product stock in Airtable after successful payment
     */
    private function updateProductStock(array $cartItems): void
    {
        foreach ($cartItems as $item) {
            try {
                $product = $this->airtableService->getProduct($item['productId']);
                if ($product) {
                    $currentStock = (int) ($product['fields']['Stock'] ?? 0);
                    $newStock = max(0, $currentStock - $item['quantity']);
                    
                    $this->airtableService->updateProductStock($item['productId'], $newStock);
                }
            } catch (\Exception $e) {
                $this->logger->warning('Failed to update product stock', [
                    'productId' => $item['productId'],
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Send order confirmation email (placeholder)
     */
    private function sendOrderConfirmation(array $order): void
    {
        // This would typically send an email using a mail service
        // For now, just log the action
        $this->logger->info('Order confirmation would be sent', [
            'orderId' => $order['id'],
            'customerEmail' => $order['customerInfo']['email'] ?? ''
        ]);
    }
}