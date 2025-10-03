# Wellness Quiz Demo - How to Add New Quiz Forms

This document demonstrates how easy it is to add new quiz forms to the system using the **Wellness Assessment** as an example.

## What We Just Created

### 1. Quiz Configuration (`config/quiz-types.php`)
```php
'wellness' => [
    'name' => 'Wellness Assessment',
    'description' => 'Simple wellness and lifestyle assessment',
    'version' => '1.0',
    'max_score' => 20,
    'question_count' => 5,
    'scoring' => [
        'type' => 'additive',
        'risk_levels' => [
            'excellent' => ['min' => 16, 'max' => 20, 'color' => '#059669', 'label' => 'Excellent Wellness'],
            'good' => ['min' => 12, 'max' => 15, 'color' => '#10b981', 'label' => 'Good Wellness'],
            'fair' => ['min' => 8, 'max' => 11, 'color' => '#ca8a04', 'label' => 'Fair Wellness'],
            'poor' => ['min' => 4, 'max' => 7, 'color' => '#dc2626', 'label' => 'Poor Wellness'],
            'very_poor' => ['min' => 0, 'max' => 3, 'color' => '#7f1d1d', 'label' => 'Very Poor Wellness'],
        ],
    ],
],
```

### 2. Database Seeder (`database/seeders/WellnessQuizSeeder.php`)
- Created 5 wellness questions with scoring options
- Each question has 5 options (0-4 points)
- Questions cover: Physical Health, Exercise, Stress, Sleep, Work-Life Balance

### 3. Frontend HTML (`public/wellness-quiz-single.html`)
- Complete standalone HTML file
- Beautiful gradient design
- Fully integrated with the API
- Ready to copy-paste into Shopify

## How to Test the Wellness Quiz

### 1. Visit the HTML File
```
http://your-domain.com/wellness-quiz-single.html
```

### 2. Check Dashboard Analytics
- Go to Quiz Dashboard
- Select "wellness" from the quiz type dropdown
- View analytics specific to wellness assessments

### 3. API Integration
The wellness quiz automatically uses all existing API endpoints:
- `/api/quiz/start` - Creates session with `quiz_type: 'wellness'`
- `/api/quiz/demographics` - Saves user info
- `/api/quiz/response` - Saves each answer
- `/api/quiz/complete` - Calculates wellness level

## Key Benefits of This Architecture

### 1. **Zero Backend Changes Required**
- No controller modifications needed
- No route changes required
- Existing API handles all quiz types automatically

### 2. **Automatic Analytics Integration**
- Dashboard automatically shows wellness quiz data
- All existing analytics work for wellness quiz
- Risk distribution charts update automatically

### 3. **Database Flexibility**
- Same tables handle all quiz types
- `quiz_type` field differentiates quizzes
- Scoring and analytics adapt automatically

### 4. **Frontend Independence**
- Each quiz can have completely different UI/UX
- Wellness quiz has different colors, icons, messaging
- Easy to customize for different brands/purposes

## Adding More Quizzes - 3 Simple Steps

### Step 1: Add Configuration
```php
// config/quiz-types.php
'your_quiz' => [
    'name' => 'Your Quiz Name',
    'max_score' => 30,
    'question_count' => 6,
    'scoring' => [
        'risk_levels' => [
            // Define your scoring levels
        ],
    ],
],
```

### Step 2: Seed Questions
```php
// database/seeders/YourQuizSeeder.php
$questions = [
    [
        'quiz_type' => 'your_quiz',
        'question_id' => 1,
        'question_text' => 'Your question here?',
        'options' => [
            ['label' => 'Option 1', 'score' => 0],
            ['label' => 'Option 2', 'score' => 1],
            // ... more options
        ],
    ],
    // ... more questions
];
```

### Step 3: Create HTML Frontend
- Copy `wellness-quiz-single.html`
- Update `QUIZ_TYPE = 'your_quiz'`
- Modify questions array
- Customize styling and messaging
- Done! 🎉

## What Happens Automatically

✅ **API Integration** - All endpoints work immediately  
✅ **Database Storage** - Questions and responses saved automatically  
✅ **Analytics Dashboard** - Charts and metrics update automatically  
✅ **Risk Calculation** - Scoring system works based on configuration  
✅ **Session Management** - User sessions tracked automatically  
✅ **Export Functionality** - CSV exports include new quiz data  

## Testing the Wellness Quiz

1. **Open the wellness quiz**: `http://joshcreative.test/wellness-quiz-single.html`
2. **Complete the assessment** (takes ~2 minutes)
3. **Check the dashboard**: Navigate to Quiz Dashboard → Select "wellness" filter
4. **View analytics**: See how the data appears in charts and metrics

The wellness quiz demonstrates that adding new forms is incredibly simple and requires minimal code changes while providing full functionality and analytics integration.
