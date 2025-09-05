<?php

namespace GoFresh\Controllers;

use GoFresh\Services\AirtableService;
use Monolog\Logger;

/**
 * Products API Controller
 * Handles all product-related API endpoints for the Framer frontend
 */
class ProductsController
{
    private AirtableService $airtableService;
    private Logger $logger;

    public function __construct(AirtableService $airtableService, Logger $logger)
    {
        $this->airtableService = $airtableService;
        $this->logger = $logger;
    }

    /**
     * GET /api/products - Get all products with pagination
     */
    public function getAllProducts(): array
    {
        try {
            $limit = (int) ($_GET['limit'] ?? 20);
            $offset = $_GET['offset'] ?? null;
            $category = $_GET['category'] ?? null;
            $search = $_GET['search'] ?? null;

            // Validate limit
            $limit = min(max($limit, 1), 100); // Between 1 and 100

            if ($search) {
                $products = $this->airtableService->searchProducts($search, $limit);
                $data = ['records' => $products];
            } elseif ($category) {
                $products = $this->airtableService->getProductsByCategory($category, $limit);
                $data = ['records' => $products];
            } else {
                $data = $this->airtableService->getAllProducts($limit, $offset);
            }

            // Transform products for frontend
            $transformedProducts = $this->transformProductsForFrontend($data['records'] ?? []);

            return [
                'success' => true,
                'data' => [
                    'products' => $transformedProducts,
                    'pagination' => [
                        'limit' => $limit,
                        'offset' => $data['offset'] ?? null,
                        'hasMore' => isset($data['offset'])
                    ]
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch products', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Failed to fetch products',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/products/{id} - Get single product
     */
    public function getProduct(string $productId): array
    {
        try {
            $product = $this->airtableService->getProduct($productId);

            if (!$product) {
                return [
                    'success' => false,
                    'error' => 'Product not found',
                    'message' => 'The requested product could not be found'
                ];
            }

            return [
                'success' => true,
                'data' => $this->transformProductForFrontend($product)
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch product', [
                'productId' => $productId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => 'Failed to fetch product',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/categories - Get all product categories
     */
    public function getCategories(): array
    {
        try {
            $categories = $this->airtableService->getCategories();

            return [
                'success' => true,
                'data' => [
                    'categories' => $categories
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to fetch categories', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Failed to fetch categories',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * GET /api/products/search - Search products
     */
    public function searchProducts(): array
    {
        try {
            $searchTerm = $_GET['q'] ?? '';
            $limit = min(max((int) ($_GET['limit'] ?? 20), 1), 100);

            if (empty($searchTerm)) {
                return [
                    'success' => false,
                    'error' => 'Search term required',
                    'message' => 'Please provide a search term'
                ];
            }

            $products = $this->airtableService->searchProducts($searchTerm, $limit);
            $transformedProducts = $this->transformProductsForFrontend($products);

            return [
                'success' => true,
                'data' => [
                    'products' => $transformedProducts,
                    'searchTerm' => $searchTerm,
                    'count' => count($transformedProducts)
                ]
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to search products', ['error' => $e->getMessage()]);
            
            return [
                'success' => false,
                'error' => 'Search failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Transform multiple products for frontend consumption
     */
    private function transformProductsForFrontend(array $products): array
    {
        return array_map([$this, 'transformProductForFrontend'], $products);
    }

    /**
     * Transform single product for frontend consumption
     */
    private function transformProductForFrontend(array $product): array
    {
        $fields = $product['fields'] ?? [];
        
        return [
            'id' => $product['id'],
            'name' => $fields['Name'] ?? '',
            'description' => $fields['Description'] ?? '',
            'price' => (float) ($fields['Price'] ?? 0),
            'originalPrice' => (float) ($fields['OriginalPrice'] ?? 0),
            'currency' => $fields['Currency'] ?? 'CZK',
            'category' => $fields['Category'] ?? '',
            'subcategory' => $fields['Subcategory'] ?? '',
            'brand' => $fields['Brand'] ?? '',
            'sku' => $fields['SKU'] ?? '',
            'stock' => (int) ($fields['Stock'] ?? 0),
            'inStock' => ((int) ($fields['Stock'] ?? 0)) > 0,
            'images' => $this->extractImages($fields['Images'] ?? []),
            'thumbnail' => $this->extractThumbnail($fields['Images'] ?? []),
            'weight' => (float) ($fields['Weight'] ?? 0),
            'dimensions' => [
                'length' => (float) ($fields['Length'] ?? 0),
                'width' => (float) ($fields['Width'] ?? 0),
                'height' => (float) ($fields['Height'] ?? 0)
            ],
            'tags' => $this->extractTags($fields['Tags'] ?? ''),
            'nutritionalInfo' => [
                'calories' => (int) ($fields['Calories'] ?? 0),
                'protein' => (float) ($fields['Protein'] ?? 0),
                'carbs' => (float) ($fields['Carbs'] ?? 0),
                'fat' => (float) ($fields['Fat'] ?? 0),
                'fiber' => (float) ($fields['Fiber'] ?? 0)
            ],
            'origin' => $fields['Origin'] ?? '',
            'expiryDate' => $fields['ExpiryDate'] ?? null,
            'isOrganic' => (bool) ($fields['IsOrganic'] ?? false),
            'isVegan' => (bool) ($fields['IsVegan'] ?? false),
            'isGlutenFree' => (bool) ($fields['IsGlutenFree'] ?? false),
            'allergens' => $this->extractTags($fields['Allergens'] ?? ''),
            'featured' => (bool) ($fields['Featured'] ?? false),
            'discountPercentage' => $this->calculateDiscountPercentage($fields),
            'createdAt' => $product['createdTime'] ?? null,
            'rating' => (float) ($fields['Rating'] ?? 0),
            'reviewCount' => (int) ($fields['ReviewCount'] ?? 0)
        ];
    }

    /**
     * Extract image URLs from Airtable attachment field
     */
    private function extractImages(array $imageAttachments): array
    {
        $images = [];
        
        foreach ($imageAttachments as $attachment) {
            if (isset($attachment['url'])) {
                $images[] = [
                    'url' => $attachment['url'],
                    'filename' => $attachment['filename'] ?? '',
                    'size' => $attachment['size'] ?? 0,
                    'type' => $attachment['type'] ?? ''
                ];
            }
        }
        
        return $images;
    }

    /**
     * Get thumbnail image from attachments
     */
    private function extractThumbnail(array $imageAttachments): ?string
    {
        if (!empty($imageAttachments) && isset($imageAttachments[0]['url'])) {
            return $imageAttachments[0]['url'];
        }
        
        return null;
    }

    /**
     * Extract tags from comma-separated string
     */
    private function extractTags(string $tagsString): array
    {
        if (empty($tagsString)) {
            return [];
        }
        
        return array_map('trim', explode(',', $tagsString));
    }

    /**
     * Calculate discount percentage
     */
    private function calculateDiscountPercentage(array $fields): float
    {
        $price = (float) ($fields['Price'] ?? 0);
        $originalPrice = (float) ($fields['OriginalPrice'] ?? 0);
        
        if ($originalPrice > 0 && $price < $originalPrice) {
            return round((($originalPrice - $price) / $originalPrice) * 100, 2);
        }
        
        return 0;
    }
}