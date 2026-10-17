<?php
namespace App\Services;

/** Katalog template/ide video siap pakai. */
class TemplateService
{
    public function all(): array
    {
        $t = fn(string $keyword, string $judul, string $desc, int $durasi = 60, string $model = 'narasi', array $tags = []) => [
            'keyword' => $keyword, 'judul' => $judul, 'deskripsi' => $desc,
            'default_durasi' => $durasi, 'default_model' => $model, 'tags' => $tags,
        ];
        return [
            $t('Rwa Bhineda', 'Rwa Bhineda', 'Dua hal berbeda yang saling melengkapi: siang-malam, baik-buruk.', 60, 'ngobrol', ['filosofi', 'keseimbangan']),
            $t('Canang Sari', 'Canang Sari', 'Arti susunan bunga Canang Sari per arah mata angin + dewatanya.', 60, 'narasi', ['upacara', 'bunga']),
            $t('Tri Sandhya', 'Tri Sandhya', 'Sembahyang tiga kali sehari: pagi, siang, sore.', 60, 'narasi', ['sembahyang', 'harian']),
            $t('Karma', 'Karma', 'Setiap perbuatan ada hasilnya, seperti menanam benih.', 60, 'ngobrol', ['karma', 'perbuatan']),
            $t('Bhatara Sri', 'Bhatara Sri', 'Dewi Padi & kemakmuran; syukur dan jangan buang nasi.', 60, 'narasi', ['dewi', 'padi']),
            $t('Tri Hita Karana', 'Tri Hita Karana', 'Tiga penyebab kebahagiaan: Tuhan, sesama, alam.', 60, 'narasi', ['filosofi', 'harmoni']),
            $t('Ahimsa', 'Ahimsa', 'Tidak menyakiti sesama, hewan, dan tumbuhan.', 60, 'ngobrol', ['akhlak', 'kasih']),
            $t('Nyepi', 'Nyepi', 'Hari Raya sepi & Catur Brata Penyepian.', 60, 'narasi', ['hari-raya', 'nyepi']),
            $t('Ngayah', 'Ngayah', 'Bekerja bersama tanpa pamrih untuk banjar & pura.', 60, 'gabungan', ['gotong-royong', 'banjar']),
            $t('Saraswati', 'Saraswati', 'Dewi Ilmu Pengetahuan; rajin membaca & tekun belajar.', 60, 'narasi', ['dewi', 'ilmu']),
            $t('Om Swastyastu', 'Om Swastyastu', 'Arti salam pembuka umat Hindu dan kapan mengucapkannya.', 30, 'ngobrol', ['salam', 'doa']),
            $t('Panca Yama Brata', 'Panca Yama Brata', 'Lima pengendalian diri: Ahimsa, Satya, Asteya, Brahmacari, Aparigraha.', 90, 'narasi', ['etika', 'brata']),
        ];
    }
    public function find(string $keyword): ?array
    {
        foreach ($this->all() as $tpl) if (mb_strtolower($tpl['keyword']) === mb_strtolower(trim($keyword))) return $tpl;
        return null;
    }
}
