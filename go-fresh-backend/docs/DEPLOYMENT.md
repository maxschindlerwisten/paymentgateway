# Go-Fresh E-commerce Backend - Deployment Guide

## Quick Start

### 1. Server Requirements
- PHP 8.0 or higher
- Composer
- Web server (Apache/Nginx)
- SSL certificate (required for production)

### 2. Installation Steps

```bash
# Navigate to the backend directory
cd go-fresh-backend

# Install dependencies
composer install

# Copy and configure environment
cp .env.example .env
# Edit .env with your actual configuration values

# Set proper permissions
chmod -R 755 data/ logs/
chmod 600 .env

# Test the setup
php test.php
```

### 3. Configuration

#### Airtable Setup
1. Get your API key from https://airtable.com/developers/web/api/introduction
2. Create a Products table with these fields:
   - Name (Single line text) - Required
   - Description (Long text)
   - Price (Currency) - Required
   - Stock (Number) - Required
   - Category (Single select) - Required
   - Images (Attachment)
   - SKU (Single line text)
   - IsOrganic (Checkbox)
   - IsVegan (Checkbox)
   - Featured (Checkbox)

#### ČSOB Payment Gateway
1. Contact ČSOB to get merchant account: 800 150 150
2. Generate keys at https://platebnibrana.csob.cz/keygen/
3. Place your private key as `config/merchant.key`
4. Submit public key to ČSOB for approval

### 4. Web Server Configuration

#### Apache (.htaccess already included)
```apache
<VirtualHost *:443>
    ServerName api.go-fresh.com
    DocumentRoot /path/to/go-fresh-backend/public
    
    SSLEngine on
    SSLCertificateFile /path/to/certificate.crt
    SSLCertificateKeyFile /path/to/private.key
    
    <Directory /path/to/go-fresh-backend/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

#### Nginx
```nginx
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

### 5. Testing Your API

```bash
# Test products endpoint
curl https://api.go-fresh.com/api/products

# Test cart functionality
curl -X POST https://api.go-fresh.com/api/cart/add \
  -H "Content-Type: application/json" \
  -d '{"productId":"recXXXXXXXXXXXXXX","quantity":2}'

# Test categories
curl https://api.go-fresh.com/api/categories
```

### 6. Frontend Integration

#### HTML/JavaScript Example
```html
<!DOCTYPE html>
<html>
<head>
    <title>Go-Fresh Products</title>
</head>
<body>
    <div id="products"></div>
    
    <script>
    async function loadProducts() {
        const response = await fetch('https://api.go-fresh.com/api/products');
        const data = await response.json();
        
        if (data.success) {
            const productsDiv = document.getElementById('products');
            data.data.products.forEach(product => {
                const productDiv = document.createElement('div');
                productDiv.innerHTML = `
                    <h3>${product.name}</h3>
                    <p>Price: ${product.price} ${product.currency}</p>
                    <button onclick="addToCart('${product.id}')">Add to Cart</button>
                `;
                productsDiv.appendChild(productDiv);
            });
        }
    }
    
    async function addToCart(productId) {
        const response = await fetch('https://api.go-fresh.com/api/cart/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ productId, quantity: 1 })
        });
        
        const result = await response.json();
        if (result.success) {
            alert('Added to cart!');
        }
    }
    
    loadProducts();
    </script>
</body>
</html>
```

#### Framer Code Component
```javascript
import { useState, useEffect } from "react"

export default function ProductGrid() {
    const [products, setProducts] = useState([])
    const [loading, setLoading] = useState(true)

    useEffect(() => {
        fetch('https://api.go-fresh.com/api/products')
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setProducts(data.data.products)
                }
                setLoading(false)
            })
    }, [])

    const addToCart = async (productId) => {
        const response = await fetch('https://api.go-fresh.com/api/cart/add', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'include',
            body: JSON.stringify({ productId, quantity: 1 })
        })

        const result = await response.json()
        if (result.success) {
            // Update UI or show success message
            console.log('Added to cart:', result.data.cart)
        }
    }

    if (loading) return <div>Loading products...</div>

    return (
        <div className="product-grid">
            {products.map(product => (
                <div key={product.id} className="product-card">
                    <img src={product.thumbnail} alt={product.name} />
                    <h3>{product.name}</h3>
                    <p>{product.price} {product.currency}</p>
                    <button onClick={() => addToCart(product.id)}>
                        Add to Cart
                    </button>
                </div>
            ))}
        </div>
    )
}
```

### 7. Security Considerations

- Always use HTTPS in production
- Keep .env file secure (chmod 600)
- Regularly update dependencies
- Monitor logs for suspicious activity
- Backup data directory regularly
- Use strong merchant key passwords

### 8. Monitoring & Maintenance

#### Log Monitoring
```bash
# Check API logs
tail -f logs/api.log

# Check error logs
tail -f /var/log/apache2/error.log
```

#### Performance Monitoring
- Monitor response times for API endpoints
- Check Airtable API rate limits
- Monitor ČSOB payment success rates
- Set up alerts for 4xx/5xx responses

#### Backup Strategy
```bash
# Backup data directory
tar -czf backup-$(date +%Y%m%d).tar.gz data/

# Backup configuration
cp .env .env.backup
```

### 9. Troubleshooting

#### Common Issues

**"Service initialization failed"**
- Check .env configuration
- Verify Airtable API key and base ID
- Ensure directories are writable

**"Cart validation failed"**
- Check Airtable connection
- Verify product data structure
- Check stock levels

**"Payment initialization failed"**
- Verify ČSOB merchant ID
- Check key file paths and permissions
- Test ČSOB connectivity

**CORS errors**
- Update FRONTEND_URL in .env
- Check web server CORS configuration

### 10. Production Checklist

- [ ] SSL certificate installed and working
- [ ] Environment variables configured
- [ ] ČSOB merchant account activated
- [ ] Airtable products populated
- [ ] Web server security headers configured
- [ ] Log rotation configured
- [ ] Backup system in place
- [ ] Monitoring alerts configured
- [ ] API endpoints tested
- [ ] Frontend integration tested
- [ ] Payment flow tested (sandbox)

### Support

For technical issues:
1. Check logs in `logs/api.log`
2. Run `php test.php` to verify setup
3. Test individual API endpoints
4. Verify environment configuration
5. Check web server configuration

**API Base URL**: https://api.go-fresh.com
**Documentation**: See `docs/API.md`
**OpenAPI Spec**: See `docs/openapi.json`