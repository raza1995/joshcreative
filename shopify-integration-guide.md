# Shopify Integration Guide for Mycolean Quiz

## Quick Setup (Copy & Paste Method)

### Step 1: Copy the Quiz Code
1. Open `public/mycolean-alcohol-quiz-single.html`
2. Copy the entire file content (Ctrl+A, Ctrl+C)

### Step 2: Add to Shopify
1. **Go to Shopify Admin** → Online Store → Themes
2. **Click "Actions"** → Edit code
3. **Add a new section:**
   - Click "Add a new section"
   - Name it: `mycolean-quiz`
   - Paste the HTML content
   - Save

### Step 3: Add to Page/Product
1. **Create a new page** or edit existing one
2. **In the page editor**, add a "Custom Liquid" block
3. **Add this code:**
```liquid
{% section 'mycolean-quiz' %}
```
4. **Save and publish**

## Advanced Integration Options

### Option 1: Product Page Integration
Add the quiz to specific product pages:

```liquid
<!-- In product.liquid template -->
{% if product.handle == 'mycolean-product' %}
  {% section 'mycolean-quiz' %}
{% endif %}
```

### Option 2: Collection Page Integration
Show quiz on collection pages:

```liquid
<!-- In collection.liquid template -->
{% if collection.handle == 'alcohol-support' %}
  {% section 'mycolean-quiz' %}
{% endif %}
```

### Option 3: Blog Article Integration
Embed in blog posts about alcohol awareness:

```liquid
<!-- In article.liquid template -->
{% if article.tags contains 'alcohol-quiz' %}
  {% section 'mycolean-quiz' %}
{% endif %}
```

## Configuration for Your Store

### Update API URL
The quiz automatically detects Shopify domains, but you can customize:

1. **Find this line in the HTML:**
```javascript
return 'https://joshcreative.co/api/quiz';
```

2. **Replace with your domain:**
```javascript
return 'https://your-domain.com/api/quiz';
```

### Add Your Shopify Domain to Laravel
In your Laravel app's `config/quiz.php`:

```php
'allowed_origins' => [
    'https://your-store.myshopify.com',
    'https://your-custom-domain.com',
    // Add more domains as needed
]
```

## Customization Options

### 1. Styling Customization
The quiz includes Shopify-specific styles. You can customize:

```css
/* Add to your theme's CSS */
.shopify-embedded .card {
    background: var(--color-background);
    border: 1px solid var(--color-border);
}

.shopify-embedded .btn-primary {
    background: var(--color-button);
    color: var(--color-button-text);
}
```

### 2. Branding Integration
Update the logo and colors to match your brand:

```css
.logo {
    background: linear-gradient(45deg, #your-color-1, #your-color-2);
}

:root {
    --teal: #your-primary-color;
    --purple: #your-secondary-color;
}
```

### 3. Content Customization
You can modify the quiz content by editing:
- Question text
- Answer options
- Disclaimer text
- Result messages

## Analytics & Tracking

### Shopify-Specific Data Captured:
- Store domain
- Theme information
- Referrer URLs
- Customer journey data

### Integration with Shopify Analytics:
The quiz can be integrated with:
- Google Analytics (add your GA code)
- Shopify's built-in analytics
- Custom tracking pixels

### Example GA Integration:
```javascript
// Add after quiz completion
if (typeof gtag !== 'undefined') {
    gtag('event', 'quiz_completed', {
        'custom_parameter_1': totalScore,
        'custom_parameter_2': riskLevel
    });
}
```

## Testing Your Integration

### 1. Preview Mode
- Use Shopify's preview mode to test
- Check browser console for API calls
- Verify data is being saved

### 2. Mobile Testing
- Test on mobile devices
- Check responsive design
- Verify touch interactions work

### 3. Performance Testing
- Check page load times
- Monitor API response times
- Test with slow connections

## Troubleshooting

### Common Issues:

1. **Quiz not loading:**
   - Check browser console for errors
   - Verify API URL is correct
   - Check CORS settings

2. **Styling conflicts:**
   - Add `!important` to quiz styles if needed
   - Use more specific CSS selectors
   - Check for theme CSS conflicts

3. **API errors:**
   - Verify Laravel app is running
   - Check database connection
   - Confirm CORS configuration

### Debug Mode:
Add this to enable debug logging:
```javascript
// Add after API_BASE definition
const DEBUG_MODE = true;
if (DEBUG_MODE) {
    console.log('Quiz Debug Mode Enabled');
}
```

## Security Considerations

### 1. CORS Configuration
Ensure your Laravel app only allows requests from your Shopify domains:

```php
// In config/quiz.php
'allowed_origins' => [
    'https://your-store.myshopify.com',
    // Don't use wildcards in production
]
```

### 2. Rate Limiting
The API includes rate limiting to prevent abuse:
- 10 requests per IP per hour (configurable)
- Session-based tracking
- Automatic cleanup of old data

### 3. Data Privacy
- No personally identifiable information is required
- IP addresses can be disabled in config
- GDPR compliant (anonymous by default)

## Performance Optimization

### 1. CDN Integration
Consider using a CDN for the quiz assets:
- Host images on Shopify's CDN
- Use compressed CSS/JS
- Enable browser caching

### 2. Lazy Loading
Load the quiz only when needed:
```javascript
// Load quiz when user scrolls to it
const observer = new IntersectionObserver((entries) => {
    if (entries[0].isIntersecting) {
        initQuiz();
    }
});
observer.observe(document.getElementById('quiz-container'));
```

## Support & Maintenance

### Regular Tasks:
1. **Monitor API performance**
2. **Review quiz analytics**
3. **Update question content as needed**
4. **Check for Shopify theme updates**

### Backup Strategy:
- Export quiz data regularly
- Keep backups of customized code
- Document any theme modifications

## Success Metrics

Track these KPIs:
- Quiz completion rate
- Time spent on quiz
- Conversion from quiz to product purchase
- User engagement metrics
- Risk level distribution

The quiz is now ready for production use in your Shopify store! 🚀
