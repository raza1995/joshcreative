<?php

namespace App\Services;

use App\Models\QuizSession;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MycoleanEmailService
{
    /**
     * Send personalized follow-up email based on AI analysis
     */
    public function sendPersonalizedFollowUp(QuizSession $session): bool
    {
        if (empty($session->user_email)) {
            return false;
        }

        $aiAnalysis = $session->session_metadata['ai_analysis'] ?? null;
        if (!$aiAnalysis) {
            return false;
        }

        $conversionProb = $aiAnalysis['conversion_probability']['probability'] ?? 0;
        $mycoleanRec = $aiAnalysis['mycolean_recommendation'] ?? [];

        try {
            if ($conversionProb >= 0.7) {
                $this->sendHighConversionEmail($session, $aiAnalysis);
            } elseif ($conversionProb >= 0.5) {
                $this->sendMediumConversionEmail($session, $aiAnalysis);
            } else {
                $this->sendEducationalEmail($session, $aiAnalysis);
            }

            // Log the email send
            Log::info('Mycolean follow-up email sent', [
                'session_uuid' => $session->session_uuid,
                'email' => $session->user_email,
                'conversion_probability' => $conversionProb,
                'email_type' => $conversionProb >= 0.7 ? 'high_conversion' : ($conversionProb >= 0.5 ? 'medium_conversion' : 'educational')
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send Mycolean follow-up email', [
                'session_uuid' => $session->session_uuid,
                'email' => $session->user_email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Send high-conversion probability email (70%+)
     */
    private function sendHighConversionEmail(QuizSession $session, array $aiAnalysis): void
    {
        $mycoleanRec = $aiAnalysis['mycolean_recommendation'];
        $conversionProb = $aiAnalysis['conversion_probability'];
        
        $subject = "🚀 Your Perfect Mycolean Match - {$conversionProb['confidence_level']} Compatibility!";
        
        $emailContent = $this->generateHighConversionEmailContent($session, $aiAnalysis);
        
        // Send email (you would integrate with your email service here)
        $this->sendEmail($session->user_email, $subject, $emailContent, 'high_conversion');
    }

    /**
     * Send medium-conversion probability email (50-69%)
     */
    private function sendMediumConversionEmail(QuizSession $session, array $aiAnalysis): void
    {
        $subject = "🌟 Discover Your Alcohol Alternative - Personalized for You";
        
        $emailContent = $this->generateMediumConversionEmailContent($session, $aiAnalysis);
        
        $this->sendEmail($session->user_email, $subject, $emailContent, 'medium_conversion');
    }

    /**
     * Send educational email (under 50%)
     */
    private function sendEducationalEmail(QuizSession $session, array $aiAnalysis): void
    {
        $subject = "🧠 Your Quiz Results + Free Alcohol Alternative Guide";
        
        $emailContent = $this->generateEducationalEmailContent($session, $aiAnalysis);
        
        $this->sendEmail($session->user_email, $subject, $emailContent, 'educational');
    }

    /**
     * Generate high-conversion email content
     */
    private function generateHighConversionEmailContent(QuizSession $session, array $aiAnalysis): string
    {
        $mycoleanRec = $aiAnalysis['mycolean_recommendation'];
        $conversionProb = $aiAnalysis['conversion_probability'];
        $personalizedMessage = $aiAnalysis['personalized_message'];
        
        $discountCode = $this->generateDiscountCode($session);
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 15px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: white; margin: 0;'>🚀 You're a Perfect Match for Mycolean!</h1>
                <p style='font-size: 18px; margin: 10px 0; opacity: 0.9;'>Based on your quiz results, you have a {$conversionProb['probability']}% compatibility with our alcohol alternative</p>
            </div>
            
            <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin: 20px 0;'>
                <h3 style='color: white; margin-top: 0;'>🎯 Your Personalized Analysis:</h3>
                <p style='opacity: 0.9; line-height: 1.6;'>{$personalizedMessage}</p>
            </div>
            
            <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin: 20px 0;'>
                <h3 style='color: white; margin-top: 0;'>✨ Perfect for You Because:</h3>
                <ul style='opacity: 0.9; line-height: 1.8;'>
                    " . implode('', array_map(fn($scenario) => "<li>{$scenario}</li>", $mycoleanRec['usage_scenarios'])) . "
                </ul>
            </div>
            
            <div style='background: rgba(255,255,255,0.1); padding: 20px; border-radius: 10px; margin: 20px 0;'>
                <h3 style='color: white; margin-top: 0;'>🎁 EXCLUSIVE OFFER - 25% OFF</h3>
                <p style='opacity: 0.9;'>Use code: <strong style='background: rgba(255,255,255,0.2); padding: 5px 10px; border-radius: 5px;'>{$discountCode}</strong></p>
                <p style='opacity: 0.8; font-size: 14px;'>Valid for 48 hours only - because you're such a great match!</p>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://mycolean.com/?utm_source=quiz&utm_medium=email&utm_campaign=high_conversion&discount={$discountCode}' 
                   style='display: inline-block; background: #ff6b6b; color: white; padding: 18px 35px; text-decoration: none; border-radius: 25px; font-weight: bold; font-size: 18px; box-shadow: 0 4px 15px rgba(255,107,107,0.3);'>
                    🛒 Get Mycolean Now - 25% OFF
                </a>
            </div>
            
            <div style='background: rgba(255,255,255,0.1); padding: 15px; border-radius: 10px; margin: 20px 0;'>
                <h4 style='color: white; margin-top: 0;'>📈 Your Success Plan:</h4>
                <p style='opacity: 0.9; margin: 0;'>{$mycoleanRec['transition_plan']}</p>
            </div>
            
            <div style='text-align: center; margin-top: 30px; opacity: 0.8; font-size: 14px;'>
                <p>Questions? Reply to this email - we're here to help!</p>
                <p>🌟 Join 30,000+ people who've made the switch to Mycolean</p>
            </div>
        </div>";
    }

    /**
     * Generate medium-conversion email content
     */
    private function generateMediumConversionEmailContent(QuizSession $session, array $aiAnalysis): string
    {
        $mycoleanRec = $aiAnalysis['mycolean_recommendation'];
        $personalizedMessage = $aiAnalysis['personalized_message'];
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #333;'>🌟 Your Personalized Alcohol Alternative</h1>
                <p style='font-size: 16px; color: #666;'>Based on your quiz results, here's what we recommend</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #667eea;'>
                <h3 style='color: #333; margin-top: 0;'>🧠 Your AI Analysis:</h3>
                <p style='color: #555; line-height: 1.6;'>{$personalizedMessage}</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0;'>
                <h3 style='color: #333; margin-top: 0;'>✨ Why Mycolean Could Work for You:</h3>
                <ul style='color: #555; line-height: 1.8;'>
                    " . implode('', array_map(fn($benefit) => "<li>{$benefit}</li>", $mycoleanRec['expected_benefits'])) . "
                </ul>
            </div>
            
            <div style='background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; margin: 30px 0;'>
                <h3 style='margin-top: 0; color: white;'>🎁 Special Offer: Try Risk-Free</h3>
                <p style='opacity: 0.9;'>Get a free sample + 15% off your first order</p>
                <a href='https://mycolean.com/free-sample?utm_source=quiz&utm_medium=email&utm_campaign=medium_conversion' 
                   style='display: inline-block; background: #ff6b6b; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold; margin-top: 10px;'>
                    🆓 Get Free Sample
                </a>
            </div>
            
            <div style='text-align: center; margin-top: 30px; color: #666; font-size: 14px;'>
                <p>Questions? We're here to help: support@mycolean.com</p>
            </div>
        </div>";
    }

    /**
     * Generate educational email content
     */
    private function generateEducationalEmailContent(QuizSession $session, array $aiAnalysis): string
    {
        $riskLevel = $session->risk_level ?? 'unknown';
        $totalScore = $session->total_score ?? 0;
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 30px;'>
            <div style='text-align: center; margin-bottom: 30px;'>
                <h1 style='color: #333;'>🧠 Your Quiz Results + Helpful Resources</h1>
                <p style='font-size: 16px; color: #666;'>Thank you for taking our alcohol assessment</p>
            </div>
            
            <div style='background: #f8f9fa; padding: 20px; border-radius: 10px; margin: 20px 0; text-align: center;'>
                <h2 style='color: #333; margin: 0;'>Your Score: {$totalScore}</h2>
                <p style='color: #666; margin: 10px 0;'>Risk Level: " . ucfirst($riskLevel) . "</p>
            </div>
            
            <div style='background: #e3f2fd; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #2196f3;'>
                <h3 style='color: #333; margin-top: 0;'>📚 Understanding Your Results</h3>
                <p style='color: #555; line-height: 1.6;'>
                    The AUDIT (Alcohol Use Disorders Identification Test) is a scientifically validated screening tool 
                    developed by the World Health Organization. Your results provide insights into your drinking patterns 
                    and potential areas for improvement.
                </p>
            </div>
            
            <div style='background: #f3e5f5; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #9c27b0;'>
                <h3 style='color: #333; margin-top: 0;'>🌟 Did You Know?</h3>
                <p style='color: #555; line-height: 1.6;'>
                    Many people are exploring alcohol alternatives like Mycolean to maintain their social life 
                    while reducing alcohol consumption. It's a growing trend toward healthier lifestyle choices.
                </p>
            </div>
            
            <div style='background: #fff3e0; padding: 20px; border-radius: 10px; margin: 20px 0; border-left: 4px solid #ff9800;'>
                <h3 style='color: #333; margin-top: 0;'>🎯 Free Resources for You:</h3>
                <ul style='color: #555; line-height: 1.8;'>
                    <li>📖 Complete Guide to Alcohol Alternatives</li>
                    <li>🧘 Mindful Drinking Techniques</li>
                    <li>🍹 Mocktail Recipes for Social Events</li>
                    <li>💪 Building Healthy Social Habits</li>
                </ul>
            </div>
            
            <div style='text-align: center; margin: 30px 0;'>
                <a href='https://mycolean.com/resources?utm_source=quiz&utm_medium=email&utm_campaign=educational' 
                   style='display: inline-block; background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 25px; font-weight: bold;'>
                    📚 Access Free Resources
                </a>
            </div>
            
            <div style='text-align: center; margin-top: 30px; color: #666; font-size: 14px;'>
                <p>Want to learn more about Mycolean? <a href='https://mycolean.com' style='color: #667eea;'>Visit our website</a></p>
                <p>Questions? We're here to help: support@mycolean.com</p>
            </div>
        </div>";
    }

    /**
     * Generate unique discount code for user
     */
    private function generateDiscountCode(QuizSession $session): string
    {
        $prefix = 'QUIZ25';
        $suffix = strtoupper(substr($session->session_uuid, 0, 6));
        return $prefix . $suffix;
    }

    /**
     * Send email using your preferred email service
     */
    private function sendEmail(string $email, string $subject, string $content, string $type): void
    {
        // This is a placeholder - integrate with your actual email service
        // Examples: SendGrid, Mailgun, AWS SES, etc.
        
        Log::info('Email would be sent', [
            'to' => $email,
            'subject' => $subject,
            'type' => $type,
            'content_length' => strlen($content)
        ]);
        
        // Example integration with Laravel Mail:
        /*
        Mail::send([], [], function ($message) use ($email, $subject, $content) {
            $message->to($email)
                    ->subject($subject)
                    ->html($content);
        });
        */
    }

    /**
     * Schedule follow-up emails based on user behavior
     */
    public function scheduleFollowUpSequence(QuizSession $session): void
    {
        if (empty($session->user_email)) {
            return;
        }

        $aiAnalysis = $session->session_metadata['ai_analysis'] ?? null;
        if (!$aiAnalysis) {
            return;
        }

        $conversionProb = $aiAnalysis['conversion_probability']['probability'] ?? 0;

        // Schedule immediate follow-up
        $this->sendPersonalizedFollowUp($session);

        // Schedule additional emails based on conversion probability
        if ($conversionProb >= 0.7) {
            // High conversion: aggressive follow-up
            $this->scheduleEmail($session, 'reminder', 24); // 24 hours
            $this->scheduleEmail($session, 'urgency', 48);  // 48 hours
        } elseif ($conversionProb >= 0.5) {
            // Medium conversion: educational sequence
            $this->scheduleEmail($session, 'testimonials', 72); // 3 days
            $this->scheduleEmail($session, 'benefits', 168);    // 1 week
        } else {
            // Low conversion: nurture sequence
            $this->scheduleEmail($session, 'education', 168);   // 1 week
            $this->scheduleEmail($session, 'community', 336);   // 2 weeks
        }
    }

    /**
     * Schedule an email to be sent later
     */
    private function scheduleEmail(QuizSession $session, string $type, int $hoursDelay): void
    {
        // This would integrate with your job queue system
        // Example: Laravel Queues, Redis, etc.
        
        Log::info('Email scheduled', [
            'session_uuid' => $session->session_uuid,
            'email' => $session->user_email,
            'type' => $type,
            'delay_hours' => $hoursDelay,
            'send_at' => Carbon::now()->addHours($hoursDelay)->toIso8601String()
        ]);
        
        // Example with Laravel Jobs:
        /*
        dispatch(new SendMycoleanFollowUpEmail($session, $type))
            ->delay(Carbon::now()->addHours($hoursDelay));
        */
    }

    /**
     * Get email performance analytics
     */
    public function getEmailAnalytics(int $days = 30): array
    {
        $startDate = Carbon::now()->subDays($days);
        
        $sessions = QuizSession::where('completed', true)
            ->where('completed_at', '>=', $startDate)
            ->whereNotNull('user_email')
            ->get();

        $emailsSent = 0;
        $highConversionEmails = 0;
        $mediumConversionEmails = 0;
        $educationalEmails = 0;

        foreach ($sessions as $session) {
            $aiAnalysis = $session->session_metadata['ai_analysis'] ?? null;
            if ($aiAnalysis) {
                $emailsSent++;
                $conversionProb = $aiAnalysis['conversion_probability']['probability'] ?? 0;
                
                if ($conversionProb >= 0.7) {
                    $highConversionEmails++;
                } elseif ($conversionProb >= 0.5) {
                    $mediumConversionEmails++;
                } else {
                    $educationalEmails++;
                }
            }
        }

        return [
            'total_emails_sent' => $emailsSent,
            'high_conversion_emails' => $highConversionEmails,
            'medium_conversion_emails' => $mediumConversionEmails,
            'educational_emails' => $educationalEmails,
            'email_types_distribution' => [
                'high_conversion' => $emailsSent > 0 ? round(($highConversionEmails / $emailsSent) * 100) : 0,
                'medium_conversion' => $emailsSent > 0 ? round(($mediumConversionEmails / $emailsSent) * 100) : 0,
                'educational' => $emailsSent > 0 ? round(($educationalEmails / $emailsSent) * 100) : 0,
            ]
        ];
    }
}
