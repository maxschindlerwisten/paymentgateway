const fetch = require('node-fetch');
const CSOBCryptoService = require('./crypto-service');

class CSOBApiService {
  constructor(config) {
    this.baseUrl = config.apiUrl;
    this.merchantId = config.merchantId;
    this.cryptoService = new CSOBCryptoService(
      config.privateKey,
      config.privateKeyPassword,
      config.csobPublicKey
    );
    this.timeout = config.timeout || 30000; // 30 seconds default
  }

  /**
   * Initialize payment with CSOB
   * @param {Object} cartData - Cart data from Framer site
   * @param {Object} customerData - Customer information
   * @returns {Object} Payment initialization response
   */
  async initializePayment(cartData, customerData) {
    const orderNumber = this.cryptoService.generateOrderNumber();
    
    // Calculate total amount in cents (CSOB expects amounts in cents)
    const totalAmount = Math.round(cartData.total * 100);
    
    const payload = {
      merchantId: this.merchantId,
      orderNo: orderNumber,
      dttm: this.getCurrentTimestamp(),
      payOperation: 'payment',
      payMethod: 'card',
      totalAmount: totalAmount,
      currency: cartData.currency || 'CZK',
      closePayment: true,
      returnUrl: cartData.returnUrl,
      merchantData: this.encodeMerchantData({
        customerEmail: customerData.email,
        customerName: customerData.name,
        cartItems: cartData.items,
        timestamp: Date.now()
      }),
      customerId: customerData.customerId || null,
      language: cartData.language || 'CZ',
      cart: this.formatCartForCSOB(cartData.items)
    };

    // Create signature
    this.cryptoService.createSignature(payload);

    try {
      const response = await this.makeRequest('/payment/init', payload);
      
      if (response.resultCode === 0) {
        return {
          success: true,
          payId: response.payId,
          paymentUrl: `${this.baseUrl.replace('/api/v1.9', '')}/payment/gateway/${response.payId}`,
          orderNumber: orderNumber,
          totalAmount: totalAmount,
          currency: payload.currency
        };
      } else {
        throw new Error(`Payment initialization failed: ${response.resultMessage}`);
      }
    } catch (error) {
      console.error('Payment initialization error:', error);
      throw error;
    }
  }

  /**
   * Get payment status from CSOB
   * @param {string} payId - Payment ID from initialization
   * @returns {Object} Payment status response
   */
  async getPaymentStatus(payId) {
    const payload = {
      merchantId: this.merchantId,
      payId: payId,
      dttm: this.getCurrentTimestamp()
    };

    this.cryptoService.createSignature(payload);

    try {
      const response = await this.makeRequest('/payment/status', payload);
      
      if (!this.cryptoService.verifySignature(response)) {
        throw new Error('Invalid signature in payment status response');
      }

      return {
        success: true,
        payId: response.payId,
        status: this.mapPaymentStatus(response.paymentStatus),
        rawStatus: response.paymentStatus,
        resultCode: response.resultCode,
        resultMessage: response.resultMessage,
        merchantData: response.merchantData ? this.decodeMerchantData(response.merchantData) : null
      };
    } catch (error) {
      console.error('Payment status check error:', error);
      throw error;
    }
  }

  /**
   * Process payment (for one-click payments or additional processing)
   * @param {string} payId - Payment ID
   * @returns {Object} Payment process response
   */
  async processPayment(payId) {
    const payload = {
      merchantId: this.merchantId,
      payId: payId,
      dttm: this.getCurrentTimestamp()
    };

    this.cryptoService.createSignature(payload);

    try {
      const response = await this.makeRequest('/payment/process', payload);
      
      if (!this.cryptoService.verifySignature(response)) {
        throw new Error('Invalid signature in payment process response');
      }

      return {
        success: response.resultCode === 0,
        payId: response.payId,
        status: this.mapPaymentStatus(response.paymentStatus),
        resultCode: response.resultCode,
        resultMessage: response.resultMessage
      };
    } catch (error) {
      console.error('Payment process error:', error);
      throw error;
    }
  }

  /**
   * Make HTTP request to CSOB API
   * @param {string} endpoint - API endpoint
   * @param {Object} payload - Request payload
   * @returns {Object} Response data
   */
  async makeRequest(endpoint, payload) {
    const url = `${this.baseUrl}${endpoint}`;
    
    const response = await fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json'
      },
      body: JSON.stringify(payload),
      timeout: this.timeout
    });

    if (!response.ok) {
      const errorText = await response.text();
      throw new Error(`HTTP ${response.status}: ${errorText}`);
    }

    return await response.json();
  }

  /**
   * Get current timestamp in CSOB format (YYYYMMDDhhmmss)
   * @returns {string} Formatted timestamp
   */
  getCurrentTimestamp() {
    const now = new Date();
    return now.getFullYear().toString() +
           (now.getMonth() + 1).toString().padStart(2, '0') +
           now.getDate().toString().padStart(2, '0') +
           now.getHours().toString().padStart(2, '0') +
           now.getMinutes().toString().padStart(2, '0') +
           now.getSeconds().toString().padStart(2, '0');
  }

  /**
   * Format cart items for CSOB API
   * @param {Array} items - Cart items
   * @returns {Array} Formatted cart for CSOB
   */
  formatCartForCSOB(items) {
    return items.map((item, index) => ({
      name: item.name,
      quantity: item.quantity,
      amount: Math.round(item.price * 100), // Convert to cents
      description: item.description || item.name
    }));
  }

  /**
   * Encode merchant data for storage
   * @param {Object} data - Data to encode
   * @returns {string} Base64 encoded data
   */
  encodeMerchantData(data) {
    return Buffer.from(JSON.stringify(data)).toString('base64');
  }

  /**
   * Decode merchant data from storage
   * @param {string} encodedData - Base64 encoded data
   * @returns {Object} Decoded data
   */
  decodeMerchantData(encodedData) {
    try {
      return JSON.parse(Buffer.from(encodedData, 'base64').toString());
    } catch (error) {
      console.error('Failed to decode merchant data:', error);
      return null;
    }
  }

  /**
   * Map CSOB payment status to readable format
   * @param {number} status - CSOB payment status code
   * @returns {string} Readable status
   */
  mapPaymentStatus(status) {
    const statusMap = {
      1: 'created',
      2: 'in_progress',
      4: 'confirmed',
      5: 'cancelled',
      6: 'declined',
      7: 'waiting_for_settlement',
      8: 'settled',
      9: 'refunded',
      10: 'partially_refunded'
    };
    return statusMap[status] || 'unknown';
  }
}

module.exports = CSOBApiService;