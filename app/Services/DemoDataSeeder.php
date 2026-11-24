<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Contracts\ConnectionInterface;

/**
 * Seeds synthetic school data for demos. Never use real student data here.
 */
final class DemoDataSeeder
{
    public function seed(ConnectionInterface $db): array
    {
        $existing = $db->selectOne('SELECT COUNT(*) AS total FROM students');
        if ((int) ($existing['total'] ?? 0) > 0) {
            return ['skipped' => true, 'students' => (int) $existing['total']];
        }

        $year = $db->selectOne('SELECT id, name FROM academic_years ORDER BY id ASC LIMIT 1');
        $yearId = $year !== null ? (int) $year['id'] : null;

        // Staff accounts and teachers.
        $staff = [
            ['Ibu Ratna Kusuma', 'guru@demo.edusmart.local', 'Guru Matematika', 'teacher'],
            ['Pak Budi Santoso', 'bendahara@demo.edusmart.local', 'Bendahara Sekolah', 'treasurer'],
            ['Ibu Sari Wulandari', 'tu@demo.edusmart.local', 'Operator Tata Usaha', 'tu_operator'],
        ];

        $roleIds = [];
        foreach ($db->select('SELECT id, code FROM roles') as $role) {
            $roleIds[(string) $role['code']] = (int) $role['id'];
        }

        $userIdByEmail = [];
        foreach ($staff as [$name, $email, $position, $roleCode]) {
            $existingUser = $db->selectOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]);
            $userId = $existingUser !== null
                ? (int) $existingUser['id']
                : $db->insert(
                    'INSERT INTO users (name, email, password_hash, is_active, created_at) VALUES (?, ?, ?, 1, ?)',
                    [$name, $email, password_hash('DemoPass!123456', PASSWORD_DEFAULT), date('Y-m-d H:i:s')]
                );
            $userIdByEmail[$email] = $userId;

            if (isset($roleIds[$roleCode])) {
                $db->execute('INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)', [$userId, $roleIds[$roleCode]]);
            }

