# Go-Fresh E-commerce API Documentation

## Overview

The Go-Fresh API provides a complete e-commerce backend solution with Airtable CMS integration and ČSOB payment processing.

**Base URL**: `https://api.go-fresh.com`

## Authentication

Most endpoints require no authentication for reading product data. Cart and checkout operations use session-based authentication.

## Rate Limiting

- **Public endpoints**: 100 requests per minute
- **Cart operations**: 60 requests per minute  
- **Checkout operations**: 10 requests per minute

## Response Format

All responses follow this structure:

```json
{
  "success": boolean,
  "message": "string",
  "data": {},
  "error": "string" // Only present on errors
}
```

## Products API

### Get All Products

**GET** `/api/products`

**Parameters:**
- `limit` (optional): Number of products to return (1-100, default: 20)
- `offset` (optional): Pagination offset
- `category` (optional): Filter by category
- `search` (optional): Search term

**Example Response:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": "recXXXXXXXXXXXXXX",
        "name": "Organic Apples",
        "description": "Fresh organic apples from local farms",
        "price": 89.50,
        "currency": "CZK",
        "category": "Fruits",
        "stock": 150,
        "inStock": true,
        "images": [
          {
            "url": "https://dl.airtable.com/...",
            "filename": "apples.jpg"
          }
        ],
        "thumbnail": "https://dl.airtable.com/...",
        "isOrganic": true,
        "isVegan": true,
        "featured": true
      }
    ],
    "pagination": {
      "limit": 20,
      "offset": "recYYYYYYYYYYYYYY",
      "hasMore": true
    }
  }
}
```

### Get Single Product

**GET** `/api/products/{id}`

**Example Response:**
```json
{
  "success": true,
  "data": {
    "id": "recXXXXXXXXXXXXXX",
    "name": "Organic Apples",
    "description": "Fresh organic apples from local farms",
    "price": 89.50,
    "originalPrice": 99.50,
    "discountPercentage": 10.05,
    "currency": "CZK",
    "category": "Fruits",
    "subcategory": "Fresh Fruits",
    "brand": "Local Farm Co.",
    "sku": "APL-ORG-001",
    "stock": 150,
    "inStock": true,
    "weight": 0.5,
    "dimensions": {
      "length": 0,
      "width": 0,
      "height": 0
    },
    "nutritionalInfo": {
      "calories": 52,
      "protein": 0.3,
      "carbs": 14,
      "fat": 0.2,
      "fiber": 2.4
    },
    "origin": "Czech Republic",
    "isOrganic": true,
    "isVegan": true,
    "isGlutenFree": true,
    "allergens": [],
    "tags": ["fresh", "local", "seasonal"],
    "rating": 4.8,
    "reviewCount": 127,
    "featured": true
  }
}
```

### Search Products

**GET** `/api/products/search`

**Parameters:**
- `q` (required): Search term
- `limit` (optional): Number of results (1-100, default: 20)

### Get Categories

**GET** `/api/categories`

**Example Response:**
```json
{
  "success": true,
  "data": {
    "categories": [
      "Fruits",
      "Vegetables", 
      "Dairy",
      "Meat & Poultry",
      "Bakery",
      "Beverages"
    ]
  }
}
```

## Cart API

### Get Cart

**GET** `/api/cart`

**Example Response:**
```json
{
  "success": true,
  "data": {
    "cart": {
      "itemCount": 2,
      "totalItems": 3,
      "subtotal": 189.50,
      "tax": 39.80,
      "taxRate": 0.21,
      "total": 229.30,
      "items": {
        "recXXXXXXXXXXXXXX": {
          "productId": "recXXXXXXXXXXXXXX",
          "name": "Organic Apples",
          "price": 89.50,
          "quantity": 2,
          "image": "https://dl.airtable.com/...",
          "addedAt": "2024-01-15 14:30:00"
        }
      }
    }
  }
}
```

### Add to Cart

**POST** `/api/cart/add`

**Request Body:**
```json
{
  "productId": "recXXXXXXXXXXXXXX",
  "quantity": 2
}
```

### Update Cart

**PUT** `/api/cart/update`

**Request Body:**
```json
{
  "productId": "recXXXXXXXXXXXXXX",
  "quantity": 3
}
```

### Remove from Cart

**DELETE** `/api/cart/remove/{productId}`

### Clear Cart

**DELETE** `/api/cart/clear`

### Validate Cart

**POST** `/api/cart/validate`

Validates all cart items against current product data (stock, prices, availability).

### Bulk Add to Cart

**POST** `/api/cart/bulk-add`

**Request Body:**
```json
{
  "products": [
    {
      "productId": "recXXXXXXXXXXXXXX",
      "quantity": 2
    },
    {
      "productId": "recYYYYYYYYYYYYYY", 
      "quantity": 1
    }
  ]
}
```

## Checkout API

### Validate Checkout

**POST** `/api/checkout/validate`

**Request Body:**
```json
{
  "customerInfo": {
    "email": "customer@example.com",
    "firstName": "Jan",
    "lastName": "Novák",
    "phone": "+420123456789"
  },
  "shippingInfo": {
    "address": "Wenceslas Square 1",
    "city": "Prague",
    "postalCode": "11000",
    "country": "Czech Republic"
  }
}
```

### Create Order

**POST** `/api/checkout/create-order`

**Request Body:**
```json
{
  "customerInfo": {
    "email": "customer@example.com",
    "firstName": "Jan",
    "lastName": "Novák",
    "phone": "+420123456789"
  },
  "shippingInfo": {
    "address": "Wenceslas Square 1",
    "city": "Prague",
    "postalCode": "11000",
    "country": "Czech Republic"
  },
  "billingInfo": {
    "address": "Wenceslas Square 1",
    "city": "Prague", 
    "postalCode": "11000",
    "country": "Czech Republic"
  },
  "paymentMethod": "card",
  "language": "cs",
  "notes": "Please ring doorbell"
}
```

**Example Response:**
```json
{
  "success": true,
  "message": "Order created successfully",
  "data": {
    "order": {
      "id": "550e8400-e29b-41d4-a716-446655440000",
      "orderNo": "GF2401150001",
      "status": "pending",
      "totalAmount": 229.30,
      "currency": "CZK",
      "createdAt": "2024-01-15 14:30:00"
    },
    "payment": {
      "payId": "123456789",
      "paymentUrl": "https://platebnibrana.csob.cz/pay/123456789",
      "status": "initialized"
    }
  }
}
```

### Payment Return Handler

**POST** `/api/checkout/payment-return`

Handles return from ČSOB payment gateway. This endpoint is called automatically by the payment gateway.

### Get Payment Status

**GET** `/api/checkout/payment-status/{payId}`

### Get Shipping Rates

**GET** `/api/checkout/shipping-rates`

**Example Response:**
```json
{
  "success": true,
  "data": {
    "shippingRates": [
      {
        "id": "standard",
        "name": "Standard Delivery",
        "description": "Delivery within 3-5 business days",
        "price": 99.00,
        "currency": "CZK",
        "estimatedDays": 5
      },
      {
        "id": "express",
        "name": "Express Delivery", 
        "description": "Next business day delivery",
        "price": 199.00,
        "currency": "CZK",
        "estimatedDays": 1
      }
    ]
  }
}
```

## Error Responses

**400 Bad Request:**
```json
{
  "success": false,
  "error": "Validation failed",
  "message": "Please correct the following errors",
  "data": {
    "validationErrors": {
      "email": "Valid email is required",
      "firstName": "First name is required"
    }
  }
}
```

**404 Not Found:**
```json
{
  "success": false,
  "error": "Product not found",
  "message": "The requested product could not be found"
}
```

**500 Internal Server Error:**
```json
{
  "success": false,
  "error": "Internal server error",
  "message": "An unexpected error occurred"
}
```

## Integration Examples

### React/Next.js Integration

```javascript
// API client
class GoFreshAPI {
  constructor(baseURL = 'https://api.go-fresh.com') {
    this.baseURL = baseURL;
  }

