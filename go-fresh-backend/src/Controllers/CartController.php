<?php

namespace GoFresh\Controllers;

use GoFresh\Services\CartService;
use GoFresh\Services\AirtableService;
use Monolog\Logger;

/**
 * Cart API Controller
 * Handles all shopping cart-related API endpoints for the Framer frontend
 */
class CartController
{
    private CartService $cartService;
    private AirtableService $airtableService;
    private Logger $logger;

    public function __construct(CartService $cartService, AirtableService $airtableService, Logger $logger)
    {
        $this->cartService = $cartService;
        $this->airtableService = $airtableService;
        $this->logger = $logger;
    }

    /**
     * POST /api/cart/add - Add product to cart
     */
    public function addToCart(): array
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $productId = $input['productId'] ?? '';
            $quantity = (int) ($input['quantity'] ?? 1);
            
            if (empty($productId)) {
                return [
                    'success' => false,
                    'error' => 'Product ID required',
                    'message' => 'Please provide a valid product ID'
                ];
            }

            if ($quantity <= 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid quantity',
                    'message' => 'Quantity must be greater than 0'
                ];
            }

            // Get product data from Airtable
            $product = $this->airtableService->getProduct($productId);
            
            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Product not found',
                    'message' => 'The requested product could not be found'
                ];
            }

            $fields = $product['fields'];
            $stock = (int) ($fields['Stock'] ?? 0);

            // Check stock availability
            if ($stock < $quantity) {
                return [
                    'success' => false,
                    'error' => 'Insufficient stock',
                    'message' => "Only {$stock} items available in stock",
                    'availableStock' => $stock
                ];
            }

            // Prepare product data for cart
            $productData = [
                'name' => $fields['Name'] ?? '',
                'price' => (float) ($fields['Price'] ?? 0),
                'image' => $this->getProductThumbnail($fields['Images'] ?? []),
                'sku' => $fields['SKU'] ?? '',
                'category' => $fields['Category'] ?? ''
            ];

            // Add to cart
            $this->cartService->addProduct($productId, $quantity, $productData);
            
            // Get updated cart summary
            $cartSummary = $this->cartService->getCartSummary();

            return [
                'success' => true,
                'message' => 'Product added to cart successfully',
                'data' => [
                    'cart' => $cartSummary,
                    'addedProduct' => array_merge($productData, [
                        'productId' => $productId,
                        'quantity' => $quantity
                    ])
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to add product to cart', [
                'productId' => $productId ?? '',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to add product to cart',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * DELETE /api/cart/remove/{productId} - Remove product from cart
     */
    public function removeFromCart(string $productId): array
    {
        try {
            $removed = $this->cartService->removeProduct($productId);
            
            if (!$removed) {
                return [
                    'success' => false,
                    'error' => 'Product not in cart',
                    'message' => 'The product is not in your cart'
                ];
            }

            $cartSummary = $this->cartService->getCartSummary();

            return [
                'success' => true,
                'message' => 'Product removed from cart successfully',
                'data' => [
                    'cart' => $cartSummary
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to remove product from cart', [
                'productId' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to remove product from cart',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * PUT /api/cart/update - Update product quantity in cart
     */
    public function updateCart(): array
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            $productId = $input['productId'] ?? '';
            $quantity = (int) ($input['quantity'] ?? 0);
            
            if (empty($productId)) {
                return [
                    'success' => false,
                    'error' => 'Product ID required',
                    'message' => 'Please provide a valid product ID'
                ];
            }

            if ($quantity < 0) {
                return [
                    'success' => false,
                    'error' => 'Invalid quantity',
                    'message' => 'Quantity cannot be negative'
                ];
            }

            // If quantity is 0, remove the product
            if ($quantity === 0) {
                return $this->removeFromCart($productId);
            }

            // Check stock availability
            $product = $this->airtableService->getProduct($productId);
            if ($product) {
                $stock = (int) ($product['fields']['Stock'] ?? 0);
                if ($stock < $quantity) {
                    return [
                        'success' => false,
                        'error' => 'Insufficient stock',
                        'message' => "Only {$stock} items available in stock",
                        'availableStock' => $stock
                    ];
                }
            }

            $updated = $this->cartService->updateQuantity($productId, $quantity);
            
            if (!$updated) {
                return [
                    'success' => false,
                    'error' => 'Product not in cart',
                    'message' => 'The product is not in your cart'
                ];
            }

            $cartSummary = $this->cartService->getCartSummary();

            return [
                'success' => true,
                'message' => 'Cart updated successfully',
                'data' => [
                    'cart' => $cartSummary
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to update cart', [
                'productId' => $productId ?? '',
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to update cart',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/cart - Get current cart contents
     */
    public function getCart(): array
    {
        try {
            $cartSummary = $this->cartService->getCartSummary();

            return [
                'success' => true,
                'data' => [
                    'cart' => $cartSummary
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to get cart', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Failed to get cart',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * DELETE /api/cart/clear - Clear entire cart
     */
    public function clearCart(): array
    {
        try {
            $this->cartService->clearCart();

            return [
                'success' => true,
                'message' => 'Cart cleared successfully',
                'data' => [
                    'cart' => $this->cartService->getCartSummary()
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to clear cart', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Failed to clear cart',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * POST /api/cart/validate - Validate cart items against current product data
     */
    public function validateCart(): array
    {
        try {
            $validation = $this->cartService->validateCart($this->airtableService);

            if (!$validation['isValid']) {
                // Apply validation results (remove unavailable products, update prices, etc.)
                $this->cartService->applyValidationResults($validation);
            }

            $cartSummary = $this->cartService->getCartSummary();

            return [
                'success' => true,
                'data' => [
                    'validation' => $validation,
                    'cart' => $cartSummary
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to validate cart', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Failed to validate cart',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * POST /api/cart/bulk-add - Add multiple products to cart
     */
    public function bulkAddToCart(): array
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $products = $input['products'] ?? [];
            
            if (empty($products)) {
                return [
                    'success' => false,
                    'error' => 'No products provided',
                    'message' => 'Please provide an array of products to add'
                ];
            }

            $results = [];
            $errors = [];

            foreach ($products as $productData) {
                $productId = $productData['productId'] ?? '';
                $quantity = (int) ($productData['quantity'] ?? 1);

                try {
                    // Get product from Airtable
                    $product = $this->airtableService->getProduct($productId);
                    
                    if (!$product) {
                        $errors[] = "Product {$productId} not found";
                        continue;
                    }

                    $fields = $product['fields'];
                    $stock = (int) ($fields['Stock'] ?? 0);

                    if ($stock < $quantity) {
                        $errors[] = "Insufficient stock for product {$productId}";
                        continue;
                    }

                    $productInfo = [
                        'name' => $fields['Name'] ?? '',
                        'price' => (float) ($fields['Price'] ?? 0),
                        'image' => $this->getProductThumbnail($fields['Images'] ?? []),
                        'sku' => $fields['SKU'] ?? '',
                        'category' => $fields['Category'] ?? ''
                    ];

                    $this->cartService->addProduct($productId, $quantity, $productInfo);
                    $results[] = ['productId' => $productId, 'quantity' => $quantity, 'status' => 'added'];

                } catch (\Exception $e) {
                    $errors[] = "Failed to add product {$productId}: " . $e->getMessage();
                }
            }

            $cartSummary = $this->cartService->getCartSummary();

            return [
                'success' => empty($errors),
                'message' => empty($errors) ? 'All products added successfully' : 'Some products could not be added',
                'data' => [
                    'cart' => $cartSummary,
                    'results' => $results,
                    'errors' => $errors
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to bulk add to cart', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Failed to bulk add to cart',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get product thumbnail from images array
     */
    private function getProductThumbnail(array $images): string
    {
        if (!empty($images) && isset($images[0]['url'])) {
            return $images[0]['url'];
        }
        
        return '';
    }

    /**
     * Initialize cart for session
     */
    public function initializeCartSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $cartData = $_SESSION['cart'] ?? [];
        $this->cartService->initializeCart($cartData);
    }

    /**
     * Save cart to session
     */
    public function saveCartSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['cart'] = $this->cartService->getCartData();
    }
}