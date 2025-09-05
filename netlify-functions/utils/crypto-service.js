const NodeRSA = require('node-rsa');
const crypto = require('crypto');

class CSOBCryptoService {
  constructor(privateKeyPem, privateKeyPassword, csobPublicKeyPem) {
    this.privateKey = new NodeRSA(privateKeyPem);
    this.csobPublicKey = new NodeRSA(csobPublicKeyPem, 'public');
    
    if (privateKeyPassword) {
      this.privateKey.importKey(privateKeyPem, 'private', privateKeyPassword);
    }
  }

  /**
   * Create signature for CSOB API request
   * @param {Object} payload - Request payload object
   * @returns {string} Base64 encoded signature
   */
  createSignature(payload) {
    // Convert payload to string for signing
    const dataToSign = this.prepareDataForSigning(payload);
    
    // Create signature using SHA-256 with RSA
    const signature = this.privateKey.sign(dataToSign, 'base64', 'utf8');
    
    // Add signature to payload
    payload.signature = signature;
    
    return signature;
  }

  /**
   * Verify signature from CSOB API response
   * @param {Object} response - Response object from CSOB
   * @returns {boolean} True if signature is valid
   */
  verifySignature(response) {
    if (!response.signature) {
      return false;
    }

    const signature = response.signature;
    // Remove signature from response for verification
    const responseForVerification = { ...response };
    delete responseForVerification.signature;

    const dataToVerify = this.prepareDataForSigning(responseForVerification);
    
    try {
      return this.csobPublicKey.verify(dataToVerify, signature, 'utf8', 'base64');
    } catch (error) {
      console.error('Signature verification failed:', error);
      return false;
    }
  }

  /**
   * Prepare data for signing according to CSOB specification
   * @param {Object} data - Data object to sign
   * @returns {string} Concatenated string for signing
   */
  prepareDataForSigning(data) {
    const keys = Object.keys(data).sort();
    const values = [];
    
    keys.forEach(key => {
      if (key !== 'signature' && data[key] !== undefined && data[key] !== null) {
        if (Array.isArray(data[key])) {
          // Handle arrays by joining with |
          values.push(data[key].join('|'));
        } else if (typeof data[key] === 'object') {
          // Handle nested objects
          values.push(JSON.stringify(data[key]));
        } else {
          values.push(data[key].toString());
        }
      }
    });
    
    return values.join('|');
  }

  /**
   * Generate random order number
   * @returns {string} Random order number
   */
  generateOrderNumber() {
    return crypto.randomBytes(8).toString('hex').toUpperCase();
  }
}

module.exports = CSOBCryptoService;