<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\QuizQuestion;

class QuizQuestionsSeeder extends Seeder
{
    public function run(): void
    {
        $questions = [
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 1,
                'question_id' => 'audit_q1',
                'question_text' => 'How often do you have a drink containing alcohol?',
                'options' => [
                    ['label' => 'Never', 'score' => 0],
                    ['label' => 'Monthly or less', 'score' => 1],
                    ['label' => '2 to 4 times per month', 'score' => 2],
                    ['label' => '2 to 3 times per week', 'score' => 3],
                    ['label' => '4 times or more per week', 'score' => 4],
                ],
                'sort_order' => 1,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 2,
                'question_id' => 'audit_q2',
                'question_text' => 'How many units of alcohol do you drink on a typical day when you are drinking?',
                'options' => [
                    ['label' => '0 to 2', 'score' => 0],
                    ['label' => '3 to 4', 'score' => 1],
                    ['label' => '5 to 6', 'score' => 2],
                    ['label' => '7 to 9', 'score' => 3],
                    ['label' => '10 or more', 'score' => 4],
                ],
                'help_text' => 'UNIT GUIDE: A unit measures how much alcohol is in your drink. Quick Guide: 1 beer = 2 units, 1 glass of wine = 2-3 units, 1 shot = 1 unit, 1 cocktail = 1-3 units (depends on shots inside).',
                'sort_order' => 2,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 3,
                'question_id' => 'audit_q3',
                'question_text' => 'How often have you had 6 or more units (if female), or 8 or more (if male), on a single occasion in the last year?',
                'options' => [
                    ['label' => 'Never', 'score' => 0],
                    ['label' => 'Less than monthly', 'score' => 1],
                    ['label' => 'Monthly', 'score' => 2],
                    ['label' => 'Weekly', 'score' => 3],
                    ['label' => 'Daily or almost daily', 'score' => 4],
                ],
                'sort_order' => 3,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 4,
                'question_id' => 'audit_q4',
                'question_text' => 'How often during the last year have you found that you were not able to stop drinking once you had started?',
                'options' => [
                    ['label' => 'Never', 'score' => 0],
                    ['label' => 'Less than monthly', 'score' => 1],
                    ['label' => 'Monthly', 'score' => 2],
                    ['label' => 'Weekly', 'score' => 3],
                    ['label' => 'Daily or almost daily', 'score' => 4],
                ],
                'sort_order' => 4,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 5,
                'question_id' => 'audit_q5',
                'question_text' => 'How often during the last year have you failed to do what was normally expected from you because of your drinking?',
                'options' => [
                    ['label' => 'Never', 'score' => 0],
                    ['label' => 'Less than monthly', 'score' => 1],
                    ['label' => 'Monthly', 'score' => 2],
                    ['label' => 'Weekly', 'score' => 3],
                    ['label' => 'Daily or almost daily', 'score' => 4],
                ],
                'sort_order' => 5,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 6,
                'question_id' => 'audit_q6',
                'question_text' => 'How often during the last year have you needed a drink in the morning to get yourself going after a heavy drinking session?',
                'options' => [
                    ['label' => 'Never', 'score' => 0],
                    ['label' => 'Less than monthly', 'score' => 1],
                    ['label' => 'Monthly', 'score' => 2],
                    ['label' => 'Weekly', 'score' => 3],
                    ['label' => 'Daily or almost daily', 'score' => 4],
                ],
                'sort_order' => 6,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 7,
                'question_id' => 'audit_q7',
                'question_text' => 'How often during the last year have you had a feeling of guilt or remorse after drinking?',
                'options' => [
                    ['label' => 'Never', 'score' => 0],
                    ['label' => 'Less than monthly', 'score' => 1],
                    ['label' => 'Monthly', 'score' => 2],
                    ['label' => 'Weekly', 'score' => 3],
                    ['label' => 'Daily or almost daily', 'score' => 4],
                ],
                'sort_order' => 7,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 8,
                'question_id' => 'audit_q8',
                'question_text' => 'How often during the last year have you been unable to remember what happened the night before because you had been drinking?',
                'options' => [
                    ['label' => 'Never', 'score' => 0],
                    ['label' => 'Less than monthly', 'score' => 1],
                    ['label' => 'Monthly', 'score' => 2],
                    ['label' => 'Weekly', 'score' => 3],
                    ['label' => 'Daily or almost daily', 'score' => 4],
                ],
                'sort_order' => 8,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 9,
                'question_id' => 'audit_q9',
                'question_text' => 'Have you or somebody else been injured as a result of your drinking?',
                'options' => [
                    ['label' => 'No', 'score' => 0],
                    ['label' => 'Yes, but not in the last year', 'score' => 2],
                    ['label' => 'Yes, during the last year', 'score' => 4],
                ],
                'sort_order' => 9,
            ],
            [
                'quiz_type' => 'audit',
                'quiz_version' => '1.0',
                'question_number' => 10,
                'question_id' => 'audit_q10',
                'question_text' => 'Has a relative, friend, or health worker been concerned about your drinking or suggested that you cut down?',
                'options' => [
                    ['label' => 'No', 'score' => 0],
                    ['label' => 'Yes, but not in the last year', 'score' => 2],
                    ['label' => 'Yes, during the last year', 'score' => 4],
                ],
                'sort_order' => 10,
            ],
        ];

        foreach ($questions as $questionData) {
            QuizQuestion::updateOrCreate(
                [
                    'quiz_type' => $questionData['quiz_type'],
                    'question_id' => $questionData['question_id'],
                ],
                $questionData
            );
        }
    }
}
