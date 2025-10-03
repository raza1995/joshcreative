# Quiz System Setup Instructions

## Database Setup

1. **Start your database server** (MySQL/MariaDB)

2. **Run migrations:**
```bash
php artisan migrate
```

3. **Seed quiz questions:**
```bash
php artisan db:seed --class=QuizQuestionsSeeder
```

## Testing the System

### 1. Test the HTML Quiz
- Open `public/mycolean-alcohol-quiz-single.html` in your browser
- Complete the quiz and check browser console for API calls
- Verify data is saved to database

### 2. Access the Dashboard
- Login to your Laravel app
- Visit: `/quiz/dashboard`
- View analytics at: `/quiz/analytics`
- Browse sessions at: `/quiz/sessions`

## API Endpoints Available

### Quiz API (`/api/quiz/`)
- `POST /start` - Start new quiz session
- `GET /session/{uuid}` - Get session data
- `GET /questions` - Get quiz questions
- `POST /demographics` - Save demographics
- `POST /response` - Save individual response
- `POST /complete` - Complete quiz
- `POST /track` - Track analytics event
- `GET /stats` - Get statistics

### Dashboard Routes (`/quiz/`)
- `/dashboard` - Main analytics dashboard
- `/sessions` - List all sessions with filters
- `/session/{uuid}` - Individual session details
- `/analytics` - Detailed analytics
- `/export` - Export data to CSV

## Shopify Integration

1. **Copy the HTML file content** from `public/mycolean-alcohol-quiz-single.html`

2. **Paste into Shopify:**
   - Go to Shopify Admin → Online Store → Themes
   - Edit Code → Add a new section or template
   - Paste the HTML content

3. **Update API URL:**
   - The HTML automatically detects Shopify domains
   - Ensure your Laravel app allows CORS from Shopify domains
   - Update `config/quiz.php` with your Shopify domains

## Configuration

### Environment Variables
Add to your `.env` file:
```env
QUIZ_ALLOWED_ORIGINS="https://mycolean.com,https://your-shop.myshopify.com,http://localhost"
QUIZ_DEFAULT_TYPE=audit
QUIZ_DEFAULT_VERSION=1.0
QUIZ_TRACK_ANALYTICS=true
QUIZ_STORE_USER_AGENTS=true
QUIZ_STORE_IPS=true
QUIZ_SESSION_TIMEOUT=60
QUIZ_ALLOW_ANONYMOUS=true
QUIZ_RATE_LIMIT=10
QUIZ_DATA_RETENTION_DAYS=365
```

## Features Included

✅ **Complete Quiz System:**
- 10-question AUDIT alcohol assessment
- Optional email collection for user tracking
- Real-time API integration
- Offline fallback capability
- Comprehensive analytics tracking

✅ **Admin Dashboard:**
- Overview statistics with charts
- Session management and filtering
- Individual session details
- Question-by-question analytics
- CSV export functionality

✅ **Shopify Ready:**
- Copy-paste HTML integration
- Automatic domain detection
- CORS configuration
- Responsive design

✅ **Analytics & Insights:**
- Completion funnel analysis
- Risk level distribution
- Time analytics
- Question performance metrics
- Trend analysis over time

## Database Tables Created

1. **`quiz_sessions`** - Main quiz attempts
2. **`quiz_questions`** - Dynamic question storage
3. **`quiz_analytics`** - Detailed event tracking

## Next Steps

1. Set up your database connection
2. Run the migrations and seeder
3. Test the quiz HTML file
4. Access the dashboard
5. Configure for Shopify integration
6. Customize styling as needed

The system is production-ready and scalable!
