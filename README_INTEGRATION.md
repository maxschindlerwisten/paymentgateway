# Go-Fresh CSOB Payment Gateway Integration

Complete integration between Framer, CSOB payment gateway, and Airtable CMS for Go-Fresh e-commerce site.

## Table of Contents

1. [Overview](#overview)
2. [Prerequisites](#prerequisites)
3. [CSOB Setup](#csob-setup)
4. [Netlify Deployment](#netlify-deployment)
5. [Airtable Configuration](#airtable-configuration)
6. [Framer Integration](#framer-integration)
7. [Testing](#testing)
8. [Troubleshooting](#troubleshooting)

## Overview

This integration provides:
- Serverless functions for CSOB payment gateway integration
- JavaScript library for Framer sites
- Automatic inventory management through Airtable
- Secure payment processing with digital signatures
- Complete order tracking and status updates

### Architecture

```
Framer Site (Frontend)
    ↓
Netlify Functions (Serverless Backend)
    ↓
CSOB Payment Gateway (Payment Processing)
    ↓
Airtable CMS (Inventory & Orders)
```

## Prerequisites

### Required Accounts
- [ ] CSOB merchant account with payment gateway access
- [ ] Netlify account (free tier sufficient)
- [ ] Airtable account (free tier sufficient)
- [ ] Framer account with published site

### Technical Requirements
- Basic knowledge of copy/paste for code snippets
- Access to your Framer site's custom code settings
- Access to your Netlify environment variables

## CSOB Setup

### 1. Generate RSA Key Pair

1. Go to [https://platebnibrana.csob.cz/keygen/](https://platebnibrana.csob.cz/keygen/)
2. Click "Generate Key Pair"
3. Download both files:
   - `YOUR_MERCHANT_ID.key` (private key)
   - `YOUR_MERCHANT_ID.pub` (public key)
4. **IMPORTANT**: Keep the private key secure and never share it

### 2. Get CSOB Public Keys

The CSOB public keys are already included in this repository:
- Test environment: `keys/mips_iplatebnibrana.csob.cz.pub`
- Production: `keys/mips_platebnibrana.csob.cz.pub`

### 3. Merchant Registration

1. Contact CSOB at 800 150 150 to set up your merchant account
2. Provide your public key (`YOUR_MERCHANT_ID.pub`) to CSOB
3. Receive your merchant ID and gateway access credentials
4. Test access using the integration environment first

## Netlify Deployment

### 1. Deploy Functions

1. **Create a new Netlify site:**
   - Connect your GitHub repository
   - Set build command: `cd netlify-functions && npm install`
   - Set functions directory: `netlify-functions`

2. **Or deploy manually:**
   ```bash
   # In the netlify-functions directory
   npm install
   netlify deploy --functions=. --prod
   ```

### 2. Configure Environment Variables

In your Netlify dashboard, go to Site Settings > Environment Variables and add:

#### Required Variables

| Variable | Description | Example |
|----------|-------------|---------|
| `CSOB_MERCHANT_ID` | Your CSOB merchant ID | `M1MIPSXXXX` |
| `CSOB_PRIVATE_KEY` | Your private key content (full PEM) | `-----BEGIN PRIVATE KEY-----\n...` |
| `CSOB_PUBLIC_KEY` | CSOB public key content | `-----BEGIN PUBLIC KEY-----\n...` |
| `AIRTABLE_API_KEY` | Your Airtable API key | `keyXXXXXXXXXXXXXX` |
| `AIRTABLE_BASE_ID` | Your Airtable base ID | `appXXXXXXXXXXXXXX` |

#### Optional Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `CSOB_API_URL` | `https://iapi.iplatebnibrana.csob.cz/api/v1.9` | CSOB API endpoint |
| `CSOB_PRIVATE_KEY_PASSWORD` | `null` | Private key password if set |
| `AIRTABLE_INVENTORY_TABLE` | `Inventory` | Airtable inventory table name |
| `AIRTABLE_ORDERS_TABLE` | `Orders` | Airtable orders table name |
| `SITE_URL` | | Your Framer site URL for return URLs |

### 3. Format RSA Keys for Environment Variables

**For private key:**
1. Open your `.key` file in a text editor
2. Copy the entire content including `-----BEGIN PRIVATE KEY-----` and `-----END PRIVATE KEY-----`
3. Replace all line breaks with `\n`
4. Paste into the `CSOB_PRIVATE_KEY` environment variable

**For CSOB public key:**
1. Copy the content from `keys/mips_iplatebnibrana.csob.cz.pub`
2. Replace all line breaks with `\n`
3. Paste into the `CSOB_PUBLIC_KEY` environment variable

**Example:**
```
-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG9w0BAQEFAASCBKgwggSkAgEAAoIBAQC...\n-----END PRIVATE KEY-----
```

## Airtable Configuration

### 1. Get API Credentials

1. Go to [airtable.com/api](https://airtable.com/api)
2. Select your base
3. Copy your Base ID (starts with `app`)
4. Go to [airtable.com/account](https://airtable.com/account)
5. Generate and copy your API key (starts with `key`)

### 2. Set Up Tables

#### Inventory Table

Create a table named "Inventory" with these fields:

| Field Name | Field Type | Description |
|------------|------------|-------------|
| `Name` | Single line text | Product name |
| `Price` | Number | Product price |
| `Stock` | Number | Available quantity |
| `Description` | Long text | Product description |
| `SKU` | Single line text | Product SKU (optional) |
| `Last Updated` | Date & time | Auto-updated by system |
| `Last Order Quantity` | Number | Last purchased quantity |

#### Orders Table

Create a table named "Orders" with these fields:

| Field Name | Field Type | Description |
|------------|------------|-------------|
| `Order Number` | Single line text | Unique order identifier |
| `Payment ID` | Single line text | CSOB payment ID |
| `Customer Email` | Email | Customer email address |
| `Customer Name` | Single line text | Customer name |
| `Total Amount` | Number | Order total in main currency |
| `Currency` | Single line text | Currency code |
| `Status` | Single select | Order status |
| `Order Date` | Date & time | Order creation date |
| `Status Updated` | Date & time | Last status update |
| `Items` | Long text | JSON string of ordered items |
| `Payment Method` | Single line text | Always "CSOB Gateway" |

#### Status Options

For the Status field in Orders table, add these options:
- `initiated` (default)
- `in_progress`
- `confirmed`
- `settled`
- `cancelled`
- `declined`

### 3. Populate Products

Add your ~2000 products to the Inventory table with:
- Unique record IDs (automatically generated)
- Product names and descriptions
- Current prices
- Available stock quantities

## Framer Integration

### 1. Add JavaScript Library

1. In Framer, go to your site settings
2. Open the "Custom Code" section
3. In the "End of `<head>` tag" field, add:

```html
<script src="https://YOUR_NETLIFY_SITE.netlify.app/gofresh-payment.js"></script>
<link rel="stylesheet" href="https://YOUR_NETLIFY_SITE.netlify.app/gofresh-payment.css">

<script>
// Configure the payment integration
document.addEventListener('DOMContentLoaded', function() {
    if (window.goFreshPayment) {
        // Update configuration
        window.goFreshPayment.apiBaseUrl = 'https://YOUR_NETLIFY_SITE.netlify.app/.netlify/functions';
        window.goFreshPayment.siteUrl = 'https://YOUR_FRAMER_SITE.framer.website';
    }
});
</script>
```

### 2. Serve Static Files

Host the JavaScript and CSS files on your Netlify site:

1. Create a `public` folder in your repository
2. Copy `gofresh-payment.js` and `gofresh-payment.css` to the `public` folder
3. Configure Netlify to serve these as static files
4. Update the script/link tags to point to your Netlify URL

### 3. Product HTML Structure

For each product on your Framer site, use this HTML structure:

```html
<div class="product">
    <h3>Product Name</h3>
    <p>Product description...</p>
    <p class="price">99.99 CZK</p>
    
    <button class="add-to-cart-btn"
            data-product-id="YOUR_AIRTABLE_RECORD_ID"
            data-product-name="Product Name"
            data-product-price="99.99"
            data-product-description="Product description">
        Add to Cart
    </button>
</div>
```

**Important:** Replace `YOUR_AIRTABLE_RECORD_ID` with the actual record ID from your Airtable inventory.

### 4. Cart Display

Add cart display elements to your site:

```html
<div class="cart-summary">
    <span>Cart (<span class="cart-counter">0</span>)</span>
    <span class="cart-total">0.00 CZK</span>
</div>

<div class="cart-page">
    <h2>Your Cart</h2>
    <div class="cart-items-list"></div>
    
    <form class="customer-form">
        <h3>Customer Information</h3>
        <div class="form-group">
            <label for="name">Name <span class="required">*</span></label>
            <input type="text" name="name" id="name" required>
        </div>
        <div class="form-group">
            <label for="email">Email <span class="required">*</span></label>
            <input type="email" name="email" id="email" required>
        </div>
        <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" name="phone" id="phone">
        </div>
        
        <button type="button" class="checkout-btn">Proceed to Payment</button>
    </form>
</div>
```

### 5. Payment Return Page

Create a page at `/payment-return` on your Framer site. The JavaScript library will automatically handle the return flow and display success/error messages.

## Testing

### 1. Test Environment Setup

Use the CSOB test environment for initial testing:

```
CSOB_API_URL=https://iapi.iplatebnibrana.csob.cz/api/v1.9
```

### 2. Test Cards

Use these test card numbers in the CSOB test environment:

| Card Number | Result |
|-------------|--------|
| 4056070000000008 | Successful payment |
| 4056070000000016 | Declined payment |
| 4056070000000024 | Timeout |

### 3. Test Workflow

1. **Add products to cart:**
   - Click "Add to Cart" buttons
   - Verify cart counter updates
   - Check cart display

2. **Checkout process:**
   - Fill in customer form
   - Click "Proceed to Payment"
   - Verify redirect to CSOB

3. **Payment completion:**
   - Complete payment on CSOB
   - Verify return to your site
   - Check Airtable for order record
   - Verify inventory updates

### 4. Monitor Logs

Check Netlify function logs for any errors:
1. Go to Netlify dashboard
2. Select your site
3. Go to Functions tab
4. Click on any function to see logs

## Troubleshooting

### Common Issues

#### 1. "Missing required environment variables"
- Check all environment variables are set in Netlify
- Verify variable names match exactly
- Ensure RSA keys are properly formatted with `\n`

#### 2. "Signature verification failed"
- Check that CSOB public key is correct
- Verify your private key is properly formatted
- Ensure using correct environment (test vs production)

#### 3. "Products not available"
- Check Airtable record IDs match `data-product-id` attributes
- Verify Airtable credentials are correct
- Check product stock quantities in Airtable

#### 4. "Payment initialization failed"
- Verify CSOB merchant ID is correct
- Check API URL (test vs production)
- Review function logs for detailed error messages

### Debug Mode

Enable debug logging by adding this environment variable:
```
DEBUG=true
```

### Support Contacts

- **CSOB Technical Support:** [CSOB Documentation](https://github.com/csob/paymentgateway/wiki)
- **Netlify Support:** [Netlify Documentation](https://docs.netlify.com/)
- **Airtable Support:** [Airtable Support](https://support.airtable.com/)

### Security Checklist

- [ ] Private key is stored securely in Netlify environment variables
- [ ] Private key is never committed to version control
- [ ] HTTPS is enabled on all sites
- [ ] CORS headers are properly configured
- [ ] Error messages don't expose sensitive information
- [ ] Regular security updates are applied

## Maintenance

### Regular Tasks

1. **Monitor payment success rates**
2. **Check inventory levels**
3. **Review order records**
4. **Update CSOB certificates when expired**
5. **Monitor function performance and logs**

### Backup Strategy

1. **Airtable:** Regular CSV exports
2. **Environment variables:** Document in secure location
3. **Keys:** Secure backup of private key

---

**Need Help?** If you encounter issues during setup, check the troubleshooting section or review the function logs in your Netlify dashboard for detailed error messages.