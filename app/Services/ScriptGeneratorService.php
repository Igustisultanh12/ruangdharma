<?php
namespace App\Services;

class ScriptGeneratorService
{
    protected array $knowledge = [
        'rwa bhineda' => [
            'judul' => 'Rwa Bhineda',
            'intro' => 'Rwa Bhineda artinya dua hal yang berbeda tapi saling melengkapi, seperti siang dan malam.',
            'poin' => ['Siang dan malam saling melengkapi','Panas dan dingin, terang dan gelap, atas dan bawah','Baik dan buruk juga berpasangan — tugas kita memilih kebaikan setiap hari','Kesalahan itu wajar, kita belajar darinya, seperti malam membuat kita menghargai siang','Jaga keseimbangan: berhati baik, menolong sesama, merawat alam'],
            'caption' => 'Kenapa ada siang dan malam? ☀️🌙 Karena dunia ini seimbang — itulah Rwa Bhineda. Baik dan buruk berpasangan, tugas kita memilih yang baik setiap hari. #RuangDharma #RwaBhineda',
        ],
        'canang sari' => [
            'judul' => 'Canang Sari',
            'intro' => 'Canang Sari itu persembahan kecil yang cantik dari bunga dan daun, sebagai tanda syukur kita kepada Tuhan.',
            'poin' => ['Bunga putih di timur untuk Sang Hyang Iswara','Bunga merah di selatan untuk Sang Hyang Brahma','Bunga kuning di barat untuk Sang Hyang Mahadewa','Bunga biru di utara untuk Sang Hyang Wisnu','Kembang rampai di tengah untuk Sang Hyang Siwa','Cara membuat: lipat daun kelapa jadi wadah kecil, susun bunga rapi dengan penuh cinta'],
            'caption' => 'Tahukah kamu arti susunan bunga Canang Sari? 🌸 Timur putih, Selatan merah, Barat kuning, Utara biru, Tengah kembang rampai — semua untuk memuliakan Tuhan. #RuangDharma #CanangSari',
        ],
        'tri sandhya' => [
            'judul' => 'Tri Sandhya',
            'intro' => 'Tri Sandhya artinya sembahyang tiga kali sehari. Tri artinya tiga, Sandhya artinya waktu.',
            'poin' => ['Pagi hari saat matahari terbit — syukur karena Tuhan membangunkan kita','Siang hari saat matahari di atas — terima kasih atas makanan, pelajaran, dan kebaikan hari ini','Sore hari saat matahari terbenam — terima kasih karena hari ini berjalan baik','Tri Sandhya itu seperti bilang terima kasih tiga kali: pagi, siang, sore'],
            'caption' => 'Tri Sandhya = terima kasih tiga kali sehari 🙏 Pagi, siang, sore — selalu ingat Tuhan dan bersyukur. #RuangDharma #TriSandhya',
        ],
        'karma' => [
            'judul' => 'Karma',
            'intro' => 'Karma artinya setiap perbuatan ada hasilnya, seperti menanam benih.',
            'poin' => ['Kalau tanam kebaikan, tumbuh bunga indah. Kalau tanam keburukan, tumbuh yang menyakitkan','Contoh: menolong teman membuat hati senang — itulah karma baik','Kalau berbuat baik setiap hari, kebaikan akan kembali kepada kita — itulah Karma Phala'],
            'caption' => 'Karma itu seperti menanam benih 🌱 Tanam kebaikan, panen kebaikan. Itulah Karma Phala. #RuangDharma #Karma #KarmaPhala',
        ],
        'bhatara sri' => [
            'judul' => 'Bhatara Sri',
            'intro' => 'Bhatara Sri adalah Dewi Padi, Dewi Kemakmuran. Beliau menjaga padi agar tumbuh subur.',
            'poin' => ['Petani merawat air, tanah, dan padi dengan sabar, lalu panennya melimpah','Kalau kita bersyukur dan merawat sawah, padi tumbuh baik dan kita bisa makan nasi enak','Bersyukur dan tidak membuang nasi itu menghormati Bhatara Sri'],
            'caption' => 'Bhatara Sri, Dewi Padi 🌾 Mari bersyukur, jaga sawah, dan jangan buang makanan. #RuangDharma #BhataraSri',
        ],
        'tri hita karana' => [
            'judul' => 'Tri Hita Karana',
            'intro' => 'Tri Hita Karana artinya tiga penyebab kebahagiaan: hubungan baik dengan Tuhan, sesama, dan alam.',
            'poin' => ['Parahyangan: hubungan baik dengan Tuhan lewat sembahyang','Pawongan: hubungan baik dengan sesama lewat tolong-menolong','Palemahan: hubungan baik dengan alam lewat merawat lingkungan'],
            'caption' => 'Bahagia itu dari tiga hubungan baik: Tuhan, sesama, dan alam 🌿 Itulah Tri Hita Karana. #RuangDharma #TriHitaKarana',
        ],
    ];

