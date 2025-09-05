<?php

namespace GoFresh\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;

/**
 * ČSOB Payment Gateway Service
 * Handles all payment processing through ČSOB payment gateway
 * Based on the existing ČSOB eAPI v1.9 implementation
 */
class PaymentService
{
    private Client $client;
    private Logger $logger;
    private string $merchantId;
    private string $apiUrl;
    private string $privateKeyPath;
    private string $publicKeyPath;
    private ?string $privateKeyPassword;
    private ?string $publicKeyPassword;
    private $privateKey;
    private $publicKey;

    public function __construct(
        Logger $logger,
        string $merchantId,
        string $apiUrl,
        string $privateKeyPath,
        string $publicKeyPath,
        ?string $privateKeyPassword = null,
        ?string $publicKeyPassword = null
    ) {
        $this->logger = $logger;
        $this->merchantId = $merchantId;
        $this->apiUrl = $apiUrl;
        $this->privateKeyPath = $privateKeyPath;
        $this->publicKeyPath = $publicKeyPath;
        $this->privateKeyPassword = $privateKeyPassword;
        $this->publicKeyPassword = $publicKeyPassword;

        $this->client = new Client([
            'base_uri' => $this->apiUrl,
            'headers' => [
                'Content-Type' => 'application/json'
            ]
        ]);

        $this->loadKeys();
    }

