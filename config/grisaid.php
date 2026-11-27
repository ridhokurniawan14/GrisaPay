<?php

return [

    /*
    | Koneksi GrisaPay ke API GrisaID (data master siswa, kelas, tahun ajaran).
    | Nilai rahasia diisi di file .env, JANGAN ditulis di sini dan jangan di-commit.
    */

    'url' => env('GRISAID_URL', 'http://grisaid.test'),

    'client_id' => env('GRISAID_CLIENT_ID'),

    'client_secret' => env('GRISAID_CLIENT_SECRET'),

    'scopes' => ['students:read', 'classes:read', 'academic-years:read'],

    // Batas waktu satu permintaan, dalam detik.
    'timeout' => (int) env('GRISAID_TIMEOUT', 15),

    'user_client_ids' => env('GRISAID_USER_CLIENT_IDS', ''),
];
