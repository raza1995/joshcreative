<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuizQuestion;

class WellnessQuizSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $questions = [
            [
                'quiz_type' => 'wellness',
                'quiz_version' => '1.0',
                'question_number' => 1,
                'question_id' => 'wellness_q1',
                'question_text' => 'How would you rate your overall physical health?',
                'options' => [
                    ['label' => 'Excellent', 'score' => 4],
                    ['label' => 'Good', 'score' => 3],
                    ['label' => 'Fair', 'score' => 2],
                    ['label' => 'Poor', 'score' => 1],
                    ['label' => 'Very Poor', 'score' => 0],
                ],
                'sort_order' => 1,
            ],
            [
                'quiz_type' => 'wellness',
                'quiz_version' => '1.0',
                'question_number' => 2,
                'question_id' => 'wellness_q2',
                'question_text' => 'How often do you exercise or engage in physical activity?',
                'options' => [
                    ['label' => 'Daily', 'score' => 4],
                    ['label' => '4-6 times per week', 'score' => 3],
                    ['label' => '2-3 times per week', 'score' => 2],
                    ['label' => 'Once a week or less', 'score' => 1],
                    ['label' => 'Never', 'score' => 0],
                ],
                'sort_order' => 2,
            ],
            [
                'quiz_type' => 'wellness',
                'quiz_version' => '1.0',
                'question_number' => 3,
                'question_id' => 'wellness_q3',
                'question_text' => 'How would you describe your stress levels?',
                'options' => [
                    ['label' => 'Very low stress', 'score' => 4],
                    ['label' => 'Low stress', 'score' => 3],
                    ['label' => 'Moderate stress', 'score' => 2],
                    ['label' => 'High stress', 'score' => 1],
                    ['label' => 'Very high stress', 'score' => 0],
                ],
                'sort_order' => 3,
            ],
            [
                'quiz_type' => 'wellness',
                'quiz_version' => '1.0',
                'question_number' => 4,
                'question_id' => 'wellness_q4',
                'question_text' => 'How many hours of sleep do you typically get per night?',
                'options' => [
                    ['label' => '8+ hours', 'score' => 4],
                    ['label' => '7-8 hours', 'score' => 3],
                    ['label' => '6-7 hours', 'score' => 2],
                    ['label' => '5-6 hours', 'score' => 1],
                    ['label' => 'Less than 5 hours', 'score' => 0],
                ],
                'sort_order' => 4,
            ],
            [
                'quiz_type' => 'wellness',
                'quiz_version' => '1.0',
                'question_number' => 5,
                'question_id' => 'wellness_q5',
                'question_text' => 'How satisfied are you with your work-life balance?',
                'options' => [
                    ['label' => 'Very satisfied', 'score' => 4],
                    ['label' => 'Satisfied', 'score' => 3],
                    ['label' => 'Neutral', 'score' => 2],
                    ['label' => 'Dissatisfied', 'score' => 1],
                    ['label' => 'Very dissatisfied', 'score' => 0],
                ],
                'sort_order' => 5,
            ],
        ];

        foreach ($questions as $question) {
            QuizQuestion::updateOrCreate(
                [
                    'quiz_type' => $question['quiz_type'],
                    'question_number' => $question['question_number'],
                    'quiz_version' => $question['quiz_version'],
                ],
                $question
            );
        }

        $this->command->info('Wellness quiz seeded successfully!');
    }
}
