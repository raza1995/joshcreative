# How to Add New Forms/Quiz Types - Complete Guide

## 🎯 Overview

The system is designed to support unlimited quiz types with zero code changes to the core system. Here's exactly how to add new forms/assessments.

---

## 🚀 Quick Start (5-Minute Setup)

### **Step 1: Add Quiz Configuration**
Edit `config/quiz-types.php`:

```php
'your_new_quiz' => [
    'name' => 'Your Assessment Name',
    'description' => 'Brief description of what this assesses',
    'version' => '1.0',
    'max_score' => 30,  // Maximum possible score
    'question_count' => 10,  // Number of questions
    'scoring' => [
        'type' => 'additive',  // additive, weighted, or categorical
        'risk_levels' => [
            'low' => [
                'min' => 0, 
                'max' => 10, 
                'color' => '#059669', 
                'label' => 'Low Risk'
            ],
            'moderate' => [
                'min' => 11, 
                'max' => 20, 
                'color' => '#ca8a04', 
                'label' => 'Moderate Risk'
            ],
            'high' => [
                'min' => 21, 
                'max' => 30, 
                'color' => '#dc2626', 
                'label' => 'High Risk'
            ],
        ],
    ],
    'demographics' => [
        'required' => ['age', 'sex'],  // Required fields
        'optional' => ['email'],       // Optional fields
        'age_brackets' => ['18-24', '25-34', '35-44', '45-54', '55-64', '65+'],
        'gender_options' => ['male', 'female', 'other'],
    ],
    'analytics' => [
        'track_time_per_question' => true,
        'track_user_journey' => true,
        'track_demographics' => true,
        'track_device_info' => true,
        'track_geographic' => true,
    ],
],
```

### **Step 2: Create Questions Seeder**
Create `database/seeders/YourNewQuizSeeder.php`:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuizQuestion;

class YourNewQuizSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'quiz_type' => 'your_new_quiz',
                'question_id' => 1,
                'question_text' => 'Your first question here?',
                'options' => [
                    ['label' => 'Never', 'score' => 0],
                    ['label' => 'Sometimes', 'score' => 1],
                    ['label' => 'Often', 'score' => 2],
                    ['label' => 'Always', 'score' => 3],
                ],
                'version' => '1.0',
            ],
            [
                'quiz_type' => 'your_new_quiz',
                'question_id' => 2,
                'question_text' => 'Your second question here?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'A little', 'score' => 1],
                    ['label' => 'Moderately', 'score' => 2],
                    ['label' => 'Extremely', 'score' => 3],
                ],
                'version' => '1.0',
            ],
            // Add more questions...
        ];

        foreach ($questions as $question) {
            QuizQuestion::updateOrCreate(
                [
                    'quiz_type' => $question['quiz_type'],
                    'question_id' => $question['question_id'],
                    'version' => $question['version'],
                ],
                $question
            );
        }
    }
}
```

### **Step 3: Run the Seeder**
```bash
php artisan db:seed --class=YourNewQuizSeeder
```

### **Step 4: That's It!**
Your new quiz type is now:
- ✅ Available in the analytics dashboard
- ✅ Fully functional with all analytics
- ✅ Automatically included in all reports
- ✅ Ready for frontend integration

---

## 📊 Database Structure (Already Built)

The current database structure supports unlimited quiz types:

### **Core Tables**
```sql
-- Main session data
quiz_sessions (
    id, session_uuid, quiz_type, user_email,
    demographics (JSON), responses (JSON),
    total_score, risk_level, completed,
    started_at, completed_at, time_taken_seconds,
    shopify_domain, referrer_url, session_metadata (JSON),
    ip_address, user_agent
)

-- Dynamic questions for any quiz type
quiz_questions (
    id, quiz_type, question_id, question_text,
    options (JSON), version
)

-- Detailed analytics for any quiz type
quiz_analytics (
    id, quiz_session_id, event_type, question_id,
    event_data (JSON), event_timestamp,
    time_on_question, user_action, interaction_data (JSON)
)
```

### **Why This Structure is Perfect**
- **`quiz_type` field**: Separates different assessments
- **JSON fields**: Store flexible, dynamic data
- **Relationship-based**: Maintains data integrity
- **Scalable**: Handles unlimited quiz types
- **Analytics-ready**: Tracks everything automatically

---

## 🎨 Frontend Integration

### **Option 1: Copy Existing HTML Template**
1. Copy `public/mycolean-alcohol-quiz-single.html`
2. Rename to `public/your-new-quiz-single.html`
3. Update the quiz configuration:

```javascript
// Change the quiz type
const QUIZ_TYPE = 'your_new_quiz';

// Update questions array
const QUIZ = [
    {type: "gate", title: "Age Verification"},
    {type: "demographics", title: "About You"},
    {type: "audit", id: 1, title: "Your first question here?", options: [...]},
    {type: "audit", id: 2, title: "Your second question here?", options: [...]},
    // ... more questions
];
```

### **Option 2: API Integration**
Use the existing API endpoints with your new quiz type:

```javascript
// Start session
POST /api/quiz/start
{
    "quiz_type": "your_new_quiz",
    "shopify_domain": "your-store.myshopify.com"
}

// Get questions dynamically
GET /api/quiz/questions?quiz_type=your_new_quiz

// Save responses
POST /api/quiz/response
{
    "session_uuid": "...",
    "question_id": 1,
    "score": 2
}