    /**
     * Load private and public keys
     */
    private function loadKeys(): void
    {
        try {
            // Load private key
            if (file_exists($this->privateKeyPath)) {
                $privateKeyContent = file_get_contents($this->privateKeyPath);
                $this->privateKey = openssl_pkey_get_private($privateKeyContent, $this->privateKeyPassword);
                
                if (!$this->privateKey) {
                    throw new \Exception('Failed to load private key');
                }
            }

            // Load public key
            if (file_exists($this->publicKeyPath)) {
                $publicKeyContent = file_get_contents($this->publicKeyPath);
                $this->publicKey = openssl_pkey_get_public($publicKeyContent);
                
                if (!$this->publicKey) {
                    throw new \Exception('Failed to load public key');
                }
            }

            $this->logger->info('Payment gateway keys loaded successfully');
        } catch (\Exception $e) {
            $this->logger->error('Failed to load payment gateway keys', [
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Test connection to payment gateway
     */
    public function testConnection(): bool
    {
        try {
            $response = $this->client->get('/echo');
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            $this->logger->error('Payment gateway connection test failed', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Initialize payment
     */
    public function initializePayment(array $orderData): array
    {
        try {
            // Prepare payment request
            $paymentRequest = [
                'merchantId' => $this->merchantId,
                'orderNo' => $orderData['orderNo'],
                'payOperation' => 'payment',
                'payMethod' => 'card',
                'totalAmount' => $this->convertToHalere($orderData['totalAmount']),
                'currency' => $orderData['currency'] ?? 'CZK',
                'closePayment' => true,
                'returnUrl' => $orderData['returnUrl'],
                'returnMethod' => 'POST',
                'cart' => $this->formatCartForPayment($orderData['cart']),
                'language' => $orderData['language'] ?? 'cs'
            ];

            // Add optional fields
            if (isset($orderData['customerInfo'])) {
                $paymentRequest['customer'] = $orderData['customerInfo'];
            }

            if (isset($orderData['merchantData'])) {
                $paymentRequest['merchantData'] = base64_encode($orderData['merchantData']);
            }

            // Sign the request
            $this->signRequest($paymentRequest);

            // Send request to payment gateway
            $response = $this->client->post('/payment/init', [
                'json' => $paymentRequest
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            // Verify response signature
            if (!$this->verifySignature($responseData)) {
                throw new \Exception('Response signature verification failed');
            }

            $this->logger->info('Payment initialized successfully', [
                'orderNo' => $orderData['orderNo'],
                'payId' => $responseData['payId'] ?? null
            ]);

            return $responseData;

        } catch (RequestException $e) {
            $this->logger->error('Payment initialization failed', [
                'orderNo' => $orderData['orderNo'],
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Payment initialization failed: ' . $e->getMessage());
        }
    }

    /**
     * Get payment status
     */
    public function getPaymentStatus(string $payId): array
    {
        try {
            $request = [
                'merchantId' => $this->merchantId,
                'payId' => $payId
            ];

            $this->signRequest($request);

            $response = $this->client->get('/payment/status', [
                'query' => $request
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (!$this->verifySignature($responseData)) {
                throw new \Exception('Response signature verification failed');
            }

            $this->logger->info('Payment status retrieved', [
                'payId' => $payId,
                'paymentStatus' => $responseData['paymentStatus'] ?? null
            ]);

            return $responseData;

        } catch (RequestException $e) {
            $this->logger->error('Failed to get payment status', [
                'payId' => $payId,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to get payment status: ' . $e->getMessage());
        }
    }

    /**
     * Process payment return from gateway
     */
    public function processPaymentReturn(array $returnData): array
    {
        try {
            // Verify return signature
            if (!$this->verifySignature($returnData)) {
                throw new \Exception('Return data signature verification failed');
            }

            $paymentStatus = $returnData['paymentStatus'] ?? null;
            $payId = $returnData['payId'] ?? null;

            $this->logger->info('Payment return processed', [
                'payId' => $payId,
                'paymentStatus' => $paymentStatus
            ]);

            return [
                'success' => $paymentStatus === 7, // 7 = payment successful
                'payId' => $payId,
                'paymentStatus' => $paymentStatus,
                'statusText' => $this->getPaymentStatusText($paymentStatus),
                'returnData' => $returnData
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to process payment return', [
                'error' => $e->getMessage(),
                'returnData' => $returnData
            ]);
            throw $e;
        }
    }

    /**
     * Refund payment
     */
    public function refundPayment(string $payId, int $amount = null): array
    {
        try {
            $request = [
                'merchantId' => $this->merchantId,
                'payId' => $payId
            ];

            if ($amount !== null) {
                $request['amount'] = $this->convertToHalere($amount);
            }

            $this->signRequest($request);

            $response = $this->client->put('/payment/refund', [
                'json' => $request
            ]);

            $responseData = json_decode($response->getBody()->getContents(), true);

            if (!$this->verifySignature($responseData)) {
                throw new \Exception('Response signature verification failed');
            }

            $this->logger->info('Payment refund processed', [
                'payId' => $payId,
                'amount' => $amount,
                'resultCode' => $responseData['resultCode'] ?? null
            ]);

            return $responseData;

        } catch (RequestException $e) {
            $this->logger->error('Payment refund failed', [
                'payId' => $payId,
                'amount' => $amount,
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Payment refund failed: ' . $e->getMessage());
        }
    }

    /**
     * Sign request with private key
     */
    private function signRequest(array &$request): void
    {
        if (!$this->privateKey) {
            throw new \Exception('Private key not loaded');
        }

        // Create signature base
        $signatureBase = $this->createSignatureBase($request);
        
        // Sign with RSA-SHA256
        $signature = '';
        openssl_sign($signatureBase, $signature, $this->privateKey, OPENSSL_ALGO_SHA256);
        
        // Encode signature
        $request['signature'] = base64_encode($signature);
    }

    /**
     * Verify response signature
     */
    private function verifySignature(array $response): bool
    {
        if (!$this->publicKey || !isset($response['signature'])) {
            return false;
        }

        $signature = base64_decode($response['signature']);
        $responseForVerification = $response;
        unset($responseForVerification['signature']);
        
        $signatureBase = $this->createSignatureBase($responseForVerification);
        
        return openssl_verify($signatureBase, $signature, $this->publicKey, OPENSSL_ALGO_SHA256) === 1;
    }

    /**
     * Create signature base string
     */
    private function createSignatureBase(array $data): string
    {
        $values = [];
        $this->extractValues($data, $values);
        return implode('|', $values);
    }

    /**
     * Extract values for signature
     */
    private function extractValues($data, array &$values): void
    {
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if ($key !== 'signature') {
                    $this->extractValues($value, $values);
                }
            }
        } else {
            $values[] = $data;
        }
    }

    /**
     * Convert amount to halere (smallest currency unit)
     */
    private function convertToHalere(float $amount): int
    {
        return (int) round($amount * 100);
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
                'amount' => $this->convertToHalere($item['price'] * $item['quantity'])
            ];
        }
        
        return $formattedCart;
    }

    /**
     * Get payment status text
     */
    private function getPaymentStatusText(int $status): string
    {
        $statusTexts = [
            1 => 'Created',
            2 => 'In progress',
            3 => 'Canceled',
            4 => 'Approved',
            5 => 'Reversed',
            6 => 'Declined',
            7 => 'Waiting for settlement',
            8 => 'Settled',
            9 => 'Refunded',
            10 => 'Partially refunded'
        ];

        return $statusTexts[$status] ?? 'Unknown';
    }
}