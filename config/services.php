<?php

return [
    'quran_api' => [
        'base_url' => env('QURAN_API_BASE_URL', 'https://api.quran.com/api/v4'),
        'translation_id' => (int) env('QURAN_TRANSLATION_ID', 33), // 33 = Kemenag RI (Indonesian Ministry of Religious Affairs)
        'default_reciter_id' => (int) env('QURAN_DEFAULT_RECITER_ID', 7),
        'timeout' => (int) env('QURAN_API_TIMEOUT', 15),
        'reciters' => [
            7 => [
                'id' => 7,
                'name' => 'Mishary Rashid Alafasy',
                'style' => 'Murattal',
                'everyayah_folder' => 'Alafasy_128kbps',
            ],
            6 => [
                'id' => 6,
                'name' => 'Mahmoud Khalil Al-Husary',
                'style' => 'Murattal (Tajwid Jelas)',
                'everyayah_folder' => 'Husary_128kbps',
            ],
            2 => [
                'id' => 2,
                'name' => 'AbdulBaset AbdulSamad',
                'style' => 'Murattal',
                'everyayah_folder' => 'Abdul_Basit_Murattal_192kbps',
            ],
            3 => [
                'id' => 3,
                'name' => 'Abdurrahmaan As-Sudais',
                'style' => 'Haramain',
                'everyayah_folder' => 'Abdurrahmaan_As-Sudais_192kbps',
            ],
        ],
    ],
];
