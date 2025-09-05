const Airtable = require('airtable');

class AirtableService {
  constructor(config) {
    this.base = new Airtable({
      apiKey: config.apiKey
    }).base(config.baseId);
    this.inventoryTable = config.inventoryTable || 'Inventory';
    this.ordersTable = config.ordersTable || 'Orders';
  }

  /**
   * Update inventory after successful payment
   * @param {Array} cartItems - Items that were purchased
   * @returns {Promise<Object>} Update results
   */
  async updateInventoryAfterPayment(cartItems) {
    try {
      const updatePromises = cartItems.map(item => this.updateProductInventory(item));
      const results = await Promise.allSettled(updatePromises);
      
      return {
        success: true,
        updates: results.map((result, index) => ({
          productId: cartItems[index].productId || cartItems[index].id,
          success: result.status === 'fulfilled',
          error: result.status === 'rejected' ? result.reason.message : null
        }))
      };
    } catch (error) {
      console.error('Inventory update failed:', error);
      throw error;
    }
  }

  /**
   * Update individual product inventory
   * @param {Object} item - Cart item with product info
   * @returns {Promise<Object>} Update result
   */
  async updateProductInventory(item) {
    const productId = item.productId || item.id;
    const quantityPurchased = item.quantity;

    try {
      // First, get current inventory
      const record = await this.base(this.inventoryTable).find(productId);
      const currentStock = record.get('Stock') || 0;
      const newStock = Math.max(0, currentStock - quantityPurchased);

      // Update the record
      const updatedRecord = await this.base(this.inventoryTable).update(productId, {
        'Stock': newStock,
        'Last Updated': new Date().toISOString(),
        'Last Order Quantity': quantityPurchased
      });

      return {
        productId: productId,
        previousStock: currentStock,
        newStock: newStock,
        quantityPurchased: quantityPurchased,
        recordId: updatedRecord.id
      };
    } catch (error) {
      console.error(`Failed to update inventory for product ${productId}:`, error);
      throw error;
    }
  }

  /**
   * Create order record in Airtable
   * @param {Object} orderData - Order information
   * @returns {Promise<Object>} Created order record
   */
  async createOrderRecord(orderData) {
    try {
      const orderRecord = {
        'Order Number': orderData.orderNumber,
        'Payment ID': orderData.payId,
        'Customer Email': orderData.customerEmail,
        'Customer Name': orderData.customerName,
        'Total Amount': orderData.totalAmount / 100, // Convert from cents
        'Currency': orderData.currency,
        'Status': orderData.status,
        'Order Date': new Date().toISOString(),
        'Items': JSON.stringify(orderData.items),
        'Payment Method': 'CSOB Gateway'
      };

      const createdRecord = await this.base(this.ordersTable).create(orderRecord);
      
      return {
        success: true,
        orderId: createdRecord.id,
        orderNumber: orderData.orderNumber
      };
    } catch (error) {
      console.error('Failed to create order record:', error);
      throw error;
    }
  }

  /**
   * Update order status
   * @param {string} payId - Payment ID to find order
   * @param {string} status - New status
   * @returns {Promise<Object>} Update result
   */
  async updateOrderStatus(payId, status) {
    try {
      // Find order by Payment ID
      const records = await this.base(this.ordersTable).select({
        filterByFormula: `{Payment ID} = '${payId}'`,
        maxRecords: 1
      }).firstPage();

      if (records.length === 0) {
        throw new Error(`Order not found for Payment ID: ${payId}`);
      }

      const record = records[0];
      const updatedRecord = await this.base(this.ordersTable).update(record.id, {
        'Status': status,
        'Status Updated': new Date().toISOString()
      });

      return {
        success: true,
        orderId: updatedRecord.id,
        newStatus: status
      };
    } catch (error) {
      console.error('Failed to update order status:', error);
      throw error;
    }
  }

  /**
   * Get product information from Airtable
   * @param {Array} productIds - Array of product IDs
   * @returns {Promise<Array>} Product information
   */
  async getProductsInfo(productIds) {
    try {
      const products = [];
      
      for (const productId of productIds) {
        try {
          const record = await this.base(this.inventoryTable).find(productId);
          products.push({
            id: record.id,
            name: record.get('Name'),
            price: record.get('Price'),
            stock: record.get('Stock'),
            description: record.get('Description'),
            sku: record.get('SKU')
          });
        } catch (error) {
          console.error(`Product ${productId} not found:`, error);
          products.push({
            id: productId,
            error: 'Product not found'
          });
        }
      }
      
      return products;
    } catch (error) {
      console.error('Failed to get products info:', error);
      throw error;
    }
  }

  /**
   * Check product availability
   * @param {Array} cartItems - Items to check
   * @returns {Promise<Object>} Availability check result
   */
  async checkProductAvailability(cartItems) {
    try {
      const availabilityChecks = [];
      
      for (const item of cartItems) {
        try {
          const record = await this.base(this.inventoryTable).find(item.productId || item.id);
          const currentStock = record.get('Stock') || 0;
          const isAvailable = currentStock >= item.quantity;
          
          availabilityChecks.push({
            productId: item.productId || item.id,
            requestedQuantity: item.quantity,
            availableStock: currentStock,
            isAvailable: isAvailable,
            name: record.get('Name')
          });
        } catch (error) {
          availabilityChecks.push({
            productId: item.productId || item.id,
            requestedQuantity: item.quantity,
            isAvailable: false,
            error: 'Product not found'
          });
        }
      }
      
      const allAvailable = availabilityChecks.every(check => check.isAvailable);
      
      return {
        allAvailable: allAvailable,
        checks: availabilityChecks,
        unavailableItems: availabilityChecks.filter(check => !check.isAvailable)
      };
    } catch (error) {
      console.error('Product availability check failed:', error);
      throw error;
    }
  }
}

module.exports = AirtableService;