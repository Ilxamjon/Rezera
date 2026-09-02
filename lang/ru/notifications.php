<?php

return [
    'types' => [
        'reservation_created' => [
            'title' => 'Бронирование создано',
            'body' => 'Ваше бронирование :reservation_number в :business_name создано.',
        ],
        'reservation_confirmed' => [
            'title' => 'Бронирование подтверждено',
            'body' => 'Ваше бронирование :reservation_number в :business_name подтверждено на :date в :start_time.',
        ],
        'reservation_cancelled' => [
            'title' => 'Бронирование отменено',
            'body' => 'Ваше бронирование :reservation_number в :business_name отменено.',
        ],
        'reservation_completed' => [
            'title' => 'Бронирование завершено',
            'body' => 'Ваше бронирование :reservation_number в :business_name завершено.',
        ],
        'reservation_no_show' => [
            'title' => 'Неявка',
            'body' => 'Бронирование :reservation_number в :business_name отмечено как неявка.',
        ],
        'reservation_reminder' => [
            'title' => 'Напоминание о бронировании',
            'body' => 'Напоминание: :reservation_number в :business_name начинается :date в :start_time.',
        ],
        'payment_created' => [
            'title' => 'Платёж создан',
            'body' => 'Платёж :payment_number для бронирования :reservation_number создан.',
        ],
        'payment_processing' => [
            'title' => 'Платёж обрабатывается',
            'body' => 'Платёж :payment_number обрабатывается.',
        ],
        'payment_succeeded' => [
            'title' => 'Платёж успешен',
            'body' => 'Платёж :payment_number на сумму :amount :currency успешно выполнен.',
        ],
        'payment_failed' => [
            'title' => 'Платёж не выполнен',
            'body' => 'Платёж :payment_number не удалось завершить.',
        ],
        'payment_refunded' => [
            'title' => 'Платёж возвращён',
            'body' => 'Платёж :payment_number возвращён.',
        ],
        'business_booking_received' => [
            'title' => 'Новое бронирование',
            'body' => 'Новое бронирование :reservation_number для :resource_name на :date в :start_time.',
        ],
        'business_booking_cancelled' => [
            'title' => 'Бронирование отменено',
            'body' => 'Бронирование :reservation_number отменено клиентом.',
        ],
        'availability_alert' => [
            'title' => 'Появился доступный слот',
            'body' => 'По вашему сохранённому поиску :resource_name в :business_name доступен :date с :start_time до :end_time.',
        ],
        'system_notification' => [
            'title' => 'Системное уведомление',
            'body' => 'У вас новое системное уведомление.',
        ],
    ],
];
