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

    // Support both GET and POST methods
    let payId;
    if (event.httpMethod === 'GET') {
      payId = event.queryStringParameters?.payId;
    } else if (event.httpMethod === 'POST') {
      const requestBody = JSON.parse(event.body || '{}');
      payId = requestBody.payId;
    } else {
      return {
        statusCode: 405,
        headers: corsHeaders,
        body: JSON.stringify({ error: 'Method not allowed' })
      };
    }

    if (!payId) {
      return {
        statusCode: 400,
        headers: corsHeaders,
        body: JSON.stringify({ 
          error: 'Missing payment ID',
          message: 'payId parameter is required'
        })
      };
    }

    console.log(`Checking payment status for payId: ${payId}`);
    const statusResult = await csobApi.getPaymentStatus(payId);

    if (statusResult.success) {
      // Update order status in Airtable
      const newStatus = statusResult.status;
      console.log(`Updating order status to: ${newStatus}`);
      
      try {
        await airtable.updateOrderStatus(payId, newStatus);
      } catch (airtableError) {
        console.error('Failed to update order status in Airtable:', airtableError);
        // Don't fail the entire request if Airtable update fails
      }

      // If payment is confirmed, update inventory
      if (statusResult.status === 'confirmed' || statusResult.status === 'settled') {
        try {
          const merchantData = statusResult.merchantData;
          if (merchantData && merchantData.cartItems) {
            console.log('Updating inventory after successful payment...');
            const inventoryUpdate = await airtable.updateInventoryAfterPayment(merchantData.cartItems);
            console.log('Inventory update result:', inventoryUpdate);
          }
        } catch (inventoryError) {
          console.error('Failed to update inventory:', inventoryError);
          // Log the error but don't fail the status check
        }
      }

      return {
        statusCode: 200,
        headers: corsHeaders,
        body: JSON.stringify({
          success: true,
          payId: statusResult.payId,
          status: statusResult.status,
          rawStatus: statusResult.rawStatus,
          resultCode: statusResult.resultCode,
          resultMessage: statusResult.resultMessage,
          isComplete: ['confirmed', 'settled', 'cancelled', 'declined'].includes(statusResult.status),
          isSuccessful: ['confirmed', 'settled'].includes(statusResult.status)
        })
      };
    } else {
      throw new Error('Failed to get payment status');
    }

  } catch (error) {
    console.error('Payment status check error:', error);
    
    return {
      statusCode: 500,
      headers: corsHeaders,
      body: JSON.stringify({
        error: 'Payment status check failed',
        message: error.message || 'Internal server error'
      })
    };
  }
};