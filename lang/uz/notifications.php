<?php

return [
    'types' => [
        'reservation_created' => [
            'title' => 'Bron qilindi',
            'body' => ':business_name dagi :reservation_number broningiz yaratildi.',
        ],
        'reservation_confirmed' => [
            'title' => 'Bron tasdiqlandi',
            'body' => ':business_name dagi :reservation_number broningiz :date kuni :start_time da tasdiqlandi.',
        ],
        'reservation_cancelled' => [
            'title' => 'Bron bekor qilindi',
            'body' => ':business_name dagi :reservation_number broningiz bekor qilindi.',
        ],
        'reservation_completed' => [
            'title' => 'Bron yakunlandi',
            'body' => ':business_name dagi :reservation_number broningiz yakunlandi.',
        ],
        'reservation_no_show' => [
            'title' => 'Kelmaslik',
            'body' => ':business_name dagi :reservation_number bron kelmaslik deb belgilandi.',
        ],
        'reservation_reminder' => [
            'title' => 'Bron eslatmasi',
            'body' => 'Eslatma: :business_name dagi :reservation_number :date kuni :start_time da boshlanadi.',
        ],
        'payment_created' => [
            'title' => 'Toʻlov yaratildi',
            'body' => ':reservation_number bron uchun :payment_number toʻlovi yaratildi.',
        ],
        'payment_processing' => [
            'title' => 'Toʻlov qayta ishlanmoqda',
            'body' => ':payment_number toʻlovi qayta ishlanmoqda.',
        ],
        'payment_succeeded' => [
            'title' => 'Toʻlov muvaffaqiyatli',
            'body' => ':payment_number toʻlovi (:amount :currency) muvaffaqiyatli amalga oshirildi.',
        ],
        'payment_failed' => [
            'title' => 'Toʻlov amalga oshmadi',
            'body' => ':payment_number toʻlovini yakunlab boʻlmadi.',
        ],
        'payment_refunded' => [
            'title' => 'Toʻlov qaytarildi',
            'body' => ':payment_number toʻlovi qaytarildi.',
        ],
        'business_booking_received' => [
            'title' => 'Yangi bron',
            'body' => ':resource_name uchun yangi bron :reservation_number — :date, :start_time.',
        ],
        'business_booking_cancelled' => [
            'title' => 'Bron bekor qilindi',
            'body' => ':reservation_number broni mijoz tomonidan bekor qilindi.',
        ],
        'availability_alert' => [
            'title' => 'Mos vaqt topildi',
            'body' => 'Siz saqlagan qidiruv bo‘yicha :business_name dagi :resource_name :date kuni :start_time–:end_time oralig‘ida mavjud.',
        ],
        'system_notification' => [
            'title' => 'Tizim bildirishnomasi',
            'body' => 'Sizda yangi tizim bildirishnomasi bor.',
        ],
    ],
];