    public function generate(string $keyword, int $durasi, string $model): array
    {
        $key = mb_strtolower(trim($keyword));
        $data = $this->knowledge[$key] ?? null;
        $judul = $data['judul'] ?? $this->titleCase($keyword);
        $poin = $data['poin'] ?? [
            "Apa itu {$judul}? Mari kita belajar bersama dengan hati gembira.",
            "Mengapa {$judul} penting dalam kehidupan sehari-hari?",
            "Contoh sederhana {$judul} yang bisa kita lakukan di rumah dan sekolah.",
            "Apa yang bisa kita lakukan hari ini untuk mengamalkan {$judul}?",
        ];
        $intro = $data['intro'] ?? "Hari ini kita belajar tentang {$judul} bersama Ibu dan Anak.";
        $caption = $data['caption'] ?? "Hari ini kita belajar tentang {$judul} 🌸 Yuk belajar bersama Ruang Dharma! #RuangDharma #BelajarHindu #AnakBali #HinduDharma";

        $naskah = [];
        $prompts = [];
        $bagian = $durasi === 30 ? 3 : 6;
        // Bangun 6 bagian standar, potong jika 30 detik
        $all = [];
        if ($model === 'narasi') {
            $all[] = ['speaker'=>'Narator','teks'=>"Om Swastyastu! {$intro}"];
            foreach ($poin as $i => $p) {
                $all[] = ['speaker'=>'Narator','teks'=> $p . '.'];
            }
            $all[] = ['speaker'=>'Narator','teks'=>'Yuk, kita amalkan dengan hati yang tulus. Om Santi Santi Santi Om.'];
        } elseif ($model === 'ngobrol') {
            $all[] = ['speaker'=>'Anak','teks'=>"Om Swastyastu, Ibu! Apa itu {$judul}?"];
            $all[] = ['speaker'=>'Ibu','teks'=>"Om Swastyastu, sayang. {$intro} Yuk, Ibu ceritakan!"];
            foreach ($poin as $p) {
                $all[] = ['speaker'=>'Ibu','teks'=> $p . '.'];
            }
            $all[] = ['speaker'=>'Anak','teks'=>'Aku mau selalu ingat dan mengamalkannya, Bu!'];
            $all[] = ['speaker'=>'Ibu','teks'=>'Bagus, sayang! Semoga kita selalu bersyukur. Om Santi Santi Santi Om.'];
        } else { // gabungan
            $all[] = ['speaker'=>'Narator','teks'=>"Om Swastyastu! Hari ini kita belajar tentang {$judul}. {$intro}"];
            $all[] = ['speaker'=>'Anak','teks'=>"Ibu, apa itu {$judul}?"];
            $all[] = ['speaker'=>'Ibu','teks'=> ($poin[0] ?? $intro) . '.'];
            $all[] = ['speaker'=>'Narator','teks'=> ($poin[1] ?? $intro) . '.'];
            $all[] = ['speaker'=>'Ibu','teks'=> ($poin[2] ?? $intro) . '.'];
            $all[] = ['speaker'=>'Narator','teks'=>'Yuk, kita amalkan dengan hati yang tulus. Om Santi Santi Santi Om.'];
        }
        // Sesuaikan jumlah bagian
        $selected = array_slice($all, 0, $bagian);
        // Pastikan penutup selalu Om Santi
        $last = end($selected);
        if (!str_contains($last['teks'], 'Om Santi Santi Santi Om')) {
            $selected[] = ['speaker'=> $model==='ngobrol' ? 'Ibu' : 'Narator','teks'=>'Semoga kita selalu bersyukur. Om Santi Santi Santi Om.'];
        }
        foreach ($selected as $i => $s) {
            $naskah[] = ['bagian'=> $i+1,'speaker'=>$s['speaker'],'teks'=>$s['teks'],'suara'=> $s['speaker']==='Anak' ? 'Lumi (avocado_v2:lumi)' : ($s['speaker']==='Ibu' ? 'Aria (avocado_v2:MAI_01)' : 'Narator Aria (avocado_v2:MAI_01)')];
            $prompts[] = $this->visualPrompt($judul, $i+1, $model, $s['speaker']);
        }
        // Tanpa kata terlarang
        $joined = json_encode($naskah, JSON_UNESCAPED_UNICODE);
        if (preg_match('/\broh\b|penunggu/i', $joined)) {
            throw new \Exception('Naskah mengandung kata terlarang.');
        }
        return [
            'judul'=>$judul,'keyword'=>$keyword,'durasi'=>$durasi,'model'=>$model,
            'naskah'=>$naskah,'prompts'=>$prompts,'caption'=>$caption . "\n\nOm Santi Santi Santi Om 🙏\n#RuangDharma #HinduUntukAnak #CeritaHindu #DharmaUntukAnak #Bali #HinduDharma #EdukasiAnak",
            'aturan'=>['pembuka'=>'Om Swastyastu','penutup'=>'Om Santi Santi Santi Om','visual'=>'3D Disney/Pixar, 9:16, anak Bali 5 tahun + Ibu, mata besar berkilau, pencahayaan hangat','suara'=> $model==='narasi' ? 'Narator tunggal, visual diam tanpa wajah bicara + voiceover' : 'Ibu=Aria, Anak=Lumi, tanpa backsound, satu pembicara per segmen, yang lain diam'],
        ];
    }
    protected function visualPrompt(string $judul, int $bagian, string $model, string $speaker): string {
        $base = "3D Disney/Pixar cartoon, vertical 9:16, 5-year-old Balinese boy + adult mother, large glossy eyes, warm cinematic lighting, Octane Render";
        if ($model==='narasi') {
            return "$base, SILENT VISUAL ONLY for {$judul} part {$bagian}: back view / hands / objects close-up, NO talking faces, NO mouth movement, NO dialogue, narrator voiceover only.";
        }
        if ($speaker==='Anak') {
            return "$base, ONLY child speaks in {$judul} part {$bagian}, mother silent mouth closed listening and nodding, do not echo child's line.";
        }
        if ($speaker==='Ibu') {
            return "$base, ONLY mother speaks in {$judul} part {$bagian}, child silent mouth closed listening, do not echo mother's line.";
        }
        return "$base, scene for {$judul} part {$bagian}.";
    }
    protected function titleCase(string $s): string { return mb_convert_case($s, MB_CASE_TITLE, 'UTF-8'); }
}
