# Payment Security Implementation Guide

## Overview
This document outlines comprehensive security measures implemented for the payment system to prevent unauthorized access, payment page hacking, API redirection attacks, and other security threats.

---

## 1. Payment Page Access Token System

### Purpose
Prevent users from being redirected to fake payment pages or unauthorized payment endpoints.

### Implementation

#### Token Generation (payment.php)
```php
// Generated when user accesses payment page
- Combines user ID, order ID, session token, timestamp, IP address, and User-Agent hash
- Token expires in 30 minutes
- Stored in both session and HttpOnly secure cookie (SameSite: Strict)
```

#### Token Validation (process_payment.php)
1. **Timestamp Check**: Ensures token hasn't expired (30-minute window)
2. **User Verification**: Confirms token belongs to authenticated user
3. **Order Validation**: Verifies token is for the correct order
4. **Session Matching**: Ensures session hasn't changed
5. **Device Fingerprinting**: Validates User-Agent matches
6. **IP Address Logging**: Detects suspicious IP changes

### Attack Prevention
- **Redirect to Fake Payment Pages**: Token validation prevents redirects from unauthorized sources
- **Session Hijacking**: User-Agent and IP validation detect compromised sessions
- **Token Reuse**: Tokens expire after 30 minutes and are invalidated after successful payment
- **Cross-Origin Attacks**: Token is invalidated if session changes

---

## 2. Request Origin Verification

### Security Headers Added
```
X-Content-Type-Options: nosniff
X-Frame-Options: DENY
X-XSS-Protection: 1; mode=block
Strict-Transport-Security: max-age=31536000
Content-Security-Policy: Restricted to trusted sources
Referrer-Policy: strict-origin-when-cross-origin
```

### Request Validation
1. **Origin Check**: Verifies request came from payment page
2. **AJAX Validation**: Ensures request is AJAX (XMLHttpRequest)
3. **Source Verification**: Custom header validates request source is payment interface

### API Redirect Protection
- **Whitelist Validation**: Only allowed payment methods processed
- **URL Parameter Validation**: Payment ID format validated (alphanumeric only)
- **Referrer Check**: Suspicious referrers logged but not blocked (allows direct access)
- **Frame Detection**: Prevents payment page from loading in iframes (payment_status.php)

---

## 3. Rate Limiting Implementation

### Payment Page Access
- **Limit**: 20 attempts per hour per user
- **Time Window**: 1 hour
- **Reset**: Automatic after 1 hour of inactivity
- **Key**: User ID + IP Address

### Payment Processing
- **Limit**: 5 attempts per hour per order
- **Time Window**: 1 hour
- **Reset**: Automatic after 1 hour
- **Key**: User ID + Order ID + IP Address
- **HTTP Status**: 429 Too Many Requests

### Prevents
- Brute force attacks
- Automated payment bypass attempts
- Distributed attack patterns
- Multiple rapid transaction attempts

---

## 4. CSRF Protection

### Implementation
- CSRF token validation on all POST requests
- Token rotation after successful/failed operations
- Token validation before payment processing
- Session-based token storage

### Verification Flow
1. Token generated on payment page load
2. Token included in form submission
3. Server validates token matches session
4. Token rotated for next request
5. Old tokens invalidated

---

## 5. Payment Status Page Security

### User Ownership Verification
```php
// Critical check: Verify payment belongs to logged-in user
if ($paymentDetails['user_id'] !== $authenticatedUserId) {
    // Deny access and log security alert
}
```

### Prevents
- Unauthorized payment status viewing
- Cross-user payment hijacking
- API endpoint enumeration attacks
- Information disclosure

### Logging
- All unauthorized access attempts logged
- Includes user ID, payment ID, IP address
- Alerts triggered on suspicious patterns

---

## 6. MeSomb API Security Features

### MeSomb Security Measures

#### 1. OTP (One-Time Password) Verification
- User receives OTP on their phone for payment confirmation
- OTP must be entered on user's device
- Prevents unauthorized payments even if credentials compromised

#### 2. Transaction Signing
- Each transaction includes cryptographic signature
- Prevents tampering with transaction data
- MeSomb validates signature on their servers

#### 3. IP Whitelisting (Available)
- Can configure trusted server IPs
- MeSomb will only accept requests from whitelisted IPs
- Prevents API calls from unauthorized sources

#### 4. Request Validation
- Application key, access key, and secret key required
- Keys never exposed in client-side code
- Keys stored securely in config file
- API calls must include valid authentication

### Implemented Features in Code

```php
// payment.php - Client-side validation
- Payment method selection validation
- Phone number format validation
- Amount validation (min/max checks)

// process_payment.php - Server-side validation
- Payment access token validation
- CSRF token validation
- Request origin verification
- Rate limiting checks
- Order ownership verification
- Amount range validation
- Payment method validation

// paymentManager.php - MeSomb integration
- Payment ID generation with timestamp
- Customer data encryption
- External reference tracking
- Payment response validation
- Error handling and user-friendly messages
```

---

## 7. Additional Security Measures

### Data Encryption
- Sensitive payment data encrypted before storage
- Customer information encrypted in payment records
- Decryption only happens when needed

### Audit Logging
- All payment attempts logged with:
  - User ID
  - Order ID
  - Payment method
  - Amount
  - IP address
  - Timestamp
  - Success/failure status
  - Error messages

### Input Validation
```php
// Comprehensive validation on all inputs:
- Order ID: Integer, must exist, must belong to user
- Payment Method: Enum (ORANGE, MTN, CAMTEL, YOOMEE)
- Phone Number: Cameroon phone format (6[5-9] or 2[2-3] followed by 7 digits)
- Amount: Positive number, between 0 and 100,000,000 FCFA
- Customer Data: HTML escaped, length validated
```

