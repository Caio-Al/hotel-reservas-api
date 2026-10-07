<?php

return [
    'service_fee_percent' => (float) env('HOTEL_SERVICE_FEE_PERCENT', 10),

    'max_nights' => (int) env('HOTEL_MAX_NIGHTS', 60),
];
