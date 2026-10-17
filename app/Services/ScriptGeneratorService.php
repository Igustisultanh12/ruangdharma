<?php
namespace App\Services;

/**
 * Generator naskah Ruang Dharma.
 * Aturan wajib: buka "Om Swastyastu", tutup "Om Santi Santi Santi Om",
 * tanpa kata roh/penunggu, visual Disney Pixar 3D 9:16,
 * Ibu=Aria / Anak=Lumi / tanpa backsound,
 * narasi = visual diam tanpa wajah bicara + voiceover.
 * Return: naskah (tepat 6 bagian: judul, dialog/narasi, prompt_visual, suara
 * + kunci kompatibel speaker/teks/bagian), caption (STRING berisi hook+isi+hashtag,
 * agar frontend lama & test lama tetap jalan) + caption_parts terstruktur, prompts.
 */
class ScriptGeneratorService
{
    public const OPENING = 'Om Swastyastu';
    public const CLOSING = 'Om Santi Santi Santi Om';

    protected array $knowledge = [
        'rwa bhineda' => ['judul' => 'Rwa Bhineda', 'intro' => 'Rwa Bhineda artinya dua hal yang berbeda tapi saling melengkapi, seperti siang dan malam.', 'poin' => ['Siang dan malam saling melengkapi', 'Panas dan dingin, terang dan gelap, atas dan bawah — semuanya berpasangan', 'Baik dan buruk juga berpasangan — tugas kita memilih kebaikan setiap hari', 'Kesalahan itu wajar, kita belajar darinya, seperti malam membuat kita menghargai siang'], 'caption' => 'Kenapa ada siang dan malam? ☀️🌙 Karena dunia ini seimbang — itulah Rwa Bhineda. Baik dan buruk berpasangan, tugas kita memilih yang baik setiap hari.', 'hashtags' => ['RuangDharma', 'RwaBhineda']],
        'canang sari' => ['judul' => 'Canang Sari', 'intro' => 'Canang Sari itu persembahan kecil yang cantik dari bunga dan daun, sebagai tanda syukur kita kepada Tuhan.', 'poin' => ['Bunga putih di timur untuk Sang Hyang Iswara', 'Bunga merah di selatan untuk Sang Hyang Brahma, dan bunga kuning di barat untuk Sang Hyang Mahadewa', 'Bunga biru di utara untuk Sang Hyang Wisnu, dan kembang rampai di tengah untuk Sang Hyang Siwa', 'Cara membuat: lipat daun kelapa jadi wadah kecil, susun bunga rapi dengan penuh cinta'], 'caption' => 'Tahukah kamu arti susunan bunga Canang Sari? 🌸 Timur putih, Selatan merah, Barat kuning, Utara biru, Tengah kembang rampai — semua untuk memuliakan Tuhan.', 'hashtags' => ['RuangDharma', 'CanangSari']],
        'tri sandhya' => ['judul' => 'Tri Sandhya', 'intro' => 'Tri Sandhya artinya sembahyang tiga kali sehari. Tri artinya tiga, Sandhya artinya waktu.', 'poin' => ['Pagi hari saat matahari terbit — syukur karena Tuhan membangunkan kita', 'Siang hari saat matahari di atas — terima kasih atas makanan, pelajaran, dan kebaikan hari ini', 'Sore hari saat matahari terbenam — terima kasih karena hari ini berjalan baik', 'Tri Sandhya itu seperti bilang terima kasih tiga kali: pagi, siang, sore'], 'caption' => 'Tri Sandhya = terima kasih tiga kali sehari 🙏 Pagi, siang, sore — selalu ingat Tuhan dan bersyukur.', 'hashtags' => ['RuangDharma', 'TriSandhya']],
        'karma' => ['judul' => 'Karma', 'intro' => 'Karma artinya setiap perbuatan ada hasilnya, seperti menanam benih.', 'poin' => ['Kalau tanam kebaikan, tumbuh bunga indah. Kalau tanam keburukan, tumbuh yang menyakitkan', 'Contoh: menolong teman membuat hati senang — itulah karma baik', 'Kalau berbuat baik setiap hari, kebaikan akan kembali kepada kita — itulah Karma Phala', 'Mari tanam benih kebaikan hari ini: tersenyum, menolong, dan berkata jujur'], 'caption' => 'Karma itu seperti menanam benih 🌱 Tanam kebaikan, panen kebaikan. Itulah Karma Phala.', 'hashtags' => ['RuangDharma', 'Karma', 'KarmaPhala']],
        'bhatara sri' => ['judul' => 'Bhatara Sri', 'intro' => 'Bhatara Sri adalah Dewi Padi, Dewi Kemakmuran. Beliau menjaga padi agar tumbuh subur.', 'poin' => ['Petani merawat air, tanah, dan padi dengan sabar, lalu panennya melimpah', 'Kalau kita bersyukur dan merawat sawah, padi tumbuh baik dan kita bisa makan nasi enak', 'Bersyukur dan tidak membuang nasi itu menghormati Bhatara Sri', 'Setiap butir nasi adalah anugerah — mari habiskan makanan kita dengan syukur'], 'caption' => 'Bhatara Sri, Dewi Padi 🌾 Mari bersyukur, jaga sawah, dan jangan buang makanan.', 'hashtags' => ['RuangDharma', 'BhataraSri']],
        'tri hita karana' => ['judul' => 'Tri Hita Karana', 'intro' => 'Tri Hita Karana artinya tiga penyebab kebahagiaan: hubungan baik dengan Tuhan, sesama, dan alam.', 'poin' => ['Parahyangan: hubungan baik dengan Tuhan lewat sembahyang', 'Pawongan: hubungan baik dengan sesama lewat tolong-menolong', 'Palemahan: hubungan baik dengan alam lewat merawat lingkungan', 'Kalau ketiganya dijaga, hidup kita bahagia dan damai'], 'caption' => 'Bahagia itu dari tiga hubungan baik: Tuhan, sesama, dan alam 🌿 Itulah Tri Hita Karana.', 'hashtags' => ['RuangDharma', 'TriHitaKarana']],
        'ahimsa' => ['judul' => 'Ahimsa', 'intro' => 'Ahimsa artinya tidak menyakiti. Kita menjaga sesama, hewan, dan tumbuhan dengan kasih sayang.', 'poin' => ['Tidak memukul, tidak mengejek, tidak membuat teman menangis', 'Berkata lembut dan jujur adalah bentuk Ahimsa', 'Merawat hewan dan tumbuhan tanpa menyakiti adalah Ahimsa', 'Kalau marah, tarik napas dulu lalu bicara dengan tenang'], 'caption' => 'Ahimsa = tidak menyakiti 💛 Jaga sesama, hewan, dan tumbuhan dengan kasih sayang.', 'hashtags' => ['RuangDharma', 'Ahimsa']],
        'nyepi' => ['judul' => 'Nyepi', 'intro' => 'Nyepi adalah Hari Raya sepi: sehari penuh kita diam, berpuasa, dan merenung di rumah.', 'poin' => ['Saat Nyepi kita tidak menyalakan api dan tidak bepergian — ini namanya Catur Brata Penyepian', 'Kita diam dan berdoa agar hati menjadi tenang', 'Sehari hening membuat alam ikut beristirahat', 'Setelah Nyepi, kita memulai tahun baru dengan hati bersih'], 'caption' => 'Nyepi: sehari hening untuk hati yang tenang 🕯️ Catur Brata Penyepian mengajarkan kita diam dan merenung.', 'hashtags' => ['RuangDharma', 'Nyepi']],
        'ngayah' => ['judul' => 'Ngayah', 'intro' => 'Ngayah artinya bekerja bersama tanpa pamrih untuk banjar, pura, dan sesama.', 'poin' => ['Semua ikut membantu: ada yang menyapu, ada yang menghias, ada yang memasak', 'Ngayah membuat pekerjaan berat terasa ringan dan menyenangkan', 'Tidak mengharapkan imbalan — yang penting semua bahagia', 'Ngayah melatih kita rendah hati dan peduli sesama'], 'caption' => 'Ngayah: bekerja bersama tanpa pamrih 🤝 Ringan sama dijinjing, berat sama dipikul.', 'hashtags' => ['RuangDharma', 'Ngayah']],
        'saraswati' => ['judul' => 'Saraswati', 'intro' => 'Saraswati adalah Dewi Ilmu Pengetahuan. Beliau menjaga buku, ilmu, dan seni.', 'poin' => ['Saat Hari Saraswati kita merayakan buku dan ilmu', 'Buku adalah jendela dunia — rajin membaca membuat kita pintar', 'Belajar dengan tekun adalah cara menghormati Saraswati', 'Ilmu yang baik dipakai untuk menolong sesama'], 'caption' => 'Saraswati, Dewi Ilmu Pengetahuan 📚 Rajin membaca, tekun belajar, ilmu untuk menolong sesama.', 'hashtags' => ['RuangDharma', 'Saraswati']],
        'om swastyastu' => ['judul' => 'Om Swastyastu', 'intro' => 'Om Swastyastu adalah salam umat Hindu yang artinya semoga selalu dalam keadaan baik atas karunia Tuhan.', 'poin' => ['Om adalah aksara suci untuk menyebut Tuhan, Swasti artinya selamat, Astu artinya semoga', 'Kita ucapkan saat bertemu guru, teman, atau tamu sambil mencakupkan tangan di depan dada', 'Salam ini adalah doa kecil agar orang yang kita sapa selalu bahagia', 'Mari biasakan menyapa dengan Om Swastyastu dan senyum yang tulus'], 'caption' => 'Om Swastyastu bukan sekadar salam 🙏 Artinya: semoga selalu dalam keadaan baik atas karunia Tuhan.', 'hashtags' => ['RuangDharma', 'OmSwastyastu']],
        'panca yama brata' => ['judul' => 'Panca Yama Brata', 'intro' => 'Panca Yama Brata adalah lima cara mengendalikan diri agar hidup kita baik dan damai.', 'poin' => ['Ahimsa artinya tidak menyakiti, dan Satya artinya selalu jujur', 'Asteya artinya tidak mengambil milik orang lain tanpa izin', 'Brahmacari artinya rajin belajar dan menjaga diri, Aparigraha artinya hidup sederhana dan tidak serakah', 'Kalau lima hal ini kita jaga, hati menjadi tenang dan banyak teman yang sayang'], 'caption' => 'Lima kunci mengendalikan diri ✋ Ahimsa, Satya, Asteya, Brahmacari, Aparigraha — itulah Panca Yama Brata.', 'hashtags' => ['RuangDharma', 'PancaYamaBrata']],
    ];

