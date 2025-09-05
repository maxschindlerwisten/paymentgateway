# Sample Airtable Products Data Structure

This document shows the expected structure for your Airtable Products table.

## Required Fields

### Name (Single line text)
- Primary field for product name
- Example: "Organic Apples", "Fresh Milk", "Whole Wheat Bread"

### Price (Currency)
- Product price in CZK
- Example: 89.50, 45.00, 120.00

### Stock (Number)
- Available quantity
- Example: 150, 0, 25

### Category (Single select)
- Product category
- Options: "Fruits", "Vegetables", "Dairy", "Meat & Poultry", "Bakery", "Beverages", "Organic", "Frozen"

## Optional Fields

### Description (Long text)
- Detailed product description
- Example: "Fresh organic apples from local Czech farms. Rich in vitamins and fiber."

### Images (Attachment)
- Product photos
- Recommended: Multiple high-quality images

### SKU (Single line text)
- Stock keeping unit
- Example: "APL-ORG-001", "MLK-FRS-500"

### OriginalPrice (Currency)
- Price before discount
- Used to calculate discount percentage

### Subcategory (Single line text)
- More specific categorization
- Example: "Fresh Fruits", "Citrus", "Berries"

### Brand (Single line text)
- Product brand or manufacturer
- Example: "Local Farm Co.", "Go-Fresh Organic"

### Weight (Number)
- Product weight in kg
- Example: 0.5, 1.0, 2.5

### Length, Width, Height (Number)
- Dimensions in cm for shipping calculations

### Nutritional Information
- Calories (Number): per 100g
- Protein (Number): grams per 100g
- Carbs (Number): grams per 100g
- Fat (Number): grams per 100g
- Fiber (Number): grams per 100g

### Product Attributes (Checkbox)
- IsOrganic: true/false
- IsVegan: true/false
- IsGlutenFree: true/false
- Featured: true/false for homepage display

### Additional Fields
- Origin (Single line text): Country or region of origin
- ExpiryDate (Date): For perishable items
- Tags (Single line text): Comma-separated tags like "fresh, local, seasonal"
- Allergens (Single line text): Comma-separated allergens
- Rating (Number): Average customer rating (1-5)
- ReviewCount (Number): Number of customer reviews

## Sample Data

### Product 1: Organic Apples
```
Name: Organic Apples
Description: Fresh organic apples from local Czech farms. Sweet and crispy, perfect for snacking or baking.
Price: 89.50
OriginalPrice: 99.50
Currency: CZK
Category: Fruits
Subcategory: Fresh Fruits
SKU: APL-ORG-001
Stock: 150
Weight: 0.5
IsOrganic: true
IsVegan: true
IsGlutenFree: true
Featured: true
Origin: Czech Republic
Calories: 52
Protein: 0.3
Carbs: 14
Fat: 0.2
Fiber: 2.4
Tags: fresh, local, seasonal, crunchy
Rating: 4.8
ReviewCount: 127
```

### Product 2: Fresh Milk
```
Name: Fresh Milk 1L
Description: Premium fresh milk from local dairy farms. Rich in calcium and protein.
Price: 45.00
Currency: CZK
Category: Dairy
SKU: MLK-FRS-1L
Stock: 80
Weight: 1.0
IsOrganic: false
IsVegan: false
IsGlutenFree: true
Featured: false
Origin: Czech Republic
Calories: 64
Protein: 3.2
Carbs: 4.8
Fat: 3.6
Allergens: milk
Rating: 4.5
ReviewCount: 89
```

### Product 3: Whole Wheat Bread
```
Name: Whole Wheat Bread
Description: Freshly baked whole wheat bread. High in fiber and nutrients.
Price: 65.00
Currency: CZK
Category: Bakery
SKU: BRD-WW-500
Stock: 25
Weight: 0.5
IsOrganic: true
IsVegan: true
IsGlutenFree: false
Featured: true
Origin: Czech Republic
Calories: 247
Protein: 13
Carbs: 41
Fat: 4.2
Fiber: 7
Allergens: gluten, wheat
Tags: fresh, artisan, fiber-rich
Rating: 4.6
ReviewCount: 56
```

## Import Instructions

1. **Create Base**: Create new Airtable base or use existing
2. **Add Table**: Create "Products" table
3. **Add Fields**: Add all fields with correct types
4. **Configure Options**: Set up single select options for Category
5. **Import Data**: Add your 2000+ products
6. **Get Credentials**: 
   - Copy Base ID from URL (starts with "app")
   - Generate API key from Account settings
7. **Configure Backend**: Update .env file with credentials

## API Integration

The backend will automatically:
- Fetch all products with pagination
- Search by name/category/description
- Filter by category
- Handle stock validation
- Update stock after purchases
- Transform data for frontend consumption

## Performance Tips

- Use indexes on frequently queried fields
- Optimize images (compress before upload)
- Keep descriptions concise but informative
- Use consistent naming conventions
- Regular data cleanup and validation