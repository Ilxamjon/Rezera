<?php

namespace App\Domain\Platform\Enums;

enum PlatformPermission: string
{
    case UsersView = 'users.view';
    case UsersManage = 'users.manage';
    case BusinessesView = 'businesses.view';
    case BusinessesManage = 'businesses.manage';
    case ReservationsView = 'reservations.view';
    case ReservationsManage = 'reservations.manage';
    case PaymentsView = 'payments.view';
    case CategoriesManage = 'categories.manage';
    case SettingsManage = 'settings.manage';
    case AuditLogsView = 'audit_logs.view';
    case NotificationsView = 'notifications.view';
    case ReviewsView = 'reviews.view';
    case ReviewsManage = 'reviews.manage';
    case PromotionsView = 'promotions.view';
    case PromotionsManage = 'promotions.manage';
    case PricingView = 'pricing.view';
    case PricingManage = 'pricing.manage';
    case SubscriptionsView = 'subscriptions.view';
    case SubscriptionsManage = 'subscriptions.manage';
}