### Error Handling
- User-friendly error messages shown to users
- Detailed error logs stored server-side only
- No sensitive information exposed in error messages
- Exceptions caught and logged without exposure

---

## 8. Security Workflow

### User Accesses Payment Page
```
1. User authenticated → Check user session
2. Order verified → Check order belongs to user
3. Token generated → Create payment access token
4. Token stored → Session + HttpOnly Cookie
5. Page loaded → Payment interface shown
6. Token embedded → In form and hidden fields
```

### User Submits Payment
```
1. Client validation → Check form fields
2. Security check → Validate payment page token
3. AJAX request → Send to process_payment.php
4. Origin verification → Check request headers
5. Token validation → Verify payment access token
6. CSRF check → Validate CSRF token
7. Rate limiting → Check attempt count
8. Order verification → Confirm order ownership
9. Amount validation → Check amount is valid
10. MeSomb call → Send to payment gateway
11. Response handling → Process MeSomb response
12. Status update → Update database
13. Token invalidate → Clear payment token
14. Redirect → Send user to payment status page
```

### User Checks Payment Status
```
1. User authenticated → Check session
2. Payment ID validated → Format and range check
3. Access verified → Confirm payment belongs to user
4. Details retrieved → Fetch from database
5. Status displayed → Show payment outcome
6. Security alerts → Log any access issues
```

---

## 9. Detected Threats & Prevention

| Threat | Prevention Method |
|--------|------------------|
| Redirect to fake payment | Payment access token validation |
| Session hijacking | Device fingerprinting (User-Agent) + IP logging |
| CSRF attacks | CSRF token rotation |
| Brute force | Rate limiting (20/hour for page, 5/hour per order) |
| Token replay | 30-minute expiration + token invalidation after use |
| API redirection | Request origin verification + whitelist validation |
| Cross-user access | User ownership verification on all operations |
| XSS attacks | Content Security Policy header + HTMLspecialchars |
| Clickjacking | X-Frame-Options: DENY header |
| Man-in-Middle | HSTS header + SameSite cookies |
| Unauthorized OTP | User receives OTP on registered phone only |
| Payment tampering | MeSomb transaction signing |

---

## 10. Best Practices

### For Users
1. Use strong passwords
2. Keep phone number up to date
3. Check phone for OTP confirmation
4. Don't share payment links
5. Verify amounts before confirming OTP

### For Administrators
1. Monitor audit logs regularly
2. Review security alerts
3. Update IP whitelist if changing servers
4. Keep MeSomb credentials secure
5. Test security measures regularly

### For Developers
1. Keep payment.php, process_payment.php, payment_status.php updated
2. Never expose API keys in client-side code
3. Always validate on server-side
4. Use HTTPS for all payment operations
5. Implement rate limiting on all payment endpoints
6. Log all security events
7. Review code before deployment

---

## 11. MeSomb Integration Status

### Current Features Active
- ✅ Phone-based OTP verification
- ✅ Transaction signing and validation
- ✅ Customer information transmission
- ✅ Payment status tracking
- ✅ Error message handling
- ✅ Payment method support (ORANGE, MTN)

### Available but Not Yet Implemented (Optional)
- ⚙️ IP Whitelisting (Add MeSomb server IP to whitelist)
- ⚙️ Webhook validation (Implement webhook verification token)
- ⚙️ 3D Secure (if MeSomb supports - requires update)

### To Enable Advanced Features
```
1. Contact MeSomb support
2. Request IP whitelisting setup
3. Configure webhook endpoint
4. Implement webhook signature validation
5. Test in sandbox environment
6. Deploy to production
```

---

## 12. Configuration

### File: config.ini
```ini
MESOMB_APPLICATION_KEY=your_application_key
MESOMB_ACCESS_KEY=your_access_key
MESOMB_SECRET_KEY=your_secret_key
```

### Environmental Requirements
- PHP >= 7.4
- HTTPS enabled (required for Strict security headers)
- Session security enabled
- Cookie security enabled

---

## 13. Testing Checklist

- [ ] Access payment page directly → Should work
- [ ] Access payment page without authentication → Should redirect to login
- [ ] Tamper with payment access token → Should be rejected
- [ ] Try to access someone else's payment → Should be denied
- [ ] Submit payment form 6 times rapidly → Should be rate limited on 5th
- [ ] Access from iframe → Should be denied
- [ ] Submit fake CSRF token → Should be rejected
- [ ] Invalid phone number → Should show error
- [ ] Amount exceeds limit → Should show error
- [ ] MeSomb server down → Should show friendly error

---

## 14. Incident Response

### If Attack Detected
1. Check logs for patterns
2. Identify attacking IP
3. Block IP at firewall level
4. Notify affected users
5. Review all transactions from attacker
6. Update security measures if needed

### Log Locations
- Application errors: `/error_logs/`
- System logs: Server error_log
- Audit logs: Database (audit_logs table)

---

## 15. Compliance

### OWASP Top 10
- ✅ A01: Broken Access Control (User ownership verification)
- ✅ A02: Cryptographic Failures (HTTPS, encryption)
- ✅ A03: Injection (Input validation, parameterized queries)
- ✅ A04: Insecure Design (Security by design)
- ✅ A05: Security Misconfiguration (Security headers)
- ✅ A06: Vulnerable Components (Updated dependencies)
- ✅ A07: Authentication Failures (Session validation)
- ✅ A08: Buffer Overflow (PHP type checking)
- ✅ A09: Logging Failures (Comprehensive logging)
- ✅ A10: Server Side Template Injection (No templates in payment)

---

**Last Updated**: April 10, 2026  
**Version**: 1.0  
**Status**: Production Ready