  async request(endpoint, options = {}) {
    const response = await fetch(`${this.baseURL}${endpoint}`, {
      credentials: 'include', // For session management
      headers: {
        'Content-Type': 'application/json',
        ...options.headers,
      },
      ...options,
    });

    return response.json();
  }

  // Products
  async getProducts(params = {}) {
    const query = new URLSearchParams(params).toString();
    return this.request(`/api/products?${query}`);
  }

  async getProduct(id) {
    return this.request(`/api/products/${id}`);
  }

  // Cart
  async getCart() {
    return this.request('/api/cart');
  }

  async addToCart(productId, quantity) {
    return this.request('/api/cart/add', {
      method: 'POST',
      body: JSON.stringify({ productId, quantity }),
    });
  }

  // Checkout
  async createOrder(orderData) {
    return this.request('/api/checkout/create-order', {
      method: 'POST',
      body: JSON.stringify(orderData),
    });
  }
}

// Usage
const api = new GoFreshAPI();

// Get products
const { data } = await api.getProducts({ category: 'Fruits', limit: 10 });
console.log(data.products);

// Add to cart
await api.addToCart('recXXXXXXXXXXXXXX', 2);
```

### Framer Code Components

```javascript
// Product List Component
export default function ProductList() {
  const [products, setProducts] = useState([]);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    fetch('https://api.go-fresh.com/api/products')
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          setProducts(data.data.products);
        }
        setLoading(false);
      });
  }, []);

  if (loading) return <div>Loading...</div>;

  return (
    <div className="product-grid">
      {products.map(product => (
        <ProductCard key={product.id} product={product} />
      ))}
    </div>
  );
}

// Add to Cart Component
export default function AddToCartButton({ productId, quantity = 1 }) {
  const [adding, setAdding] = useState(false);

  const handleAddToCart = async () => {
    setAdding(true);
    
    try {
      const response = await fetch('https://api.go-fresh.com/api/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        credentials: 'include',
        body: JSON.stringify({ productId, quantity })
      });

      const result = await response.json();
      
      if (result.success) {
        // Show success message or update cart UI
        console.log('Added to cart:', result.data.cart);
      } else {
        console.error('Failed to add to cart:', result.message);
      }
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setAdding(false);
    }
  };

  return (
    <button 
      onClick={handleAddToCart} 
      disabled={adding}
      className="add-to-cart-btn"
    >
      {adding ? 'Adding...' : 'Add to Cart'}
    </button>
  );
}
```