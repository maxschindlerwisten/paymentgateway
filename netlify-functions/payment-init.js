const CSOBApiService = require('../utils/csob-api-service');
const AirtableService = require('../utils/airtable-service');

// Environment variables validation
const requiredEnvVars = [
  'CSOB_MERCHANT_ID',
  'CSOB_PRIVATE_KEY',
  'CSOB_PUBLIC_KEY',
  'AIRTABLE_API_KEY',
  'AIRTABLE_BASE_ID'
];

function validateEnvironment() {
  const missing = requiredEnvVars.filter(envVar => !process.env[envVar]);
  if (missing.length > 0) {
    throw new Error(`Missing required environment variables: ${missing.join(', ')}`);
  }
}

// Initialize services
function initializeServices() {
  validateEnvironment();
  
  const csobConfig = {
    apiUrl: process.env.CSOB_API_URL || 'https://iapi.iplatebnibrana.csob.cz/api/v1.9',
    merchantId: process.env.CSOB_MERCHANT_ID,
    privateKey: process.env.CSOB_PRIVATE_KEY,
    privateKeyPassword: process.env.CSOB_PRIVATE_KEY_PASSWORD || null,
    csobPublicKey: process.env.CSOB_PUBLIC_KEY
  };

  const airtableConfig = {
    apiKey: process.env.AIRTABLE_API_KEY,
    baseId: process.env.AIRTABLE_BASE_ID,
    inventoryTable: process.env.AIRTABLE_INVENTORY_TABLE || 'Inventory',
    ordersTable: process.env.AIRTABLE_ORDERS_TABLE || 'Orders'
  };

  return {
    csobApi: new CSOBApiService(csobConfig),
    airtable: new AirtableService(airtableConfig)
  };
}

// CORS headers
const corsHeaders = {
  'Access-Control-Allow-Origin': '*',
  'Access-Control-Allow-Headers': 'Content-Type, Authorization',
  'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS'
};

exports.handler = async (event, context) => {
  // Handle CORS preflight requests
  if (event.httpMethod === 'OPTIONS') {
    return {
      statusCode: 200,
      headers: corsHeaders,
      body: ''
    };
  }

  try {
    const { csobApi, airtable } = initializeServices();

    if (event.httpMethod !== 'POST') {
      return {
        statusCode: 405,
        headers: corsHeaders,
        body: JSON.stringify({ error: 'Method not allowed' })
      };
    }

    const requestBody = JSON.parse(event.body);
    const { cartData, customerData } = requestBody;

    // Validate request data
    if (!cartData || !customerData) {
      return {
        statusCode: 400,
        headers: corsHeaders,
        body: JSON.stringify({ 
          error: 'Missing required data',
          message: 'cartData and customerData are required'
        })
      };
    }

    // Validate required cart data
    if (!cartData.items || !Array.isArray(cartData.items) || cartData.items.length === 0) {
      return {
        statusCode: 400,
        headers: corsHeaders,
        body: JSON.stringify({ 
          error: 'Invalid cart data',
          message: 'Cart must contain at least one item'
        })
      };
    }

    // Validate required customer data
    if (!customerData.email || !customerData.name) {
      return {
        statusCode: 400,
        headers: corsHeaders,
        body: JSON.stringify({ 
          error: 'Invalid customer data',
          message: 'Customer email and name are required'
        })
      };
    }

    // Check product availability in Airtable
    console.log('Checking product availability...');
    const availabilityCheck = await airtable.checkProductAvailability(cartData.items);
    
    if (!availabilityCheck.allAvailable) {
      return {
        statusCode: 400,
        headers: corsHeaders,
        body: JSON.stringify({
          error: 'Products not available',
          message: 'Some products are out of stock',
          unavailableItems: availabilityCheck.unavailableItems
        })
      };
    }

    // Add return URL to cart data if not provided
    if (!cartData.returnUrl) {
      cartData.returnUrl = `${process.env.SITE_URL}/payment-return`;
    }

    console.log('Initializing payment with CSOB...');
    const paymentResult = await csobApi.initializePayment(cartData, customerData);

    if (paymentResult.success) {
      // Create order record in Airtable
      console.log('Creating order record...');
      const orderData = {
        orderNumber: paymentResult.orderNumber,
        payId: paymentResult.payId,
        customerEmail: customerData.email,
        customerName: customerData.name,
        totalAmount: paymentResult.totalAmount,
        currency: paymentResult.currency,
        status: 'initiated',
        items: cartData.items
      };

      await airtable.createOrderRecord(orderData);

      return {
        statusCode: 200,
        headers: corsHeaders,
        body: JSON.stringify({
          success: true,
          paymentUrl: paymentResult.paymentUrl,
          payId: paymentResult.payId,
          orderNumber: paymentResult.orderNumber,
          totalAmount: paymentResult.totalAmount,
          currency: paymentResult.currency,
          message: 'Payment initialized successfully'
        })
      };
    } else {
      throw new Error('Payment initialization failed');
    }

  } catch (error) {
    console.error('Payment initialization error:', error);
    
    return {
      statusCode: 500,
      headers: corsHeaders,
      body: JSON.stringify({
        error: 'Payment initialization failed',
        message: error.message || 'Internal server error'
      })
    };
  }
};