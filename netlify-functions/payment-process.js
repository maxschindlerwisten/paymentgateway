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
    const { payId } = requestBody;

    if (!payId) {
      return {
        statusCode: 400,
        headers: corsHeaders,
        body: JSON.stringify({ 
          error: 'Missing payment ID',
          message: 'payId is required'
        })
      };
    }

    console.log(`Processing payment for payId: ${payId}`);
    const processResult = await csobApi.processPayment(payId);

    if (processResult.success) {
      // Update order status in Airtable
      console.log(`Updating order status to: ${processResult.status}`);
      
      try {
        await airtable.updateOrderStatus(payId, processResult.status);
      } catch (airtableError) {
        console.error('Failed to update order status in Airtable:', airtableError);
        // Don't fail the entire request if Airtable update fails
      }

      return {
        statusCode: 200,
        headers: corsHeaders,
        body: JSON.stringify({
          success: true,
          payId: processResult.payId,
          status: processResult.status,
          resultCode: processResult.resultCode,
          resultMessage: processResult.resultMessage,
          message: 'Payment processed successfully'
        })
      };
    } else {
      return {
        statusCode: 400,
        headers: corsHeaders,
        body: JSON.stringify({
          success: false,
          payId: processResult.payId,
          status: processResult.status,
          resultCode: processResult.resultCode,
          resultMessage: processResult.resultMessage,
          error: 'Payment processing failed'
        })
      };
    }

  } catch (error) {
    console.error('Payment processing error:', error);
    
    return {
      statusCode: 500,
      headers: corsHeaders,
      body: JSON.stringify({
        error: 'Payment processing failed',
        message: error.message || 'Internal server error'
      })
    };
  }
};