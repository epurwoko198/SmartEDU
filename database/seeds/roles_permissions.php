<?php
declare(strict_types=1);

/**
 * Permission catalog and default tenant roles.
 * Permissions follow the resource.action pattern.
 */
return [
    'permissions' => [
        'dashboard.view' => 'Melihat dashboard sekolah',
        'students.view' => 'Melihat data siswa',
        'students.create' => 'Menambah data siswa',
        'students.update' => 'Mengubah data siswa',
        'students.delete' => 'Menghapus data siswa',
        'guardians.view' => 'Melihat data orang tua/wali',
        'guardians.manage' => 'Mengelola data orang tua/wali',
        'employees.view' => 'Melihat data guru dan pegawai',
        'employees.manage' => 'Mengelola data guru dan pegawai',
        'classes.view' => 'Melihat kelas dan rombel',
        'classes.manage' => 'Mengelola kelas dan rombel',
        'subjects.view' => 'Melihat mata pelajaran',
        'subjects.manage' => 'Mengelola mata pelajaran',
        'academic_years.manage' => 'Mengelola tahun ajaran',
        'ppdb.view' => 'Melihat data PPDB',
        'ppdb.manage' => 'Mengelola pendaftaran PPDB',
        'ppdb.verify' => 'Memverifikasi berkas PPDB',
        'ppdb.announce' => 'Mengumumkan hasil seleksi',
        'academic.view' => 'Melihat data akademik',
        'academic.manage' => 'Mengelola jadwal dan pembelajaran',
        'attendance.manage' => 'Mengelola absensi',
        'grades.manage' => 'Mengelola penilaian',
        'report_cards.issue' => 'Menerbitkan rapor',
        'finance.view' => 'Melihat data keuangan',
        'finance.manage' => 'Mengelola komponen biaya dan tagihan',
        'finance.payment.post' => 'Mencatat pembayaran',
        'finance.report' => 'Mengakses laporan keuangan',
        'letters.view' => 'Melihat persuratan',
        'letters.create' => 'Membuat surat',
        'letters.approve' => 'Menyetujui surat',
        'letters.archive' => 'Mengelola arsip dokumen',
        'certificates.view' => 'Melihat sertifikat dan kelulusan',
        'certificates.issue' => 'Menerbitkan sertifikat',
        'communication.view' => 'Melihat pengumuman dan komunikasi',
        'communication.send' => 'Mengirim pengumuman',
        'users.view' => 'Melihat pengguna',
        'users.manage' => 'Mengelola pengguna',
        'roles.manage' => 'Mengelola role dan permission',
        'audit.view' => 'Melihat audit trail',
        'settings.manage' => 'Mengelola pengaturan sekolah',
        'ai.use' => 'Menggunakan AI Assistant',
    ],

    'roles' => [
        'school_super_admin' => [
            'name' => 'School Super Admin',
            'description' => 'Administrator sekolah dengan akses penuh pada tenant.',
            'permissions' => ['*'],
        ],
        'principal' => [
            'name' => 'Kepala Sekolah',
            'description' => 'Melihat dashboard dan menyetujui proses sesuai delegasi.',
            'permissions' => [
                'dashboard.view', 'students.view', 'employees.view', 'classes.view', 'subjects.view',
                'ppdb.view', 'academic.view', 'finance.view', 'letters.view', 'letters.approve',
                'certificates.view', 'certificates.issue', 'communication.view', 'audit.view',
            ],
        ],
        'curriculum' => [
            'name' => 'Kurikulum',
            'description' => 'Mengelola kurikulum, jadwal, dan penilaian.',
            'permissions' => [
                'dashboard.view', 'students.view', 'classes.view', 'classes.manage', 'subjects.view',
                'subjects.manage', 'academic.view', 'academic.manage', 'grades.manage', 'report_cards.issue',
                'attendance.manage',
            ],
        ],
        'teacher' => [
            'name' => 'Guru',
            'description' => 'Mengakses kelas, absensi, jurnal, dan penilaian sesuai penugasan.',
            'permissions' => [
                'dashboard.view', 'students.view', 'classes.view', 'subjects.view',
                'academic.view', 'attendance.manage', 'grades.manage', 'letters.view', 'communication.view',
            ],
        ],
        'treasurer' => [
            'name' => 'Bendahara',
            'description' => 'Mengelola tagihan, pembayaran, dan laporan keuangan.',
            'permissions' => [
                'dashboard.view', 'students.view', 'finance.view', 'finance.manage',
                'finance.payment.post', 'finance.report', 'communication.view',
            ],
        ],
        'cashier' => [
            'name' => 'Kasir',
            'description' => 'Mencatat pembayaran yang diterima.',
            'permissions' => ['dashboard.view', 'students.view', 'finance.view', 'finance.payment.post'],
        ],
        'tu_operator' => [
            'name' => 'Operator Tata Usaha',
            'description' => 'Mengelola data administratif dan persuratan.',
            'permissions' => [
                'dashboard.view', 'students.view', 'students.create', 'students.update',
                'guardians.view', 'guardians.manage', 'employees.view', 'classes.view',
                'ppdb.view', 'ppdb.manage', 'ppdb.verify', 'letters.view', 'letters.create',
                'letters.archive', 'certificates.view', 'communication.view',
            ],
        ],
        'parent' => [
            'name' => 'Orang Tua / Wali',
            'description' => 'Hanya melihat data anak yang terhubung dengan akun.',
            'permissions' => ['dashboard.view', 'students.view', 'academic.view', 'finance.view', 'communication.view'],
        ],
        'student' => [
            'name' => 'Siswa',
            'description' => 'Melihat data akademik dan tagihan milik sendiri.',
            'permissions' => ['dashboard.view', 'academic.view', 'finance.view', 'communication.view'],
        ],
    ],
];
