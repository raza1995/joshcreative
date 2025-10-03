<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuizQuestion;

class SimpleQuizTypesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // GAD-7 Anxiety Assessment Questions
        $gad7Questions = [
            [
                'quiz_type' => 'gad7',
                'quiz_version' => '1.0',
                'question_number' => 1,
                'question_id' => 'gad7_q1',
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by feeling nervous, anxious, or on edge?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'sort_order' => 1,
            ],
            [
                'quiz_type' => 'gad7',
                'quiz_version' => '1.0',
                'question_number' => 2,
                'question_id' => 'gad7_q2',
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by not being able to stop or control worrying?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'sort_order' => 2,
            ],
        ];

        // PHQ-9 Depression Assessment Questions (first 2 for demo)
        $phq9Questions = [
            [
                'quiz_type' => 'phq9',
                'quiz_version' => '1.0',
                'question_number' => 1,
                'question_id' => 'phq9_q1',
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by little interest or pleasure in doing things?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'sort_order' => 1,
            ],
            [
                'quiz_type' => 'phq9',
                'quiz_version' => '1.0',
                'question_number' => 2,
                'question_id' => 'phq9_q2',
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by feeling down, depressed, or hopeless?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'sort_order' => 2,
            ],
        ];

        // Insert all questions
        foreach (array_merge($gad7Questions, $phq9Questions) as $question) {
            QuizQuestion::updateOrCreate(
                [
                    'quiz_type' => $question['quiz_type'],
                    'question_number' => $question['question_number'],
                    'quiz_version' => $question['quiz_version'],
                ],
                $question
            );
        }

        $this->command->info('Simple quiz types seeded successfully!');
    }
}
