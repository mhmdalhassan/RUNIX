@props(['status'])

{{--
    Semantic status colors (§3/§9): pending=neutral, available=info,
    accepted=indigo, picked_up=warning, on_the_way=violet, delivered/
    online/active/settled=success, cancelled/offline/inactive/waived=neutral,
    failed=danger. Also covers the driver/customer/user is_active/is_online
    booleans and Order's payment_status values, so one component covers
    every status pill in the app. Accepts either a plain string or a
    backed enum instance (OrderStatus, PaymentStatus, ...) directly.
--}}

@php
    $map = [
        'pending' => ['tone' => 'neutral', 'label' => 'Pending'],
        'available' => ['tone' => 'info', 'label' => 'Available'],
        'accepted' => ['tone' => 'indigo', 'label' => 'Accepted'],
        'picked_up' => ['tone' => 'warning', 'label' => 'Picked Up'],
        'on_the_way' => ['tone' => 'violet', 'label' => 'On the Way'],
        'delivered' => ['tone' => 'success', 'label' => 'Delivered'],
        'cancelled' => ['tone' => 'neutral', 'label' => 'Cancelled'],
        'failed' => ['tone' => 'danger', 'label' => 'Failed'],
        'online' => ['tone' => 'success', 'label' => 'Online'],
        'offline' => ['tone' => 'neutral', 'label' => 'Offline'],
        'active' => ['tone' => 'success', 'label' => 'Active'],
        'inactive' => ['tone' => 'neutral', 'label' => 'Inactive'],
        // Order::payment_status (§19)
        'collected' => ['tone' => 'info', 'label' => 'Collected'],
        'remitted' => ['tone' => 'indigo', 'label' => 'Remitted'],
        'settled' => ['tone' => 'success', 'label' => 'Settled'],
        'waived' => ['tone' => 'neutral', 'label' => 'Waived'],
        // OrderOffer::result (Phase 4)
        'rejected' => ['tone' => 'warning', 'label' => 'Rejected'],
        'expired' => ['tone' => 'neutral', 'label' => 'Expired'],
    ];

    $key = match (true) {
        $status instanceof \BackedEnum => $status->value,
        is_string($status) => strtolower($status),
        default => 'pending',
    };
    $entry = $map[$key] ?? ['tone' => 'neutral', 'label' => ucwords(str_replace('_', ' ', $key))];
@endphp

<x-badge :tone="$entry['tone']" {{ $attributes }}>{{ __($entry['label']) }}</x-badge>
