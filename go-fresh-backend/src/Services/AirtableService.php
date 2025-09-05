<?php

namespace GoFresh\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use Monolog\Logger;

/**
 * Airtable Service for product management
 * Handles all interactions with Airtable CMS for the Go-Fresh store
 */
class AirtableService
{
    private Client $client;
    private Logger $logger;
    private string $apiKey;
    private string $baseId;
    private string $productsTable;

    public function __construct(Logger $logger, string $apiKey, string $baseId, string $productsTable = 'Products')
    {
        $this->logger = $logger;
        $this->apiKey = $apiKey;
        $this->baseId = $baseId;
        $this->productsTable = $productsTable;
        
        $this->client = new Client([
            'base_uri' => 'https://api.airtable.com/v0/',
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ]
        ]);
    }

    /**
     * Get all products from Airtable
     */
    public function getAllProducts(int $limit = 100, string $offset = null): array
    {
        try {
            $params = ['maxRecords' => $limit];
            if ($offset) {
                $params['offset'] = $offset;
            }

            $response = $this->client->get(
                "{$this->baseId}/{$this->productsTable}",
                ['query' => $params]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            
            $this->logger->info('Retrieved products from Airtable', [
                'count' => count($data['records'] ?? [])
            ]);

            return $data;
        } catch (RequestException $e) {
            $this->logger->error('Failed to fetch products from Airtable', [
                'error' => $e->getMessage()
            ]);
            throw new \Exception('Failed to fetch products: ' . $e->getMessage());
        }
    }

    /**
     * Get a specific product by ID
     */
    public function getProduct(string $productId): ?array
    {
        try {
            $response = $this->client->get("{$this->baseId}/{$this->productsTable}/{$productId}");
            $data = json_decode($response->getBody()->getContents(), true);
            
            return $data;
        } catch (RequestException $e) {
            $this->logger->error('Failed to fetch product from Airtable', [
                'productId' => $productId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Search products by name or category
     */
    public function searchProducts(string $searchTerm, int $limit = 50): array
    {
        try {
            $formula = "OR(SEARCH(LOWER('{$searchTerm}'), LOWER({Name})), SEARCH(LOWER('{$searchTerm}'), LOWER({Category})))";
            
            $response = $this->client->get(
                "{$this->baseId}/{$this->productsTable}",
                [
                    'query' => [
                        'filterByFormula' => $formula,
                        'maxRecords' => $limit
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $data['records'] ?? [];
        } catch (RequestException $e) {
            $this->logger->error('Failed to search products in Airtable', [
                'searchTerm' => $searchTerm,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get products by category
     */
    public function getProductsByCategory(string $category, int $limit = 100): array
    {
        try {
            $formula = "LOWER({Category}) = LOWER('{$category}')";
            
            $response = $this->client->get(
                "{$this->baseId}/{$this->productsTable}",
                [
                    'query' => [
                        'filterByFormula' => $formula,
                        'maxRecords' => $limit
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            
            return $data['records'] ?? [];
        } catch (RequestException $e) {
            $this->logger->error('Failed to fetch products by category from Airtable', [
                'category' => $category,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Get all categories
     */
    public function getCategories(): array
    {
        try {
            $response = $this->client->get(
                "{$this->baseId}/{$this->productsTable}",
                [
                    'query' => [
                        'fields' => ['Category'],
                        'maxRecords' => 2000
                    ]
                ]
            );

            $data = json_decode($response->getBody()->getContents(), true);
            $categories = [];

            foreach ($data['records'] ?? [] as $record) {
                $category = $record['fields']['Category'] ?? null;
                if ($category && !in_array($category, $categories)) {
                    $categories[] = $category;
                }
            }

            sort($categories);
            return $categories;
        } catch (RequestException $e) {
            $this->logger->error('Failed to fetch categories from Airtable', [
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    /**
     * Update product stock in Airtable (if needed for inventory management)
     */
    public function updateProductStock(string $productId, int $newStock): bool
    {
        try {
            $response = $this->client->patch(
                "{$this->baseId}/{$this->productsTable}/{$productId}",
                [
                    'json' => [
                        'fields' => [
                            'Stock' => $newStock
                        ]
                    ]
                ]
            );

            $this->logger->info('Updated product stock in Airtable', [
                'productId' => $productId,
                'newStock' => $newStock
            ]);

            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            $this->logger->error('Failed to update product stock in Airtable', [
                'productId' => $productId,
                'newStock' => $newStock,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}