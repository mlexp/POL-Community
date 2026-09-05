<?php

return [
    'image_hard_max_bytes' => (int) env('IMAGE_HARD_MAX_BYTES', 10485760),
    'image_max_pixels' => 8000000,
    'image_max_dimension' => 6000,
    'thumbnail_dimension' => 480,
    'feed_max_bytes' => 2097152,
];
