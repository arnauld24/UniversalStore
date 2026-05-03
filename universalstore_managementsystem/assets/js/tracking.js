// User Tracking JavaScript
// Include this in pages that need client-side tracking

class UserTrackerJS {
    constructor() {
        this.apiUrl = BASE_URL + '/universalstore_managementsystem/api/track.php';
        this.pageLoadTime = Date.now();
        this.scrollDepth = 0;
        this.maxScrollDepth = 0;
        this.init();
    }

    init() {
        // Track page view
        this.trackEvent('page_view', {
            page_title: document.title,
            page_url: window.location.href,
            referrer: document.referrer
        });

        // Track scroll depth
        this.trackScroll();

        // Track time on page
        this.trackTimeOnPage();

        // Track engagement events
        this.trackEngagement();

        // Track before unload
        window.addEventListener('beforeunload', () => {
            this.trackEvent('page_unload', {
                time_on_page: Date.now() - this.pageLoadTime,
                max_scroll_depth: this.maxScrollDepth
            });
        });
    }
    
    trackEvent(eventType, eventData = {}, category = null) {
        const data = {
            event_type: eventType,
            event_data: eventData,
            category: category,
            timestamp: Date.now(),
            session_id: this.getCookie('tracking_session')
        };

        // Send via fetch API
        fetch(this.apiUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify(data),
            keepalive: true // Allow sending even if page is unloading
        }).catch(error => {
            console.warn('Tracking error:', error);
        });
    }

    trackScroll() {
        let ticking = false;

        window.addEventListener('scroll', () => {
            if (!ticking) {
                requestAnimationFrame(() => {
                    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
                    const windowHeight = window.innerHeight;
                    const documentHeight = Math.max(
                        document.body.scrollHeight,
                        document.body.offsetHeight,
                        document.documentElement.clientHeight,
                        document.documentElement.scrollHeight,
                        document.documentElement.offsetHeight
                    );

                    this.scrollDepth = Math.round((scrollTop + windowHeight) / documentHeight * 100);
                    this.maxScrollDepth = Math.max(this.maxScrollDepth, this.scrollDepth);

                    // Track scroll milestones
                    if (this.scrollDepth >= 25 && this.scrollDepth < 50 && this.maxScrollDepth === 25) {
                        this.trackEvent('scroll_25');
                    } else if (this.scrollDepth >= 50 && this.scrollDepth < 75 && this.maxScrollDepth === 50) {
                        this.trackEvent('scroll_50');
                    } else if (this.scrollDepth >= 75 && this.scrollDepth < 100 && this.maxScrollDepth === 75) {
                        this.trackEvent('scroll_75');
                    } else if (this.scrollDepth >= 100 && this.maxScrollDepth === 100) {
                        this.trackEvent('scroll_100');
                    }

                    ticking = false;
                });
                ticking = true;
            }
        });
    }

    trackTimeOnPage() {
        // Track time milestones
        setTimeout(() => this.trackEvent('time_30s'), 30000);
        setTimeout(() => this.trackEvent('time_1m'), 60000);
        setTimeout(() => this.trackEvent('time_2m'), 120000);
        setTimeout(() => this.trackEvent('time_5m'), 300000);
    }

    trackEngagement() {
        // Track clicks on important elements
        document.addEventListener('click', (e) => {
            const target = e.target.closest('a, button, [data-track]');
            if (target) {
                const eventData = {
                    element_type: target.tagName.toLowerCase(),
                    element_text: target.textContent?.trim().substring(0, 100),
                    element_href: target.href,
                    element_class: target.className
                };

                if (target.hasAttribute('data-track')) {
                    this.trackEvent(target.getAttribute('data-track'), eventData);
                } else if (target.tagName === 'A' && target.href && !target.href.startsWith(window.location.origin)) {
                    this.trackEvent('outbound_click', eventData, 'navigation');
                }
            }
        });

        // Track form interactions
        document.addEventListener('submit', (e) => {
            const form = e.target;
            const eventData = {
                form_action: form.action,
                form_method: form.method,
                form_id: form.id,
                form_class: form.className
            };

            this.trackEvent('form_submit', eventData);
        });

        // Track search interactions
        const searchInputs = document.querySelectorAll('input[type="search"], input[name*="search"], input[placeholder*="search"]');
        searchInputs.forEach(input => {
            let searchTimeout;
            input.addEventListener('input', () => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    if (input.value.length > 2) {
                        this.trackEvent('search_input', {
                            search_term: input.value,
                            input_name: input.name,
                            input_placeholder: input.placeholder
                        });
                    }
                }, 1000);
            });
        });
    }

    // Product-specific tracking methods
    trackProductView(productId, productName, category, price) {
        this.trackEvent('product_view', {
            product_id: productId,
            product_name: productName,
            category: category,
            price: price
        }, 'ai_insight');
    }

    trackProductClick(productId, productName, position = null) {
        this.trackEvent('product_click', {
            product_id: productId,
            product_name: productName,
            position: position
        }, 'ai_insight');
    }

    trackAddToCart(productId, productName, quantity, price) {
        this.trackEvent('add_to_cart', {
            product_id: productId,
            product_name: productName,
            quantity: quantity,
            price: price
        }, 'conversion');
    }

    trackWishlistAction(productId, productName, action) {
        this.trackEvent('wishlist_' + action, {
            product_id: productId,
            product_name: productName
        }, 'ai_insight');
    }

    // Cart and checkout tracking
    trackCartView(cartItems, totalValue) {
        this.trackEvent('cart_view', {
            items_count: cartItems.length,
            total_value: totalValue,
            items: cartItems.map(item => ({
                product_id: item.id,
                product_name: item.name,
                quantity: item.quantity,
                price: item.price
            }))
        }, 'conversion');
    }

    trackCheckoutStep(step, cartData) {
        this.trackEvent('checkout_' + step, cartData, 'conversion');
    }

    // Utility methods
    getCookie(name) {
        const value = `; ${document.cookie}`;
        const parts = value.split(`; ${name}=`);
        if (parts.length === 2) return parts.pop().split(';').shift();
        return null;
    }

    setCookie(name, value, days = 30) {
        const expires = new Date();
        expires.setTime(expires.getTime() + days * 24 * 60 * 60 * 1000);
        document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/;SameSite=Strict`;
    }
}

// Initialize tracking when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.userTracker = new UserTrackerJS();
    });
} else {
    window.userTracker = new UserTrackerJS();
}

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = UserTrackerJS;
}