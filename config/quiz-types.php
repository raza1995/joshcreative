<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Quiz Type Configurations
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for different quiz types.
    | Each quiz type can have its own scoring system, questions, and analytics.
    |
    */

    'types' => [
        'audit' => [
            'name' => 'WHO AUDIT Alcohol Assessment',
            'description' => 'World Health Organization Alcohol Use Disorders Identification Test',
            'version' => '1.0',
            'max_score' => 40,
            'question_count' => 10,
            'scoring' => [
                'type' => 'additive', // additive, weighted, categorical
                'risk_levels' => [
                    'low' => ['min' => 0, 'max' => 7, 'color' => '#059669', 'label' => 'Low Risk'],
                    'increasing' => ['min' => 8, 'max' => 15, 'color' => '#ca8a04', 'label' => 'Increasing Risk'],
                    'higher' => ['min' => 16, 'max' => 19, 'color' => '#dc2626', 'label' => 'Higher Risk'],
                    'dependence' => ['min' => 20, 'max' => 40, 'color' => '#7f1d1d', 'label' => 'Possible Dependence'],
                ],
            ],
            'demographics' => [
                'required' => ['age', 'sex'],
                'optional' => ['email'],
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

        'gad7' => [
            'name' => 'GAD-7 Anxiety Assessment',
            'description' => 'Generalized Anxiety Disorder 7-item scale',
            'version' => '1.0',
            'max_score' => 21,
            'question_count' => 7,
            'scoring' => [
                'type' => 'additive',
                'risk_levels' => [
                    'minimal' => ['min' => 0, 'max' => 4, 'color' => '#059669', 'label' => 'Minimal Anxiety'],
                    'mild' => ['min' => 5, 'max' => 9, 'color' => '#ca8a04', 'label' => 'Mild Anxiety'],
                    'moderate' => ['min' => 10, 'max' => 14, 'color' => '#dc2626', 'label' => 'Moderate Anxiety'],
                    'severe' => ['min' => 15, 'max' => 21, 'color' => '#7f1d1d', 'label' => 'Severe Anxiety'],
                ],
            ],
            'demographics' => [
                'required' => ['age', 'sex'],
                'optional' => ['email'],
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

        'phq9' => [
            'name' => 'PHQ-9 Depression Assessment',
            'description' => 'Patient Health Questionnaire-9 for depression screening',
            'version' => '1.0',
            'max_score' => 27,
            'question_count' => 9,
            'scoring' => [
                'type' => 'additive',
                'risk_levels' => [
                    'minimal' => ['min' => 0, 'max' => 4, 'color' => '#059669', 'label' => 'Minimal Depression'],
                    'mild' => ['min' => 5, 'max' => 9, 'color' => '#ca8a04', 'label' => 'Mild Depression'],
                    'moderate' => ['min' => 10, 'max' => 14, 'color' => '#dc2626', 'label' => 'Moderate Depression'],
                    'moderately_severe' => ['min' => 15, 'max' => 19, 'color' => '#991b1b', 'label' => 'Moderately Severe'],
                    'severe' => ['min' => 20, 'max' => 27, 'color' => '#7f1d1d', 'label' => 'Severe Depression'],
                ],
            ],
            'demographics' => [
                'required' => ['age', 'sex'],
                'optional' => ['email'],
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
            'demographics' => [
                'required' => ['age'],
                'optional' => ['email', 'sex'],
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

        'custom' => [
            'name' => 'Custom Assessment',
            'description' => 'Configurable custom assessment tool',
            'version' => '1.0',
            'max_score' => null, // Will be calculated dynamically
            'question_count' => null, // Will be calculated dynamically
            'scoring' => [
                'type' => 'configurable', // Can be additive, weighted, or categorical
                'risk_levels' => [], // Will be configured per instance
            ],
            'demographics' => [
                'required' => [],
                'optional' => ['email'],
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
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Quiz Type
    |--------------------------------------------------------------------------
    |
    | The default quiz type to use when none is specified.
    |
    */
    'default' => 'audit',

    /*
    |--------------------------------------------------------------------------
    | Analytics Configuration
    |--------------------------------------------------------------------------
    |
    | Global analytics settings that apply to all quiz types.
    |
    */
    'analytics' => [
        'retention_days' => 365, // How long to keep analytics data
        'aggregate_daily' => true, // Whether to create daily aggregates
        'track_ip_addresses' => false, // Whether to store IP addresses
        'anonymize_after_days' => 30, // Anonymize personal data after X days
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for data export functionality.
    |
    */
    'export' => [
        'formats' => ['csv', 'json', 'xlsx'],
        'max_records' => 10000,
        'include_analytics' => true,
        'include_demographics' => true,
    ],
];
