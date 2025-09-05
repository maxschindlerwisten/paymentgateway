<?php

namespace GoFresh\Services;

use Monolog\Logger;
use Ramsey\Uuid\Uuid;

/**
 * Order Service for managing customer orders
 * Handles order creation, tracking, and management
 */
class OrderService
{
    private Logger $logger;
    private array $orders = [];
    private string $dataPath;

    public function __construct(Logger $logger, string $dataPath = './data')
    {
        $this->logger = $logger;
        $this->dataPath = $dataPath;
        
        // Create data directory if it doesn't exist
        if (!is_dir($this->dataPath)) {
            mkdir($this->dataPath, 0755, true);
        }
        
        $this->loadOrders();
    }

    /**
     * Create a new order
     */
    public function createOrder(array $orderData): array
    {
        $orderId = Uuid::uuid4()->toString();
        $orderNo = $this->generateOrderNumber();
        
        $order = [
            'id' => $orderId,
            'orderNo' => $orderNo,
            'status' => 'pending',
            'totalAmount' => $orderData['totalAmount'],
            'currency' => $orderData['currency'] ?? 'CZK',
            'customerInfo' => $orderData['customerInfo'] ?? [],
            'shippingInfo' => $orderData['shippingInfo'] ?? [],
            'billingInfo' => $orderData['billingInfo'] ?? [],
            'cartItems' => $orderData['cartItems'],
            'paymentMethod' => $orderData['paymentMethod'] ?? 'card',
            'paymentStatus' => 'pending',
            'payId' => null,
            'createdAt' => date('Y-m-d H:i:s'),
            'updatedAt' => date('Y-m-d H:i:s'),
            'notes' => $orderData['notes'] ?? '',
            'metadata' => $orderData['metadata'] ?? []
        ];

        $this->orders[$orderId] = $order;
        $this->saveOrders();

        $this->logger->info('Order created', [
            'orderId' => $orderId,
            'orderNo' => $orderNo,
            'totalAmount' => $orderData['totalAmount']
        ]);

        return $order;
    }

    /**
     * Get order by ID
     */
    public function getOrder(string $orderId): ?array
    {
        return $this->orders[$orderId] ?? null;
    }

    /**
     * Get order by order number
     */
    public function getOrderByNumber(string $orderNo): ?array
    {
        foreach ($this->orders as $order) {
            if ($order['orderNo'] === $orderNo) {
                return $order;
            }
        }
        return null;
    }

    /**
     * Update order status
     */
    public function updateOrderStatus(string $orderId, string $status): bool
    {
        if (!isset($this->orders[$orderId])) {
            return false;
        }

        $oldStatus = $this->orders[$orderId]['status'];
        $this->orders[$orderId]['status'] = $status;
        $this->orders[$orderId]['updatedAt'] = date('Y-m-d H:i:s');
        
        $this->saveOrders();

        $this->logger->info('Order status updated', [
            'orderId' => $orderId,
            'oldStatus' => $oldStatus,
            'newStatus' => $status
        ]);

        return true;
    }

    /**
     * Update order payment information
     */
    public function updateOrderPayment(string $orderId, string $payId, string $paymentStatus): bool
    {
        if (!isset($this->orders[$orderId])) {
            return false;
        }

        $this->orders[$orderId]['payId'] = $payId;
        $this->orders[$orderId]['paymentStatus'] = $paymentStatus;
        $this->orders[$orderId]['updatedAt'] = date('Y-m-d H:i:s');

        // Update order status based on payment status
        switch ($paymentStatus) {
            case 'paid':
                $this->orders[$orderId]['status'] = 'processing';
                break;
            case 'failed':
                $this->orders[$orderId]['status'] = 'failed';
                break;
            case 'cancelled':
                $this->orders[$orderId]['status'] = 'cancelled';
                break;
        }
        
        $this->saveOrders();

        $this->logger->info('Order payment updated', [
            'orderId' => $orderId,
            'payId' => $payId,
            'paymentStatus' => $paymentStatus
        ]);

        return true;
    }

    /**
     * Get orders for a customer
     */
    public function getCustomerOrders(string $customerEmail): array
    {
        $customerOrders = [];
        
        foreach ($this->orders as $order) {
            if (($order['customerInfo']['email'] ?? '') === $customerEmail) {
                $customerOrders[] = $order;
            }
        }

        // Sort by creation date (newest first)
        usort($customerOrders, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });

