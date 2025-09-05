/**
 * Go-Fresh E-commerce CSOB Payment Integration for Framer
 * This script handles cart collection, payment initialization, and return flow
 */

class GoFreshPaymentIntegration {
  constructor(config) {
    this.apiBaseUrl = config.apiBaseUrl; // Your Netlify functions URL
    this.siteUrl = config.siteUrl; // Your Framer site URL
    this.cart = [];
    this.isProcessing = false;
    
    // Initialize the integration
    this.init();
  }

  init() {
    console.log('Initializing Go-Fresh Payment Integration...');
    
    // Set up event listeners
    this.setupEventListeners();
    
    // Handle return from payment if URL contains payment parameters
    this.handlePaymentReturn();
    
    // Load cart from localStorage if available
    this.loadCart();
  }

  setupEventListeners() {
    // Listen for add to cart events
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('add-to-cart-btn')) {
        this.handleAddToCart(e);
      }
    });

    // Listen for remove from cart events
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('remove-from-cart-btn')) {
        this.handleRemoveFromCart(e);
      }
    });

    // Listen for checkout button clicks
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('checkout-btn')) {
        this.handleCheckout(e);
      }
    });

    // Listen for quantity changes
    document.addEventListener('change', (e) => {
      if (e.target.classList.contains('cart-quantity-input')) {
        this.handleQuantityChange(e);
      }
    });
  }

  /**
   * Add product to cart
   * Expected data attributes on button:
   * - data-product-id: Airtable record ID
   * - data-product-name: Product name
   * - data-product-price: Product price
   * - data-product-description: Product description (optional)
   */
  handleAddToCart(event) {
    const button = event.target;
    const productData = {
      id: button.getAttribute('data-product-id'),
      name: button.getAttribute('data-product-name'),
      price: parseFloat(button.getAttribute('data-product-price')),
      description: button.getAttribute('data-product-description') || '',
      quantity: 1
    };

    if (!productData.id || !productData.name || !productData.price) {
      console.error('Missing required product data attributes');
      this.showMessage('Error adding product to cart', 'error');
      return;
    }

    this.addToCart(productData);
    this.showMessage(`${productData.name} added to cart!`, 'success');
    
    // Disable button temporarily to prevent double-clicks
    button.disabled = true;
    setTimeout(() => {
      button.disabled = false;
    }, 1000);
  }

  addToCart(product) {
    const existingItem = this.cart.find(item => item.id === product.id);
    
    if (existingItem) {
      existingItem.quantity += product.quantity;
    } else {
      this.cart.push({ ...product });
    }
    
    this.saveCart();
    this.updateCartDisplay();
  }

  handleRemoveFromCart(event) {
    const productId = event.target.getAttribute('data-product-id');
    this.removeFromCart(productId);
    this.showMessage('Item removed from cart', 'success');
  }

  removeFromCart(productId) {
    this.cart = this.cart.filter(item => item.id !== productId);
    this.saveCart();
    this.updateCartDisplay();
  }

  handleQuantityChange(event) {
    const productId = event.target.getAttribute('data-product-id');
    const newQuantity = parseInt(event.target.value);
    
    if (newQuantity <= 0) {
      this.removeFromCart(productId);
    } else {
      const item = this.cart.find(item => item.id === productId);
      if (item) {
        item.quantity = newQuantity;
        this.saveCart();
        this.updateCartDisplay();
      }
    }
  }

  /**
   * Handle checkout process
   * Collect customer information and initiate payment
   */
  async handleCheckout(event) {
    if (this.isProcessing) {
      return;
    }

    if (this.cart.length === 0) {
      this.showMessage('Your cart is empty', 'error');
      return;
    }

    // Get customer information from form
    const customerData = this.collectCustomerData();
    if (!customerData) {
      return;
    }

    this.isProcessing = true;
    this.showMessage('Initializing payment...', 'info');

    try {
      const paymentData = await this.initializePayment(customerData);
      
      if (paymentData.success) {
        // Store payment info for return handling
        this.storePaymentInfo(paymentData);
        
        // Redirect to CSOB payment page
        window.location.href = paymentData.paymentUrl;
      } else {
        throw new Error(paymentData.message || 'Payment initialization failed');
      }
    } catch (error) {
      console.error('Checkout error:', error);
      this.showMessage(`Checkout failed: ${error.message}`, 'error');
      this.isProcessing = false;
    }
  }

  collectCustomerData() {
    // Try to get customer data from form with class 'customer-form'
    const form = document.querySelector('.customer-form');
    if (!form) {
      this.showMessage('Customer form not found. Please add a form with class "customer-form"', 'error');
      return null;
    }

    const email = form.querySelector('input[name="email"]')?.value;
    const name = form.querySelector('input[name="name"]')?.value;
    const phone = form.querySelector('input[name="phone"]')?.value;

    if (!email || !name) {
      this.showMessage('Please fill in all required fields (name and email)', 'error');
      return null;
    }

    // Validate email format
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    if (!emailRegex.test(email)) {
      this.showMessage('Please enter a valid email address', 'error');
      return null;
    }

    return {
      email: email,
      name: name,
      phone: phone || '',
      customerId: this.generateCustomerId(email)
    };
  }

  generateCustomerId(email) {
    // Simple hash function for consistent customer ID
    let hash = 0;
    for (let i = 0; i < email.length; i++) {
      const char = email.charCodeAt(i);
      hash = ((hash << 5) - hash) + char;
      hash = hash & hash; // Convert to 32-bit integer
    }
    return Math.abs(hash).toString();
  }

  async initializePayment(customerData) {
    const cartData = {
      items: this.cart.map(item => ({
        productId: item.id,
        name: item.name,
        price: item.price,
        quantity: item.quantity,
        description: item.description
      })),
      total: this.getCartTotal(),
      currency: 'CZK', // Change to your currency
      language: 'CZ', // Change to your language
      returnUrl: `${this.siteUrl}/payment-return`
    };

    const response = await fetch(`${this.apiBaseUrl}/payment-init`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        cartData: cartData,
        customerData: customerData
      })
    });

    if (!response.ok) {
      const errorData = await response.json();
      throw new Error(errorData.message || `HTTP ${response.status}`);
    }

    return await response.json();
  }

  /**
   * Handle return from CSOB payment page
   */
  async handlePaymentReturn() {
    const urlParams = new URLSearchParams(window.location.search);
    const payId = urlParams.get('payId');
    
    if (payId) {
      console.log('Handling payment return for payId:', payId);
      this.showMessage('Checking payment status...', 'info');
      
      try {
        const statusData = await this.checkPaymentStatus(payId);
        
        if (statusData.isSuccessful) {
          this.handleSuccessfulPayment(statusData);
        } else if (statusData.isComplete) {
          this.handleFailedPayment(statusData);
        } else {
          // Payment still in progress, keep checking
          this.pollPaymentStatus(payId);
        }
      } catch (error) {
        console.error('Error checking payment status:', error);
        this.showMessage('Error checking payment status', 'error');
      }
    }
  }

  async checkPaymentStatus(payId) {
    const response = await fetch(`${this.apiBaseUrl}/payment-status?payId=${payId}`);
    
    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }
    
    return await response.json();
  }

  async pollPaymentStatus(payId, maxAttempts = 30) {
    for (let attempt = 0; attempt < maxAttempts; attempt++) {
      await new Promise(resolve => setTimeout(resolve, 2000)); // Wait 2 seconds
      
      try {
        const statusData = await this.checkPaymentStatus(payId);
        
        if (statusData.isComplete) {
          if (statusData.isSuccessful) {
            this.handleSuccessfulPayment(statusData);
          } else {
            this.handleFailedPayment(statusData);
          }
          return;
        }
      } catch (error) {
        console.error('Error polling payment status:', error);
      }
    }
    
    // Timeout reached
    this.showMessage('Payment status check timed out. Please contact support.', 'warning');
  }

  handleSuccessfulPayment(statusData) {
    console.log('Payment successful:', statusData);
    
    // Clear cart
    this.cart = [];
    this.saveCart();
    this.updateCartDisplay();
    
    // Show success message
    this.showMessage('Payment successful! Thank you for your order.', 'success');
    
    // Redirect to success page or show success content
    this.showSuccessPage(statusData);
  }

  handleFailedPayment(statusData) {
    console.log('Payment failed:', statusData);
    
    // Show error message
    this.showMessage(`Payment failed: ${statusData.resultMessage || 'Unknown error'}`, 'error');
    
    // Redirect to cart or show retry option
    this.showErrorPage(statusData);
  }

  showSuccessPage(statusData) {
    // Hide other content and show success message
    const successHtml = `
      <div class="payment-success">
        <h2>✅ Payment Successful!</h2>
        <p>Your order has been confirmed.</p>
        <p><strong>Payment ID:</strong> ${statusData.payId}</p>
        <p><strong>Status:</strong> ${statusData.status}</p>
        <button onclick="window.location.href='/'">Continue Shopping</button>
      </div>
    `;
    
    this.showPaymentResult(successHtml);
  }

  showErrorPage(statusData) {
    const errorHtml = `
      <div class="payment-error">
        <h2>❌ Payment Failed</h2>
        <p>Unfortunately, your payment could not be processed.</p>
        <p><strong>Status:</strong> ${statusData.status}</p>
        <p><strong>Message:</strong> ${statusData.resultMessage || 'Unknown error'}</p>
        <button onclick="window.location.href='/cart'">Return to Cart</button>
        <button onclick="window.location.href='/'">Continue Shopping</button>
      </div>
    `;
    
    this.showPaymentResult(errorHtml);
  }

  showPaymentResult(html) {
    // Find or create payment result container
    let container = document.querySelector('.payment-result-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'payment-result-container';
      document.body.appendChild(container);
    }
    
    container.innerHTML = html;
    container.style.display = 'block';
    
    // Hide other page content
    const mainContent = document.querySelector('main, .main-content, body > div:first-child');
    if (mainContent && mainContent !== container) {
      mainContent.style.display = 'none';
    }
  }

  // Cart management methods
  saveCart() {
    localStorage.setItem('goFreshCart', JSON.stringify(this.cart));
  }

  loadCart() {
    const savedCart = localStorage.getItem('goFreshCart');
    if (savedCart) {
      this.cart = JSON.parse(savedCart);
      this.updateCartDisplay();
    }
  }

  storePaymentInfo(paymentData) {
    localStorage.setItem('goFreshPaymentInfo', JSON.stringify(paymentData));
  }

  getCartTotal() {
    return this.cart.reduce((total, item) => total + (item.price * item.quantity), 0);
  }

  getCartItemCount() {
    return this.cart.reduce((total, item) => total + item.quantity, 0);
  }

  updateCartDisplay() {
    // Update cart counter
    const cartCounter = document.querySelector('.cart-counter');
    if (cartCounter) {
      cartCounter.textContent = this.getCartItemCount();
    }

    // Update cart total
    const cartTotal = document.querySelector('.cart-total');
    if (cartTotal) {
      cartTotal.textContent = `${this.getCartTotal().toFixed(2)} CZK`;
    }

    // Update cart items list
    this.updateCartItemsList();
  }

  updateCartItemsList() {
    const cartList = document.querySelector('.cart-items-list');
    if (!cartList) return;

    if (this.cart.length === 0) {
      cartList.innerHTML = '<p class="empty-cart">Your cart is empty</p>';
      return;
    }

    const cartHtml = this.cart.map(item => `
      <div class="cart-item" data-product-id="${item.id}">
        <div class="item-info">
          <h4>${item.name}</h4>
          <p class="item-description">${item.description}</p>
          <p class="item-price">${item.price.toFixed(2)} CZK</p>
        </div>
        <div class="item-controls">
          <input type="number" 
                 class="cart-quantity-input" 
                 data-product-id="${item.id}"
                 value="${item.quantity}" 
                 min="1" 
                 max="99">
          <button class="remove-from-cart-btn" 
                  data-product-id="${item.id}">
            Remove
          </button>
        </div>
        <div class="item-total">
          ${(item.price * item.quantity).toFixed(2)} CZK
        </div>
      </div>
    `).join('');

    cartList.innerHTML = cartHtml;
  }

  // Utility methods
  showMessage(message, type = 'info') {
    // Create or update message container
    let messageContainer = document.querySelector('.payment-message');
    if (!messageContainer) {
      messageContainer = document.createElement('div');
      messageContainer.className = 'payment-message';
      document.body.appendChild(messageContainer);
    }

    messageContainer.className = `payment-message ${type}`;
    messageContainer.textContent = message;
    messageContainer.style.display = 'block';

    // Auto-hide success and info messages
    if (type === 'success' || type === 'info') {
      setTimeout(() => {
        messageContainer.style.display = 'none';
      }, 5000);
    }
  }
}

// Initialize the payment integration when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
  // Configuration - Update these values for your setup
  const config = {
    apiBaseUrl: 'https://YOUR_NETLIFY_SITE.netlify.app/.netlify/functions',
    siteUrl: 'https://YOUR_FRAMER_SITE.framer.website'
  };

  // Initialize the payment integration
  window.goFreshPayment = new GoFreshPaymentIntegration(config);
  
  console.log('Go-Fresh Payment Integration loaded successfully!');
});

// Export for module use if needed
if (typeof module !== 'undefined' && module.exports) {
  module.exports = GoFreshPaymentIntegration;
}