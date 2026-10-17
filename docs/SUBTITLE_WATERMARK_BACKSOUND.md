# Subtitle + Watermark + Backsound (ffmpeg, dipakai Muse saat generate)

# 1. Subtitle: buat file .srt dari naskah (per bagian, timing dari durasi chunk TTS)
# Contoh srt:
# 1
# 00:00:00,000 --> 00:00:05,000
# Om Swastyastu, anak-anak!

# 2. Watermark + Subtitle (gaya screenshot: teks putih tebal, outline hitam, bawah tengah + watermark @ruangdharma_):
ffmpeg -i input.mp4 -vf "subtitles=subs.srt:force_style='FontName=Arial,FontSize=22,PrimaryColour=&HFFFFFF,OutlineColour=&H000000,BorderStyle=3,Outline=2,Shadow=0,Alignment=2,MarginV=90',drawtext=text='@ruangdharma_':fontfile=/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf:fontsize=22:fontcolor=white:borderw=2:bordercolor=black:x=(w-text_w)/2:y=h-40" -c:a copy out_sub.mp4

# 3. Backsound: potong / loop sesuai durasi video + volume + mix di bawah narasi:
# volume 20% = 0.20, trim start 0, durasi video 60 detik
ffmpeg -i out_sub.mp4 -stream_loop -1 -ss 0 -i backsound.mp3 -filter_complex "[1:a]atrim=0:60,asetpts=PTS-STARTPTS,volume=0.20[bg];[0:a][bg]amix=inputs=2:duration=first:dropout_transition=2[a]" -map 0:v -map "[a]" -c:v copy -shortest out_final.mp4
