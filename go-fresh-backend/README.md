# Go-Fresh E-commerce Backend

A comprehensive PHP-based e-commerce backend for the Go-Fresh brand, integrating with ČSOB Payment Gateway and Airtable CMS.

## Features

- **Product Management**: Integration with Airtable CMS for 2000+ products
- **Shopping Cart**: Full cart functionality with session management
- **Secure Payments**: ČSOB Payment Gateway integration
- **Order Management**: Complete order lifecycle tracking
- **RESTful API**: Clean API for Framer frontend integration
- **Real-time Stock**: Automatic stock updates after purchases
- **Multi-language**: Support for Czech and English

## Architecture

```
go-fresh-backend/
├── api/                    # API documentation
├── config/                 # Configuration files
├── src/
│   ├── Controllers/        # API controllers
│   ├── Services/          # Business logic services
│   └── Models/            # Data models (if needed)
├── public/                # Web accessible files
│   └── index.php         # Main API entry point
├── data/                  # File-based storage
├── logs/                  # Application logs
└── docs/                  # Documentation
```

## Services

### AirtableService
- Product catalog management
- Real-time product data sync
- Category management
- Stock updates

### CartService
- Add/remove/update cart items
- Cart validation
- Session management
- Bulk operations

### PaymentService
- ČSOB payment gateway integration
- Payment initialization
- Status tracking
- Refund processing

### OrderService
- Order creation and management
- Payment tracking
- Order history
- Statistics

## API Endpoints

### Products
- `GET /api/products` - Get all products with pagination
- `GET /api/products/{id}` - Get single product
- `GET /api/products/search?q={term}` - Search products
- `GET /api/categories` - Get all categories

### Cart
- `GET /api/cart` - Get current cart
- `POST /api/cart/add` - Add product to cart
- `PUT /api/cart/update` - Update product quantity
- `DELETE /api/cart/remove/{productId}` - Remove product
- `DELETE /api/cart/clear` - Clear entire cart
- `POST /api/cart/validate` - Validate cart items
- `POST /api/cart/bulk-add` - Add multiple products

### Checkout
- `POST /api/checkout/validate` - Validate checkout data
- `POST /api/checkout/create-order` - Create order and initialize payment
- `POST /api/checkout/payment-return` - Handle payment return
- `GET /api/checkout/payment-status/{payId}` - Get payment status
- `GET /api/checkout/shipping-rates` - Get shipping options

## Installation

1. **Copy ČSOB Keys**: Place your merchant keys in `config/`
   ```bash
   cp your-merchant.key config/
   cp mips_iplatebnibrana.csob.cz.pub config/
   ```

2. **Configure Environment**: Copy and customize configuration
   ```bash
   cp config/.env.example config/.env
   # Edit config/.env with your settings
   ```

3. **Install Dependencies**:
   ```bash
   cd go-fresh-backend
   composer install
   ```

4. **Set Permissions**:
   ```bash
   chmod -R 755 data/ logs/
   chmod 600 config/.env
   ```

## Configuration

Edit `config/.env`:

```env
# Airtable Configuration
AIRTABLE_API_KEY=your_airtable_api_key
AIRTABLE_BASE_ID=your_airtable_base_id
AIRTABLE_PRODUCTS_TABLE=Products

# ČSOB Payment Gateway
CSOB_MERCHANT_ID=your_merchant_id
CSOB_API_URL=https://iapi.iplatebnibrana.csob.cz/api/v1.9
CSOB_PRIVATE_KEY_PATH=./config/your-merchant.key
CSOB_PUBLIC_KEY_PATH=./config/mips_iplatebnibrana.csob.cz.pub

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.go-fresh.com
FRONTEND_URL=https://go-fresh.framer.website
```

## Airtable Setup

Your Airtable Products table should include these fields:

**Required Fields:**
- `Name` (Single line text)
- `Price` (Currency)
- `Stock` (Number)
- `Category` (Single select)

**Optional Fields:**
- `Description` (Long text)
- `Images` (Attachment)
- `SKU` (Single line text)
- `Brand` (Single line text)
- `Weight` (Number)
- `IsOrganic` (Checkbox)
- `IsVegan` (Checkbox)
- `IsGlutenFree` (Checkbox)
- `Rating` (Number)
- `Featured` (Checkbox)

## Framer Integration

### Basic Product Display
```javascript
// Fetch products
const response = await fetch('https://api.go-fresh.com/api/products');
const data = await response.json();

if (data.success) {
    const products = data.data.products;
    // Display products in your Framer components
}
```

### Add to Cart
```javascript
// Add product to cart
const addToCart = async (productId, quantity = 1) => {
    const response = await fetch('https://api.go-fresh.com/api/cart/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ productId, quantity })
    });
    
    const result = await response.json();
    return result;
};
```

### Checkout Process
```javascript
// Create order and get payment URL
const checkout = async (customerInfo, shippingInfo) => {
    const response = await fetch('https://api.go-fresh.com/api/checkout/create-order', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            customerInfo,
            shippingInfo,
            paymentMethod: 'card'
        })
    });
    
    const result = await response.json();
    
    if (result.success) {
        // Redirect to ČSOB payment gateway
        window.location.href = result.data.payment.paymentUrl;
    }
};
```

## Security Features

- **Request Validation**: All inputs validated and sanitized
- **CORS Protection**: Configured for your frontend domain
- **Payment Security**: ČSOB cryptographic signatures
- **Session Management**: Secure cart session handling
- **Error Handling**: Comprehensive error logging
- **Rate Limiting**: Can be added via web server configuration

## Deployment

### Apache Configuration
```apache
<VirtualHost *:80>
    ServerName api.go-fresh.com
    DocumentRoot /path/to/go-fresh-backend/public
    
    <Directory /path/to/go-fresh-backend/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # Redirect to HTTPS
    Redirect permanent / https://api.go-fresh.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName api.go-fresh.com
    DocumentRoot /path/to/go-fresh-backend/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /path/to/go-fresh-backend/public>
        AllowOverride All
        Require all granted
        
        # Enable rewrite engine
        RewriteEngine On
        RewriteCond %{REQUEST_FILENAME} !-f
        RewriteCond %{REQUEST_FILENAME} !-d
        RewriteRule ^(.*)$ index.php [QSA,L]
    </Directory>
</VirtualHost>
```

### Nginx Configuration
```nginx
server {
    listen 80;
    server_name api.go-fresh.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl;
    server_name api.go-fresh.com;
    root /path/to/go-fresh-backend/public;
    index index.php;
    
    ssl_certificate /path/to/certificate.crt;
    ssl_certificate_key /path/to/private.key;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.0-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## Monitoring

- **Logs**: Check `logs/api.log` for application logs
- **Performance**: Monitor response times for API endpoints
- **Errors**: Set up alerts for 4xx/5xx responses
- **Payment**: Monitor ČSOB payment success rates

## Support

For technical support or questions about the Go-Fresh e-commerce backend, please contact the development team or refer to:

- [ČSOB Payment Gateway Documentation](https://github.com/csob/paymentgateway/wiki)
- [Airtable API Documentation](https://airtable.com/developers/web/api/introduction)
- [PHP Documentation](https://www.php.net/manual/en/)

## License

This project is proprietary software for Go-Fresh brand.