// Complete quiz
POST /api/quiz/complete
{
    "session_uuid": "...",
    "responses": {1: 2, 2: 1, 3: 3}
}
```

---

## 🔧 Advanced Customization

### **Custom Scoring Systems**

#### **Weighted Scoring**
```php
'scoring' => [
    'type' => 'weighted',
    'weights' => [
        1 => 2.0,  // Question 1 worth 2x
        2 => 1.5,  // Question 2 worth 1.5x
        3 => 1.0,  // Question 3 normal weight
    ],
    'risk_levels' => [
        // Define based on weighted scores
    ],
],
```

#### **Categorical Scoring**
```php
'scoring' => [
    'type' => 'categorical',
    'categories' => [
        'anxiety' => [1, 2, 3],      // Questions 1-3 measure anxiety
        'depression' => [4, 5, 6],   // Questions 4-6 measure depression
        'stress' => [7, 8, 9],       // Questions 7-9 measure stress
    ],
    'risk_levels' => [
        // Define based on category scores
    ],
],
```

### **Custom Risk Messages**
Add to `app/Services/QuizTypeService.php`:

```php
private function getYourQuizRiskMessage(string $level, int $score): string
{
    switch ($level) {
        case 'low':
            return 'Your results suggest low risk. Continue healthy practices.';
        case 'moderate':
            return 'Moderate risk detected. Consider lifestyle changes.';
        case 'high':
            return 'High risk indicated. Seek professional guidance.';
        default:
            return 'Please consult with a healthcare professional.';
    }
}
```

### **Custom Analytics**
Override in `QuizTypeService::generateInsights()`:

```php
case 'your_new_quiz':
    return $this->generateYourQuizInsights($sessions);
```

---

## 📈 Analytics Automatically Include

When you add a new quiz type, analytics automatically include:

### **Dashboard Metrics**
- Total sessions for your quiz type
- Completion rates and conversion funnel
- Average scores and risk distribution
- Time analytics and user journey
- Demographic breakdowns
- Device and geographic data

### **Detailed Analytics**
- Question-by-question performance
- Drop-off analysis
- Engagement metrics
- Risk correlation analysis
- Comparative analysis with other quiz types

### **Export Capabilities**
- CSV export with all data
- Filtered by quiz type
- Complete session details
- Analytics events included

---

## 🎯 Real-World Examples

### **Example 1: Customer Satisfaction Survey**
```php
'customer_satisfaction' => [
    'name' => 'Customer Satisfaction Survey',
    'max_score' => 50,
    'question_count' => 10,
    'scoring' => [
        'type' => 'additive',
        'risk_levels' => [
            'dissatisfied' => ['min' => 0, 'max' => 20, 'color' => '#dc2626'],
            'neutral' => ['min' => 21, 'max' => 35, 'color' => '#ca8a04'],
            'satisfied' => ['min' => 36, 'max' => 50, 'color' => '#059669'],
        ],
    ],
],
```

### **Example 2: Employee Wellness Assessment**
```php
'employee_wellness' => [
    'name' => 'Employee Wellness Check',
    'max_score' => 40,
    'question_count' => 8,
    'scoring' => [
        'type' => 'categorical',
        'categories' => [
            'physical' => [1, 2, 3],
            'mental' => [4, 5, 6],
            'social' => [7, 8],
        ],
    ],
],
```

### **Example 3: Educational Assessment**
```php
'learning_assessment' => [
    'name' => 'Learning Style Assessment',
    'max_score' => 60,
    'question_count' => 15,
    'scoring' => [
        'type' => 'weighted',
        'weights' => [
            1 => 1.5, 2 => 1.5, 3 => 1.0, // Visual learning questions
            4 => 2.0, 5 => 2.0, 6 => 1.0, // Auditory learning questions
            7 => 1.2, 8 => 1.2, 9 => 1.0, // Kinesthetic learning questions
        ],
    ],
],
```

---

## 🔮 Future Enhancements (Optional)

### **Advanced Features You Can Add**

#### **1. Conditional Logic**
```php
'conditional_logic' => [
    'question_3' => [
        'show_if' => ['question_1' => [2, 3, 4]], // Show Q3 if Q1 score is 2, 3, or 4
    ],
],
```

#### **2. Multi-Language Support**
```php
'languages' => [
    'en' => ['question_text' => 'English question'],
    'es' => ['question_text' => 'Spanish question'],
    'fr' => ['question_text' => 'French question'],
],
```

#### **3. Custom Validation Rules**
```php
'validation' => [
    'question_1' => ['required', 'min:0', 'max:4'],
    'question_2' => ['required', 'numeric'],
],
```

#### **4. Integration Webhooks**
```php
'webhooks' => [
    'completion' => 'https://your-app.com/webhook/quiz-completed',
    'high_risk' => 'https://your-app.com/webhook/high-risk-detected',
],
```

---

## ✅ Summary: Adding New Forms is Simple

### **What You Need to Do (5 minutes)**
1. Add configuration to `config/quiz-types.php`
2. Create questions seeder
3. Run the seeder

### **What Happens Automatically**
- ✅ Analytics dashboard includes new quiz
- ✅ All metrics calculated automatically
- ✅ Export functionality works
- ✅ API endpoints support new quiz type
- ✅ Risk calculations work
- ✅ User journey tracking enabled
- ✅ Demographic analysis included

### **What You DON'T Need to Change**
- ❌ No database migrations needed
- ❌ No controller modifications
- ❌ No view updates required
- ❌ No API endpoint changes
- ❌ No analytics code changes

**The system is designed for unlimited scalability with minimal effort!** 🚀✨

---

## 🎯 Need Help?

The system includes:
- **Complete documentation** in config files
- **Example implementations** (AUDIT, GAD-7, PHQ-9)
- **Flexible architecture** for any assessment type
- **Automatic analytics** for all quiz types
- **Professional UI** that adapts automatically

**You can add any type of assessment - from medical screenings to customer surveys to educational evaluations - all with the same simple process!**