        return $customerOrders;
    }

    /**
     * Get all orders with optional filtering
     */
    public function getAllOrders(array $filters = [], int $limit = 100, int $offset = 0): array
    {
        $filteredOrders = $this->orders;

        // Apply filters
        if (isset($filters['status'])) {
            $filteredOrders = array_filter($filteredOrders, function($order) use ($filters) {
                return $order['status'] === $filters['status'];
            });
        }

        if (isset($filters['paymentStatus'])) {
            $filteredOrders = array_filter($filteredOrders, function($order) use ($filters) {
                return $order['paymentStatus'] === $filters['paymentStatus'];
            });
        }

        if (isset($filters['dateFrom'])) {
            $filteredOrders = array_filter($filteredOrders, function($order) use ($filters) {
                return strtotime($order['createdAt']) >= strtotime($filters['dateFrom']);
            });
        }

        if (isset($filters['dateTo'])) {
            $filteredOrders = array_filter($filteredOrders, function($order) use ($filters) {
                return strtotime($order['createdAt']) <= strtotime($filters['dateTo']);
            });
        }

        // Sort by creation date (newest first)
        uasort($filteredOrders, function($a, $b) {
            return strtotime($b['createdAt']) - strtotime($a['createdAt']);
        });

        // Apply pagination
        $filteredOrders = array_slice($filteredOrders, $offset, $limit, true);

        return $filteredOrders;
    }

    /**
     * Calculate order statistics
     */
    public function getOrderStatistics(string $dateFrom = null, string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?: date('Y-m-01'); // Start of current month
        $dateTo = $dateTo ?: date('Y-m-d H:i:s'); // Now

        $filteredOrders = array_filter($this->orders, function($order) use ($dateFrom, $dateTo) {
            $orderDate = strtotime($order['createdAt']);
            return $orderDate >= strtotime($dateFrom) && $orderDate <= strtotime($dateTo);
        });

        $totalOrders = count($filteredOrders);
        $totalRevenue = 0;
        $statusCounts = [];
        $paymentStatusCounts = [];

        foreach ($filteredOrders as $order) {
            if ($order['paymentStatus'] === 'paid') {
                $totalRevenue += $order['totalAmount'];
            }

            $status = $order['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;

            $paymentStatus = $order['paymentStatus'];
            $paymentStatusCounts[$paymentStatus] = ($paymentStatusCounts[$paymentStatus] ?? 0) + 1;
        }

        return [
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ],
            'totalOrders' => $totalOrders,
            'totalRevenue' => $totalRevenue,
            'averageOrderValue' => $totalOrders > 0 ? $totalRevenue / $totalOrders : 0,
            'statusBreakdown' => $statusCounts,
            'paymentStatusBreakdown' => $paymentStatusCounts
        ];
    }

    /**
     * Generate unique order number
     */
    private function generateOrderNumber(): string
    {
        $prefix = 'GF'; // Go-Fresh prefix
        $timestamp = date('ymd');
        $counter = 1;

        // Find the highest counter for today
        foreach ($this->orders as $order) {
            if (strpos($order['orderNo'], $prefix . $timestamp) === 0) {
                $existingCounter = (int) substr($order['orderNo'], -4);
                if ($existingCounter >= $counter) {
                    $counter = $existingCounter + 1;
                }
            }
        }

        return $prefix . $timestamp . str_pad($counter, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Load orders from file storage
     */
    private function loadOrders(): void
    {
        $ordersFile = $this->dataPath . '/orders.json';
        
        if (file_exists($ordersFile)) {
            $ordersData = file_get_contents($ordersFile);
            $this->orders = json_decode($ordersData, true) ?: [];
        }
    }

    /**
     * Save orders to file storage
     */
    private function saveOrders(): void
    {
        $ordersFile = $this->dataPath . '/orders.json';
        file_put_contents($ordersFile, json_encode($this->orders, JSON_PRETTY_PRINT));
    }

    /**
     * Add order note
     */
    public function addOrderNote(string $orderId, string $note): bool
    {
        if (!isset($this->orders[$orderId])) {
            return false;
        }

        $this->orders[$orderId]['notes'] .= "\n[" . date('Y-m-d H:i:s') . "] " . $note;
        $this->orders[$orderId]['updatedAt'] = date('Y-m-d H:i:s');
        
        $this->saveOrders();

        $this->logger->info('Order note added', [
            'orderId' => $orderId,
            'note' => $note
        ]);

        return true;
    }

    /**
     * Cancel order
     */
    public function cancelOrder(string $orderId, string $reason = ''): bool
    {
        if (!isset($this->orders[$orderId])) {
            return false;
        }

        $this->orders[$orderId]['status'] = 'cancelled';
        $this->orders[$orderId]['updatedAt'] = date('Y-m-d H:i:s');
        
        if ($reason) {
            $this->addOrderNote($orderId, "Order cancelled: " . $reason);
        }
        
        $this->saveOrders();

        $this->logger->info('Order cancelled', [
            'orderId' => $orderId,
            'reason' => $reason
        ]);

        return true;
    }
}