<?php

declare(strict_types=1);

return [
    // Days an emailed invitation link stays valid.
    'invitation_days' => (int) env('USERS_INVITATION_DAYS', 7),
];