            $exists = $db->selectOne('SELECT id FROM employees WHERE email = ? LIMIT 1', [$email]);
            if ($exists === null) {
                $db->insert(
                    'INSERT INTO employees (nip, full_name, position, employment_type, email, phone, is_active, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, 1, ?)',
                    ['1987' . random_int(100000, 999999), $name, $position, 'tetap', $email, '0812' . random_int(10000000, 99999999), date('Y-m-d H:i:s')]
                );
            }
        }

        // Classes.
        $classIds = [];
        foreach (['7A', '7B', '8A', '8B', '9A', '9B'] as $className) {
            $classIds[$className] = $db->insert(
                'INSERT INTO classes (name, level, academic_year_id, capacity, created_at) VALUES (?, ?, ?, 32, ?)',
                [$className, substr($className, 0, 1), $yearId, date('Y-m-d H:i:s')]
            );
        }

        // Subjects.
        $subjects = [
            ['MTK', 'Matematika', 4],
            ['BIN', 'Bahasa Indonesia', 4],
            ['BIG', 'Bahasa Inggris', 3],
            ['IPA', 'Ilmu Pengetahuan Alam', 4],
            ['IPS', 'Ilmu Pengetahuan Sosial', 3],
            ['PJOK', 'Pendidikan Jasmani', 2],
        ];
        foreach ($subjects as [$code, $name, $credits]) {
            $db->execute(
                'INSERT IGNORE INTO subjects (code, name, level, credits, created_at) VALUES (?, ?, ?, ?, ?)',
                [$code, $name, 'SMP', $credits, date('Y-m-d H:i:s')]
            );
        }

        // Students with synthetic names.
        $names = [
            'Aditya Pratama', 'Bunga Lestari', 'Citra Maharani', 'Dimas Saputra',
            'Eka Nuraini', 'Fajar Ramadhan', 'Gita Puspita', 'Hendra Wijaya',
            'Indah Permata', 'Joko Susilo', 'Kartika Sari', 'Lukman Hakim',
            'Maya Anggraini', 'Naufal Fadhillah', 'Oktavia Rahma', 'Putra Mahendra',
            'Qonita Azzahra', 'Rizky Hidayat', 'Sinta Dewi', 'Taufik Hidayat',
            'Umi Kalsum', 'Vina Oktaviani', 'Wahyu Setiawan', 'Yuni Astuti',
        ];
        $classes = array_keys($classIds);
        $studentIds = [];
        $admissionYear = (int) date('Y') - 1;

        foreach ($names as $index => $name) {
            $className = $classes[$index % count($classes)];
            $gender = $index % 2 === 0 ? 'L' : 'P';
            $studentIds[] = $db->insert(
                'INSERT INTO students (nis, nisn, full_name, gender, birth_date, class_id, guardian_name,
                    guardian_phone, status, admission_year, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'S' . date('y') . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    (string) (1000000000 + $index),
                    $name,
                    $gender,
                    date('Y-m-d', strtotime('-' . random_int(13, 15) . ' years')),
                    $classIds[$className],
                    'Orang Tua ' . explode(' ', $name)[0],
                    '0813' . random_int(10000000, 99999999),
                    'active',
                    $admissionYear,
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s'),
                ]
            );
        }

        // Fee components and invoices.
        $fees = [
            ['SPP', 'SPP Bulanan', 350000, 'bulanan', 1],
            ['PANGKAL', 'Uang Pangkal', 2500000, 'sekali', 0],
            ['KEGIATAN', 'Biaya Kegiatan Semester', 600000, 'semester', 1],
            ['SERAGAM', 'Seragam Sekolah', 850000, 'sekali', 0],
        ];
        $feeIds = [];
        foreach ($fees as [$code, $name, $amount, $period, $recurring]) {
            $db->execute(
                'INSERT IGNORE INTO fee_components (code, name, amount, period, is_recurring, created_at)
                 VALUES (?, ?, ?, ?, ?, ?)',
                [$code, $name, $amount, $period, $recurring, date('Y-m-d H:i:s')]
            );
            $row = $db->selectOne('SELECT id FROM fee_components WHERE code = ? LIMIT 1', [$code]);
            if ($row !== null) {
                $feeIds[$code] = (int) $row['id'];
            }
        }

        $invoiceNumber = 1;
        foreach ($studentIds as $position => $studentId) {
            $amount = 350000.0;
            $paid = $position % 3 === 0 ? 0.0 : $amount;
            $db->insert(
                'INSERT INTO invoices (student_id, number, amount, paid_amount, status, due_date, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?)',
                [
                    $studentId,
                    'INV-' . date('Ym') . '-' . str_pad((string) $invoiceNumber++, 4, '0', STR_PAD_LEFT),
                    $amount,
                    $paid,
                    $paid >= $amount ? 'paid' : 'unpaid',
                    date('Y-m-d', strtotime('+10 days')),
                    date('Y-m-d H:i:s'),
                ]
            );
        }

        // PPDB applicants.
        $applicants = [
            ['Aulia Rahmawati', 'SMPN 3 Contoh', 'verified', 87.5],
            ['Bagas Prasetyo', 'SMPN 5 Contoh', 'new', null],
            ['Chelsea Anindya', 'SMP Islam Contoh', 'accepted', 91.0],
            ['Daffa Alfarizi', 'SMPN 2 Contoh', 'new', null],
            ['Elvira Putri', 'MTs Contoh', 'verified', 88.25],
            ['Farhan Maulana', 'SMPN 8 Contoh', 'rejected', 62.0],
        ];
        foreach ($applicants as $index => [$name, $origin, $status, $score]) {
            $db->insert(
                'INSERT INTO applicants (registration_no, full_name, gender, birth_date, school_origin,
                    phone, email, jalur, wave, status, score, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    'PPDB' . date('Y') . str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                    $name,
                    $index % 2 === 0 ? 'P' : 'L',
                    date('Y-m-d', strtotime('-' . random_int(12, 13) . ' years')),
                    $origin,
                    '0857' . random_int(10000000, 99999999),
                    strtolower(str_replace(' ', '.', $name)) . '@example.test',
                    $index % 3 === 0 ? 'prestasi' : 'reguler',
                    'gelombang-1',
                    $status,
                    $score,
                    date('Y-m-d H:i:s'),
                    date('Y-m-d H:i:s'),
                ]
            );
        }

        // Letters.
        $letters = [
            ['incoming', '421/112/2026', 'Undangan Rapat Koordinasi Dinas Pendidikan', 'Dinas Pendidikan Kabupaten', 'Kepala Sekolah', 'approved'],
            ['outgoing', '005/SMP-DEMO/2026', 'Surat Keterangan Aktif Siswa', 'Sekolah', 'Orang Tua/Wali', 'draft'],
            ['incoming', '800/DPK/2026', 'Permohonan Data Pokok Pendidikan', 'Dinas Pendidikan Kabupaten', 'Operator TU', 'approved'],
            ['outgoing', '006/SMP-DEMO/2026', 'Undangan Pertemuan Komite Sekolah', 'Sekolah', 'Komite Sekolah', 'approved'],
        ];
        foreach ($letters as $index => [$type, $number, $subject, $sender, $recipient, $status]) {
            $db->insert(
                'INSERT INTO letters (type, number, subject, sender, recipient, letter_date, status, created_by, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
                [
                    $type,
                    $number,
                    $subject,
                    $sender,
                    $recipient,
                    date('Y-m-d', strtotime('-' . ($index + 1) . ' days')),
                    $status,
                    $userIdByEmail['tu@demo.edusmart.local'] ?? null,
                    date('Y-m-d H:i:s'),
                ]
            );
        }

        return [
            'skipped' => false,
            'students' => count($studentIds),
            'classes' => count($classIds),
            'employees' => count($staff),
            'subjects' => count($subjects),
            'applicants' => count($applicants),
        ];
    }
}
