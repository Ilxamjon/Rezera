<?php

return [
    'types' => [
        'reservation_created' => [
            'title' => 'Bron jaratıldı',
            'body' => ':business_name dagi :reservation_number broningiz jaratıldı.',
        ],
        'reservation_confirmed' => [
            'title' => 'Bron tastıyqlandı',
            'body' => ':business_name dagi :reservation_number broningiz :date kúni :start_time da tastıyqlandı.',
        ],
        'reservation_cancelled' => [
            'title' => 'Bron biykar etildi',
            'body' => ':business_name dagi :reservation_number broningiz biykar etildi.',
        ],
        'reservation_completed' => [
            'title' => 'Bron tamamlandı',
            'body' => ':business_name dagi :reservation_number broningiz tamamlandı.',
        ],
        'reservation_no_show' => [
            'title' => 'Kelmedi',
            'body' => ':business_name dagi :reservation_number bron kelmedi dep belgilendi.',
        ],
        'reservation_reminder' => [
            'title' => 'Bron esletpesi',
            'body' => 'Esletpe: :business_name dagi :reservation_number :date kúni :start_time da baslanadı.',
        ],
        'payment_created' => [
            'title' => 'Tólew jaratıldı',
            'body' => ':reservation_number bron ushın :payment_number tólewi jaratıldı.',
        ],
        'payment_processing' => [
            'title' => 'Tólew qayta işlenbekte',
            'body' => ':payment_number tólewi qayta işlenbekte.',
        ],
        'payment_succeeded' => [
            'title' => 'Tólew tabıslı',
            'body' => ':payment_number tólewi (:amount :currency) tabıslı orınlandı.',
        ],
        'payment_failed' => [
            'title' => 'Tólew orınlanbadı',
            'body' => ':payment_number tólewin tamamlap bolmadı.',
        ],
        'payment_refunded' => [
            'title' => 'Tólew qaytarıldı',
            'body' => ':payment_number tólewi qaytarıldı.',
        ],
        'business_booking_received' => [
            'title' => 'Jańa bron',
            'body' => ':resource_name ushın jańa bron :reservation_number — :date, :start_time.',
        ],
        'business_booking_cancelled' => [
            'title' => 'Bron biykar etildi',
            'body' => ':reservation_number broni klient tárepinen biykar etildi.',
        ],
        'availability_alert' => [
            'title' => 'Saqlanǵan izlew boyınsha slot bar',
            'body' => ':business_name dagi :resource_name :date küni :start_time–:end_time aralıǵında bar.',
        ],
        'system_notification' => [
            'title' => 'Sistema xabarı',
            'body' => 'Sizde jańa sistema xabarı bar.',
        ],
    ],
];
