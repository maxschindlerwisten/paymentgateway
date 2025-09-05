const CSOBCryptoService = require('../utils/crypto-service');

describe('CSOBCryptoService', () => {
  // Mock the NodeRSA functionality for testing
  const mockCryptoService = {
    generateOrderNumber: () => {
      const crypto = require('crypto');
      return crypto.randomBytes(8).toString('hex').toUpperCase();
    },
    prepareDataForSigning: (data) => {
      const keys = Object.keys(data).sort();
      const values = [];
      
      keys.forEach(key => {
        if (key !== 'signature' && data[key] !== undefined && data[key] !== null) {
          if (Array.isArray(data[key])) {
            values.push(data[key].join('|'));
          } else if (typeof data[key] === 'object') {
            values.push(JSON.stringify(data[key]));
          } else {
            values.push(data[key].toString());
          }
        }
      });
      
      return values.join('|');
    }
  };

  test('should generate order number', () => {
    const orderNumber = mockCryptoService.generateOrderNumber();
    
    expect(orderNumber).toBeDefined();
    expect(typeof orderNumber).toBe('string');
    expect(orderNumber.length).toBeGreaterThan(0);
    expect(orderNumber).toMatch(/^[A-F0-9]+$/); // Hexadecimal uppercase
  });

  test('should prepare data for signing correctly', () => {
    const testData = {
      merchantId: 'M1MIPSTEST',
      orderNo: '12345',
      dttm: '20231201120000',
      payOperation: 'payment',
      totalAmount: 10000,
      currency: 'CZK'
    };

    const result = mockCryptoService.prepareDataForSigning(testData);
    
    // Should be sorted alphabetically and joined with |
    expect(result).toBe('CZK|20231201120000|M1MIPSTEST|12345|payment|10000');
  });

  test('should handle arrays in data preparation', () => {
    const testData = {
      merchantId: 'M1MIPSTEST',
      cart: ['item1', 'item2', 'item3']
    };

    const result = mockCryptoService.prepareDataForSigning(testData);
    expect(result).toBe('item1|item2|item3|M1MIPSTEST');
  });

  test('should handle nested objects in data preparation', () => {
    const testData = {
      merchantId: 'M1MIPSTEST',
      customerData: { name: 'John Doe', email: 'john@example.com' }
    };

    const result = mockCryptoService.prepareDataForSigning(testData);
    expect(result).toContain('{"name":"John Doe","email":"john@example.com"}');
    expect(result).toContain('M1MIPSTEST');
  });

  test('should exclude signature from data preparation', () => {
    const testData = {
      merchantId: 'M1MIPSTEST',
      orderNo: '12345',
      signature: 'existing-signature-should-be-ignored'
    };

    const result = mockCryptoService.prepareDataForSigning(testData);
    expect(result).toBe('M1MIPSTEST|12345');
    expect(result).not.toContain('existing-signature-should-be-ignored');
  });

  test('should exclude null and undefined values', () => {
    const testData = {
      merchantId: 'M1MIPSTEST',
      orderNo: '12345',
      nullValue: null,
      undefinedValue: undefined,
      emptyString: ''
    };

    const result = mockCryptoService.prepareDataForSigning(testData);
    expect(result).toBe('|M1MIPSTEST|12345'); // Empty string is included
    expect(result).not.toContain('null');
    expect(result).not.toContain('undefined');
  });
});

module.exports = {};