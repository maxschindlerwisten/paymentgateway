# Go-Fresh Payment Setup Guide
## For Non-Technical Users

This guide will walk you through setting up CSOB payments on your Framer e-commerce site with automatic inventory management through Airtable.

## What You'll Accomplish

By the end of this guide, your customers will be able to:
1. Add products to cart on your Framer site
2. Enter their information and click "Pay"
3. Be redirected to secure CSOB payment page
4. Complete payment with their card
5. Return to your site with confirmation
6. Have their order automatically recorded in Airtable
7. Have your inventory automatically updated

## Prerequisites Checklist

Before starting, ensure you have:
- [ ] A CSOB merchant account (contact 800 150 150)
- [ ] A Framer site (published and accessible)
- [ ] An Airtable account (free tier is fine)
- [ ] A Netlify account (free tier is fine)
- [ ] About 2-3 hours to complete setup

## Part 1: CSOB Account Setup (30 minutes)

### Step 1: Contact CSOB
1. Call CSOB at **800 150 150** (free from Czech Republic)
2. Tell them: "I need a merchant account for online payments with the payment gateway"
3. They will send you contracts and setup information
4. **Important**: Ask for your **Merchant ID** (starts with M1MIPS)

### Step 2: Generate Your Keys
1. Go to [https://platebnibrana.csob.cz/keygen/](https://platebnibrana.csob.cz/keygen/)
2. Click "Generate Key Pair"
3. Two files will download:
   - `YOUR_MERCHANT_ID.key` (private key) - **Keep this secret!**
   - `YOUR_MERCHANT_ID.pub` (public key) - Send this to CSOB
4. Save both files in a secure location on your computer

### Step 3: Send Public Key to CSOB
1. Email your `.pub` file to CSOB as instructed in their setup documentation
2. Wait for confirmation that your keys are registered
3. You'll receive test access first, then production access

## Part 2: Airtable Database Setup (45 minutes)

### Step 1: Create Your Airtable Base
1. Go to [airtable.com](https://airtable.com) and sign up/log in
2. Click "Create a base"
3. Choose "Start from scratch"
4. Name it "Go-Fresh Inventory"

### Step 2: Set Up Inventory Table
1. Rename the default table to "Inventory"
2. Set up these columns (click the + to add new fields):

| Field Name | Field Type | Settings |
|------------|------------|----------|
| Name | Single line text | (Product name) |
| Price | Number | Format: Currency, Symbol: Kč |
| Stock | Number | Format: Integer |
| Description | Long text | (Product descriptions) |
| SKU | Single line text | (Optional product codes) |
| Last Updated | Date & time | (Will be auto-filled) |
| Last Order Quantity | Number | (Will be auto-filled) |

### Step 3: Set Up Orders Table
1. Click the + next to your table name to add a new table
2. Name it "Orders"
3. Set up these columns:

| Field Name | Field Type | Settings |
|------------|------------|----------|
| Order Number | Single line text | (Unique order ID) |
| Payment ID | Single line text | (CSOB payment ID) |
| Customer Email | Email | (Customer email) |
| Customer Name | Single line text | (Customer name) |
| Total Amount | Number | Format: Currency, Symbol: Kč |
| Currency | Single line text | (CZK, EUR, etc.) |
| Status | Single select | Options: initiated, in_progress, confirmed, settled, cancelled, declined |
| Order Date | Date & time | (When order was placed) |
| Status Updated | Date & time | (Last status change) |
| Items | Long text | (Will contain order details) |
| Payment Method | Single line text | (Always "CSOB Gateway") |

### Step 4: Add Your Products
1. Go back to the Inventory table
2. Add all your products (you mentioned ~2000 products):
   - Fill in Name, Price, Stock, Description
   - Leave Last Updated and Last Order Quantity empty
3. **Important**: Copy the Record ID for each product:
   - Click on a product row
   - In the expanded view, copy the Record ID (starts with "rec")
   - Save these IDs - you'll need them for your website

### Step 5: Get Your Airtable Credentials
1. Go to [airtable.com/api](https://airtable.com/api)
2. Select your "Go-Fresh Inventory" base
3. Copy your **Base ID** (starts with "app") - write this down
4. Go to [airtable.com/account](https://airtable.com/account)
5. In the "API" section, click "Generate API key"
6. Copy your **API Key** (starts with "key") - write this down

## Part 3: Netlify Deployment (30 minutes)

### Step 1: Get the Code
1. Go to this GitHub repository
2. Click "Fork" in the top right to copy it to your account
3. Or download the files and create your own repository

### Step 2: Deploy to Netlify
1. Go to [netlify.com](https://netlify.com) and sign up/log in
2. Click "New site from Git"
3. Choose your GitHub account and select the repository
4. Set these build settings:
   - **Build command**: `cd netlify-functions && npm install`
   - **Publish directory**: `public`
   - **Functions directory**: `netlify-functions`
5. Click "Deploy site"

### Step 3: Configure Environment Variables
1. After deployment, go to Site Settings > Environment Variables
2. Add these variables (click "Add variable" for each):

**Required Variables:**
- **Name**: `CSOB_MERCHANT_ID`, **Value**: Your merchant ID from CSOB
- **Name**: `AIRTABLE_API_KEY`, **Value**: Your API key from Airtable
- **Name**: `AIRTABLE_BASE_ID`, **Value**: Your base ID from Airtable

**RSA Keys** (this is the tricky part):
1. Open your `.key` file in Notepad (Windows) or TextEdit (Mac)
2. Copy the entire content including the BEGIN and END lines
3. Replace every line break with `\n` (literally type backslash n)
4. Add variable **Name**: `CSOB_PRIVATE_KEY`, **Value**: the formatted key

Example of formatted key:
```
-----BEGIN PRIVATE KEY-----\nMIIEvgIBADANBgkqhkiG...\n-----END PRIVATE KEY-----
```

5. Do the same for the CSOB public key:
   - Use the file from `keys/mips_iplatebnibrana.csob.cz.pub` in this repository
   - Format it the same way
   - Add variable **Name**: `CSOB_PUBLIC_KEY`, **Value**: the formatted key

**Optional Variables:**
- **Name**: `SITE_URL`, **Value**: Your Framer site URL
- **Name**: `CSOB_API_URL`, **Value**: `https://iapi.iplatebnibrana.csob.cz/api/v1.9` (for testing)

### Step 4: Test Your Functions
1. After adding all variables, go to the Functions tab
2. You should see: payment-init, payment-status, payment-process
3. Click on each to see if they're working (no errors in logs)

## Part 4: Framer Integration (45 minutes)

### Step 1: Add the Code to Framer
1. In Framer, go to your site settings
2. Open "Custom Code"
3. In "End of `<head>` tag", add this code:

```html
<script src="https://YOUR_NETLIFY_SITE.netlify.app/gofresh-payment.js"></script>
<link rel="stylesheet" href="https://YOUR_NETLIFY_SITE.netlify.app/gofresh-payment.css">

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (window.goFreshPayment) {
        window.goFreshPayment.apiBaseUrl = 'https://YOUR_NETLIFY_SITE.netlify.app/.netlify/functions';
        window.goFreshPayment.siteUrl = 'https://YOUR_FRAMER_SITE.framer.website';
    }
});
</script>
```

**Replace**:
- `YOUR_NETLIFY_SITE` with your actual Netlify site name
- `YOUR_FRAMER_SITE` with your actual Framer site URL

### Step 2: Set Up Product Buttons
For each product on your site, you need to add special attributes to the "Add to Cart" button:

1. Select your "Add to Cart" button
2. In the properties panel, add a class name: `add-to-cart-btn`
3. Add these data attributes (in the HTML if needed):

```html
<button class="add-to-cart-btn"
        data-product-id="YOUR_AIRTABLE_RECORD_ID"
        data-product-name="Product Name"
        data-product-price="99.99"
        data-product-description="Product description">
    Add to Cart
</button>
```

**Important**: Replace `YOUR_AIRTABLE_RECORD_ID` with the actual Record ID from your Airtable inventory.

### Step 3: Create Cart Display
Add this HTML to create a cart display area:

```html
<div class="cart-summary">
    <span>Cart (<span class="cart-counter">0</span>)</span>
    <span class="cart-total">0.00 CZK</span>
</div>

<div class="cart-items-list"></div>
```

### Step 4: Create Customer Form
Add this form for customer checkout:

```html
<form class="customer-form">
    <h3>Customer Information</h3>
    
    <div class="form-group">
        <label for="name">Name *</label>
        <input type="text" name="name" id="name" required>
    </div>
    
    <div class="form-group">
        <label for="email">Email *</label>
        <input type="email" name="email" id="email" required>
    </div>
    
    <div class="form-group">
        <label for="phone">Phone</label>
        <input type="tel" name="phone" id="phone">
    </div>
    
    <button type="button" class="checkout-btn">Proceed to Payment</button>
</form>
```

### Step 5: Create Payment Return Page
1. Create a new page in Framer called "Payment Return"
2. Set the URL to `/payment-return`
3. Add some basic content - the JavaScript will handle displaying results
4. Publish your site

## Part 5: Testing (30 minutes)

### Step 1: Test the Integration
1. Go to your Framer site
2. Add a product to cart
3. Check that the cart counter updates
4. Fill in the customer form
5. Click "Proceed to Payment"
6. You should be redirected to CSOB

### Step 2: Test with CSOB Test Cards
Use these test card numbers in the CSOB payment form:
- **4056070000000008**: Successful payment
- **4056070000000016**: Declined payment

### Step 3: Verify Everything Works
After completing a test payment:
1. Check that you return to your site
2. Check your Airtable Orders table for the new order
3. Check your Airtable Inventory table - stock should be reduced
4. Check Netlify function logs for any errors

## Part 6: Go Live (15 minutes)

### Step 1: Switch to Production
1. Contact CSOB to activate your production account
2. In Netlify, change the environment variable:
   - `CSOB_API_URL` = `https://api.platebnibrana.csob.cz/api/v1.9`
3. Use the production CSOB public key from `keys/mips_platebnibrana.csob.cz.pub`

### Step 2: Final Testing
1. Make a small real payment to test everything works
2. Verify the order appears in Airtable
3. Check inventory is updated correctly

## Troubleshooting Common Issues

### "Cart counter not updating"
- Check that your buttons have the class `add-to-cart-btn`
- Verify the data attributes are correctly set
- Check browser console for JavaScript errors

### "Payment initialization failed"
- Check all environment variables are set correctly in Netlify
- Verify your merchant ID is correct
- Check Netlify function logs for specific errors

### "Products not available"
- Verify the `data-product-id` matches your Airtable record IDs exactly
- Check your Airtable credentials are correct
- Ensure your products have stock > 0

### "Signature verification failed"
- Check your RSA keys are formatted correctly with `\n`
- Verify you're using the correct CSOB public key for your environment
- Ensure your private key matches what you sent to CSOB

## Need Help?

If you get stuck:
1. Check the detailed README_INTEGRATION.md file for technical details
2. Look at the Netlify function logs for error messages
3. Verify all your environment variables are set correctly
4. Contact CSOB support for payment gateway issues
5. Check Airtable support for database issues

## Security Reminders

- Never share your private key (.key file) with anyone
- Don't commit your private key to GitHub
- Keep your API keys secure
- Use HTTPS on all sites
- Monitor your payments regularly

Congratulations! Your Go-Fresh e-commerce site now has secure CSOB payments with automatic inventory management! 🎉