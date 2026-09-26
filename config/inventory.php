<?php

return [
    'default_currency' => env('INVENTORY_DEFAULT_CURRENCY', 'PHP'),

    'adjustment_dual_approval_threshold' => 25000.00,

    'replenishment' => [
        // System-level planning assumptions. Item-specific demand, lead time,
        // safety stock, reorder point, EOQ, and cost remain database-owned.
        'service_factor' => 1.645,
        'demand_variability_rate' => 0.25,
        'lead_time_variability_rate' => 0.20,
        'order_cost' => 500.00,
        'holding_cost_rate' => 0.20,
    ],
];
