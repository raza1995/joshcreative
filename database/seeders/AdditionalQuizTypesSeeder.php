<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuizQuestion;

class AdditionalQuizTypesSeeder extends Seeder
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
                'question_id' => 2,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by not being able to stop or control worrying?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'gad7',
                'question_id' => 3,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by worrying too much about different things?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'gad7',
                'question_id' => 4,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by trouble relaxing?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'gad7',
                'question_id' => 5,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by being so restless that it\'s hard to sit still?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'gad7',
                'question_id' => 6,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by becoming easily annoyed or irritable?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'gad7',
                'question_id' => 7,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by feeling afraid as if something awful might happen?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
        ];

        // PHQ-9 Depression Assessment Questions
        $phq9Questions = [
            [
                'quiz_type' => 'phq9',
                'question_id' => 1,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by little interest or pleasure in doing things?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'phq9',
                'question_id' => 2,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by feeling down, depressed, or hopeless?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'phq9',
                'question_id' => 3,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by trouble falling or staying asleep, or sleeping too much?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'phq9',
                'question_id' => 4,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by feeling tired or having little energy?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'phq9',
                'question_id' => 5,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by poor appetite or overeating?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'phq9',
                'question_id' => 6,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by feeling bad about yourself or that you are a failure or have let yourself or your family down?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'phq9',
                'question_id' => 7,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by trouble concentrating on things, such as reading the newspaper or watching television?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'phq9',
                'question_id' => 8,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by moving or speaking so slowly that other people could have noticed? Or the opposite - being so fidgety or restless that you have been moving around a lot more than usual?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
            [
                'quiz_type' => 'phq9',
                'question_id' => 9,
                'question_text' => 'Over the last 2 weeks, how often have you been bothered by thoughts that you would be better off dead, or of hurting yourself?',
                'options' => [
                    ['label' => 'Not at all', 'score' => 0],
                    ['label' => 'Several days', 'score' => 1],
                    ['label' => 'More than half the days', 'score' => 2],
                    ['label' => 'Nearly every day', 'score' => 3],
                ],
                'quiz_version' => '1.0',
            ],
        ];

        // Insert all questions
        foreach (array_merge($gad7Questions, $phq9Questions) as $question) {
            QuizQuestion::updateOrCreate(
                [
                    'quiz_type' => $question['quiz_type'],
                    'question_id' => $question['question_id'],
                    'quiz_version' => $question['quiz_version'],
                ],
                $question
            );
        }

        $this->command->info('Additional quiz types seeded successfully!');
    }
}