    /** Kalimat pengayaan untuk durasi 90 detik (agar naskah benar-benar lebih panjang). */
    protected array $elaborations = [
        'Coba bayangkan kalau kita melakukannya di rumah bersama keluarga.',
        'Hal kecil seperti ini, kalau dilakukan setiap hari, akan menjadi kebiasaan baik.',
        'Orang tua dan guru kita juga sudah mencontohkannya sejak dulu.',
        'Pelan-pelan saja, yang penting kita melakukannya dengan hati yang tulus.',
    ];

    public function knowledge(): array { return $this->knowledge; }
    public function hasKnowledge(string $keyword): bool { return isset($this->knowledge[mb_strtolower(trim($keyword))]); }

    public function generate(string $keyword, int $durasi, string $model): array
    {
        $key = mb_strtolower(trim($keyword));
        $data = $this->knowledge[$key] ?? null;
        $judul = $data['judul'] ?? $this->titleCase($keyword);
        $intro = $data['intro'] ?? "Hari ini kita belajar tentang {$judul} bersama Ibu dan Anak.";
        $poin = $data['poin'] ?? [
            "Apa itu {$judul}? Mari kita belajar bersama dengan hati gembira.",
            "Mengapa {$judul} penting dalam kehidupan sehari-hari?",
            "Contoh sederhana {$judul} yang bisa kita lakukan di rumah dan sekolah.",
            "Apa yang bisa kita lakukan hari ini untuk mengamalkan {$judul}?",
        ];
        $poin = array_values(array_slice(array_pad($poin, 4, $poin[0] ?? $intro), 0, 4));
        $hook = $data['caption'] ?? "Hari ini kita belajar tentang {$judul} 🌸 Yuk belajar bersama Ruang Dharma!";
        $hashtags = array_values(array_unique(array_merge($data['hashtags'] ?? [], ['RuangDharma', 'HinduUntukAnak', 'CeritaHindu', 'DharmaUntukAnak', 'Bali', 'HinduDharma', 'EdukasiAnak'])));
        $hashtagStr = implode(' ', array_map(fn($h) => '#' . ltrim($h, '#'), $hashtags));

        // Tepat 6 bagian.
        $lines = match ($model) {
            'narasi' => [
                ['speaker' => 'Narator', 'teks' => self::OPENING . "! {$intro}"],
                ['speaker' => 'Narator', 'teks' => $poin[0] . '.'],
                ['speaker' => 'Narator', 'teks' => $poin[1] . '.'],
                ['speaker' => 'Narator', 'teks' => $poin[2] . '.'],
                ['speaker' => 'Narator', 'teks' => $poin[3] . '.'],
                ['speaker' => 'Narator', 'teks' => 'Yuk, kita amalkan dengan hati yang tulus. ' . self::CLOSING . '.'],
            ],
            'ngobrol' => [
                ['speaker' => 'Anak', 'teks' => self::OPENING . ", Ibu! Apa itu {$judul}?"],
                ['speaker' => 'Ibu', 'teks' => self::OPENING . ", sayang. {$intro} Yuk, Ibu ceritakan!"],
                ['speaker' => 'Ibu', 'teks' => $poin[0] . '.'],
                ['speaker' => 'Ibu', 'teks' => $this->joinSentences($poin[1], $poin[2])],
                ['speaker' => 'Anak', 'teks' => 'Aku mau selalu ingat dan mengamalkannya, Bu!'],
                ['speaker' => 'Ibu', 'teks' => 'Bagus, sayang! Semoga kita selalu bersyukur. ' . self::CLOSING . '.'],
            ],
            default => [ // gabungan
                ['speaker' => 'Narator', 'teks' => self::OPENING . "! Hari ini kita belajar tentang {$judul}. {$intro}"],
                ['speaker' => 'Anak', 'teks' => "Ibu, apa itu {$judul}?"],
                ['speaker' => 'Ibu', 'teks' => $poin[0] . '.'],
                ['speaker' => 'Narator', 'teks' => $poin[1] . '.'],
                ['speaker' => 'Ibu', 'teks' => $this->joinSentences($poin[2], $poin[3])],
                ['speaker' => 'Narator', 'teks' => 'Yuk, kita amalkan dengan hati yang tulus. ' . self::CLOSING . '.'],
            ],
        };

        $lines = $this->fitDuration($lines, $durasi);
        $words = array_map(fn ($l) => max(1, str_word_count(strip_tags($l['teks']))), $lines);
        $totalWords = array_sum($words);

        $naskah = []; $prompts = [];
        foreach ($lines as $i => $s) {
            $prompt = $this->visualPrompt($judul, $i + 1, $model, $s['speaker']);
            $suara = $s['speaker'] === 'Anak' ? 'Lumi (avocado_v2:lumi)' : ($s['speaker'] === 'Ibu' ? 'Aria (avocado_v2:MAI_01)' : 'Narator Aria (avocado_v2:MAI_01)');
            $naskah[] = [
                'bagian' => $i + 1, 'judul' => "{$judul} — Bagian " . ($i + 1),
                'speaker' => $s['speaker'], 'dialog' => $s['teks'], 'narasi' => $s['teks'], 'teks' => $s['teks'],
                'prompt_visual' => $prompt, 'suara' => $suara,
                'estimasi_detik' => max(3, (int) round($durasi * $words[$i] / $totalWords)),
            ];
            $prompts[] = $prompt;
        }
        $captionText = "{$hook}\n\n{$hashtagStr}\n\n" . self::CLOSING . " 🙏";
        $joined = json_encode($naskah, JSON_UNESCAPED_UNICODE);
        if (preg_match('/\broh\b|penunggu/i', $joined)) throw new \Exception('Naskah mengandung kata terlarang.');

        return [
            'judul' => $judul, 'keyword' => $keyword, 'durasi' => $durasi, 'model' => $model,
            'naskah' => $naskah, 'prompts' => $prompts,
            'caption' => $captionText,
            'caption_parts' => ['hook' => $hook, 'isi' => $hook, 'hashtag' => $hashtags, 'text' => $captionText],
            'aturan' => ['pembuka' => self::OPENING, 'penutup' => self::CLOSING, 'visual' => '3D Disney/Pixar, 9:16, anak Bali 5 tahun + Ibu, mata besar berkilau, pencahayaan hangat', 'suara' => $model === 'narasi' ? 'Narator tunggal, visual diam tanpa wajah bicara + voiceover' : 'Ibu=Aria, Anak=Lumi, tanpa backsound, satu pembicara per segmen, yang lain diam'],
        ];
    }

