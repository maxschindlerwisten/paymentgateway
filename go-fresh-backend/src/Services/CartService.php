<?php

namespace GoFresh\Services;

use Monolog\Logger;

/**
 * Cart Service for managing shopping cart functionality
 * Handles adding, removing, and managing products in the cart
 */
class CartService
{
    private Logger $logger;
    private array $cartItems = [];

    public function __construct(Logger $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Initialize cart from session or external storage
     */
    public function initializeCart(array $cartData = []): void
    {
        $this->cartItems = $cartData;
        $this->logger->info('Cart initialized', ['itemCount' => count($this->cartItems)]);
    }

    /**
     * Add product to cart
     */
    public function addProduct(string $productId, int $quantity, array $productData): array
    {
        if ($quantity <= 0) {
            throw new \InvalidArgumentException('Quantity must be greater than 0');
        }

        if (isset($this->cartItems[$productId])) {
            $this->cartItems[$productId]['quantity'] += $quantity;
        } else {
            $this->cartItems[$productId] = [
                'productId' => $productId,
                'name' => $productData['name'] ?? '',
                'price' => $productData['price'] ?? 0,
                'image' => $productData['image'] ?? '',
                'quantity' => $quantity,
                'addedAt' => date('Y-m-d H:i:s')
            ];
        }

        $this->logger->info('Product added to cart', [
            'productId' => $productId,
            'quantity' => $quantity,
            'totalQuantity' => $this->cartItems[$productId]['quantity']
        ]);

        return $this->cartItems[$productId];
    }

    /**
     * Remove product from cart
     */
    public function removeProduct(string $productId): bool
    {
        if (isset($this->cartItems[$productId])) {
            unset($this->cartItems[$productId]);
            $this->logger->info('Product removed from cart', ['productId' => $productId]);
            return true;
        }

        return false;
    }

    /**
     * Update product quantity in cart
     */
    public function updateQuantity(string $productId, int $quantity): bool
    {
        if (!isset($this->cartItems[$productId])) {
            return false;
        }

        if ($quantity <= 0) {
            return $this->removeProduct($productId);
        }

        $oldQuantity = $this->cartItems[$productId]['quantity'];
        $this->cartItems[$productId]['quantity'] = $quantity;

        $this->logger->info('Cart quantity updated', [
            'productId' => $productId,
            'oldQuantity' => $oldQuantity,
            'newQuantity' => $quantity
        ]);

        return true;
    }

    /**
     * Get all cart items
     */
    public function getCartItems(): array
    {
        return $this->cartItems;
    }

    /**
     * Get cart summary
     */
    public function getCartSummary(): array
    {
        $totalItems = 0;
        $subtotal = 0;

        foreach ($this->cartItems as $item) {
            $totalItems += $item['quantity'];
            $subtotal += $item['price'] * $item['quantity'];
        }

        // Calculate tax (21% VAT for Czech Republic)
        $taxRate = 0.21;
        $tax = $subtotal * $taxRate;
        $total = $subtotal + $tax;

        return [
            'itemCount' => count($this->cartItems),
            'totalItems' => $totalItems,
            'subtotal' => $subtotal,
            'tax' => $tax,
            'taxRate' => $taxRate,
            'total' => $total,
            'items' => $this->cartItems
        ];
    }

    /**
     * Clear the entire cart
     */
    public function clearCart(): void
    {
        $itemCount = count($this->cartItems);
        $this->cartItems = [];
        
        $this->logger->info('Cart cleared', ['previousItemCount' => $itemCount]);
    }

    /**
     * Check if cart is empty
     */
    public function isEmpty(): bool
    {
        return empty($this->cartItems);
    }

    /**
     * Get cart data for external storage (session, database, etc.)
     */
    public function getCartData(): array
    {
        return $this->cartItems;
    }

    /**
     * Validate cart items against current product data
     */
    public function validateCart(AirtableService $airtableService): array
    {
        $validationResults = [];
        $hasErrors = false;

        foreach ($this->cartItems as $productId => $item) {
            $currentProduct = $airtableService->getProduct($productId);
            
            if (!$currentProduct) {
                $validationResults[$productId] = [
                    'status' => 'error',
                    'message' => 'Product no longer available',
                    'action' => 'remove'
                ];
                $hasErrors = true;
                continue;
            }

            $currentPrice = $currentProduct['fields']['Price'] ?? 0;
            $currentStock = $currentProduct['fields']['Stock'] ?? 0;

            // Check if price has changed
            if ($item['price'] !== $currentPrice) {
                $validationResults[$productId] = [
                    'status' => 'warning',
                    'message' => 'Price has changed',
                    'oldPrice' => $item['price'],
                    'newPrice' => $currentPrice,
                    'action' => 'update_price'
                ];
            }

            // Check stock availability
            if ($currentStock < $item['quantity']) {
                $validationResults[$productId] = [
                    'status' => 'error',
                    'message' => 'Insufficient stock',
                    'availableStock' => $currentStock,
                    'requestedQuantity' => $item['quantity'],
                    'action' => $currentStock > 0 ? 'reduce_quantity' : 'remove'
                ];
                $hasErrors = true;
            }

            if (!isset($validationResults[$productId])) {
                $validationResults[$productId] = [
                    'status' => 'valid',
                    'message' => 'Product is valid'
                ];
            }
        }

        return [
            'isValid' => !$hasErrors,
            'hasWarnings' => !empty(array_filter($validationResults, function($result) {
                return $result['status'] === 'warning';
            })),
            'results' => $validationResults
        ];
    }

    /**
     * Apply cart validation results and update cart accordingly
     */
    public function applyValidationResults(array $validationResults): void
    {
        foreach ($validationResults['results'] as $productId => $result) {
            switch ($result['action'] ?? '') {
                case 'remove':
                    $this->removeProduct($productId);
                    break;
                
                case 'update_price':
                    if (isset($this->cartItems[$productId])) {
                        $this->cartItems[$productId]['price'] = $result['newPrice'];
                    }
                    break;
                
                case 'reduce_quantity':
                    $this->updateQuantity($productId, $result['availableStock']);
                    break;
            }
        }

        $this->logger->info('Cart validation results applied', [
            'updatedProducts' => count($validationResults['results'])
        ]);
    }
}