    /**
     * Sesuaikan panjang teks dengan durasi: 30 dtk = ringkas (bagian tengah dipangkas ke
     * klausa utama), 60 dtk = standar, 90 dtk = bagian tengah diberi kalimat pengayaan.
     * Bagian pembuka (Om Swastyastu) & penutup (Om Santi Santi Santi Om) tidak diubah.
     */
    protected function fitDuration(array $lines, int $durasi): array
    {
        $last = count($lines) - 1;
        foreach ($lines as $i => &$line) {
            if ($i === 0 || $i === $last) continue;
            if ($durasi <= 30) {
                // Ambil kalimat/klausa awal sampai cukup bermakna (>= 40 karakter).
                $parts = preg_split('/\s+—\s+|(?<=[.!?])\s+/u', $line['teks']);
                $keep = '';
                foreach ($parts as $part) {
                    $keep = trim($keep === '' ? $part : rtrim($keep, ' .') . '. ' . $part);
                    if (mb_strlen($keep) >= 40) break;
                }
                $keep = rtrim($keep, " .,;");
                $line['teks'] = $keep . (preg_match('/[!?]$/u', $keep) ? '' : '.');
            } elseif ($durasi >= 90 && ($line['speaker'] ?? '') !== 'Anak' && ! str_ends_with(rtrim($line['teks']), '?')) {
                $line['teks'] = rtrim($line['teks']) . ' ' . $this->elaborations[($i - 1) % count($this->elaborations)];
            }
        }
        unset($line);
        return $lines;
    }

    protected function joinSentences(string $a, string $b): string
    {
        return rtrim($a, ' .') . '. ' . rtrim($b, ' .') . '.';
    }

    protected function visualPrompt(string $judul, int $bagian, string $model, string $speaker): string
    {
        $base = "3D Disney/Pixar cartoon, vertical 9:16, 5-year-old Balinese boy + adult mother, large glossy eyes, warm cinematic lighting, Octane Render";
        if ($model === 'narasi') return "{$base}, SILENT VISUAL ONLY for {$judul} part {$bagian}: back view / hands / objects close-up, NO talking faces, NO mouth movement, NO dialogue, narrator voiceover only.";
        if ($speaker === 'Anak') return "{$base}, ONLY child speaks in {$judul} part {$bagian}, mother silent mouth closed listening and nodding, do not echo child's line.";
        if ($speaker === 'Ibu') return "{$base}, ONLY mother speaks in {$judul} part {$bagian}, child silent mouth closed listening, do not echo mother's line.";
        return "{$base}, scene for {$judul} part {$bagian}.";
    }
    protected function titleCase(string $s): string { return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8'); }
}
