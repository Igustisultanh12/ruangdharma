package id.ruangdharma.studio.notify

import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent
import android.media.AudioAttributes
import android.media.RingtoneManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.core.content.ContextCompat
import id.ruangdharma.studio.MainActivity
import id.ruangdharma.studio.R

/**
 * Notifikasi sistem (bilah status) bersuara — pola yang sama dengan SISINDEN:
 *  - kanal terpisah: Status video (Muse selesai/gagal), Review & komentar, Umum;
 *  - deduplikasi persisten (id notifikasi terakhir yang sudah dibunyikan), sehingga
 *    notifikasi yang sama tidak berbunyi dua kali walau datang dari push FCM,
 *    polling saat aplikasi terbuka, dan WorkManager di latar belakang;
 *  - peluncuran pertama hanya menyelaraskan penanda tanpa membunyikan notifikasi lama.
 */
object NotificationHelper {
    const val CHANNEL_VIDEO = "rd_video_status"
    const val CHANNEL_REVIEW = "rd_review"
    const val CHANNEL_GENERAL = "rd_general"
    const val EXTRA_OPEN = "rd_open"

    private const val PREFS = "rd_notification_prefs"
    private const val K_LAST_ID = "last_notified_id"
    private const val K_INIT = "initialized"
    private const val K_SOUND = "sound_enabled"
    private const val K_ENABLED = "enabled"
    private const val MAX_BANNERS = 4
    private const val GROUP = "rd_studio_group"

    private fun prefs(c: Context) = c.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun isEnabled(c: Context) = prefs(c).getBoolean(K_ENABLED, true)
    fun setEnabled(c: Context, v: Boolean) = prefs(c).edit().putBoolean(K_ENABLED, v).apply()
    fun isSoundEnabled(c: Context) = prefs(c).getBoolean(K_SOUND, true)
    fun setSoundEnabled(c: Context, v: Boolean) = prefs(c).edit().putBoolean(K_SOUND, v).apply()

    /** Izin POST_NOTIFICATIONS (Android 13+) sudah diberikan? */
    fun hasPermission(c: Context): Boolean =
        Build.VERSION.SDK_INT < 33 || ContextCompat.checkSelfPermission(c, android.Manifest.permission.POST_NOTIFICATIONS) == android.content.pm.PackageManager.PERMISSION_GRANTED

    fun createChannels(c: Context) {
        if (Build.VERSION.SDK_INT < Build.VERSION_CODES.O) return
        val sound = RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION)
        val attrs = AudioAttributes.Builder()
            .setContentType(AudioAttributes.CONTENT_TYPE_SONIFICATION)
            .setUsage(AudioAttributes.USAGE_NOTIFICATION)
            .build()
        val m = c.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager
        fun ch(id: String, name: String, desc: String, vib: LongArray) = NotificationChannel(id, name, NotificationManager.IMPORTANCE_HIGH).apply {
            description = desc
            enableLights(true)
            lightColor = 0xFF7C3AED.toInt()
            enableVibration(true)
            vibrationPattern = vib
            setSound(sound, attrs)
        }
        m.createNotificationChannel(ch(CHANNEL_VIDEO, "Status video", "Video Muse selesai, gagal, atau terunggah", longArrayOf(0, 250, 150, 250)))
        m.createNotificationChannel(ch(CHANNEL_REVIEW, "Review & komentar", "Naskah perlu review, revisi, disetujui, dan komentar baru", longArrayOf(0, 180, 120, 180)))
        m.createNotificationChannel(ch(CHANNEL_GENERAL, "Notifikasi studio", "Pemberitahuan umum Ruang Dharma Studio", longArrayOf(0, 200)))
    }

    /** Pilih kanal & warna dari judul notifikasi server. */
    fun channelFor(title: String, message: String): String {
        val t = (title + " " + message).lowercase()
        return when {
            listOf("review", "revisi", "disetujui", "komentar", "terbit").any { it in t } -> CHANNEL_REVIEW
            listOf("video", "muse", "job", "generate").any { it in t } -> CHANNEL_VIDEO
            else -> CHANNEL_GENERAL
        }
    }

    data class Item(val id: Int, val title: String, val message: String, val isRead: Boolean)

    /**
     * Bunyikan semua notifikasi baru (id > penanda) yang belum dibaca.
     * @return jumlah banner yang dimunculkan.
     */
    fun process(c: Context, items: List<Item>): Int {
        if (items.isEmpty()) return 0
        val p = prefs(c)
        val maxId = items.maxOf { it.id }
        if (!p.getBoolean(K_INIT, false)) {
            p.edit().putBoolean(K_INIT, true).putInt(K_LAST_ID, maxId).apply()
            return 0
        }
        val last = p.getInt(K_LAST_ID, 0)
        val fresh = newUnread(items, last)
        if (maxId > last) p.edit().putInt(K_LAST_ID, maxId).apply()
        fresh.take(MAX_BANNERS).forEach { show(c, it.id, it.title, it.message) }
        return fresh.size.coerceAtMost(MAX_BANNERS)
    }

    internal fun newUnread(items: List<Item>, lastId: Int): List<Item> =
        items.filter { it.id > lastId && !it.isRead }.sortedByDescending { it.id }

    /** Notifikasi dari push FCM: tampilkan bila belum pernah dibunyikan (dedup dengan polling). */
    fun showPushed(c: Context, id: Int, title: String, message: String, force: Boolean = false) {
        val p = prefs(c)
        val last = p.getInt(K_LAST_ID, 0)
        if (!force && p.getBoolean(K_INIT, false) && id in 1..last) return
        if (!force) p.edit().putBoolean(K_INIT, true).putInt(K_LAST_ID, maxOf(id, last)).apply()
        show(c, id, title, message)
    }

    /** Reset penanda saat ganti akun agar notifikasi akun baru tidak tertahan. */
    fun reset(c: Context) {
        prefs(c).edit().remove(K_INIT).remove(K_LAST_ID).apply()
        cancelAll(c)
    }

    fun cancelAll(c: Context) {
        (c.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager).cancelAll()
    }

    fun show(c: Context, id: Int, title: String, message: String) {
        if (!isEnabled(c) || !hasPermission(c)) return
        createChannels(c)
        val intent = Intent(c, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP or Intent.FLAG_ACTIVITY_SINGLE_TOP
            putExtra(EXTRA_OPEN, "notifications")
        }
        val pi = PendingIntent.getActivity(c, id, intent, PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE)
        val channel = channelFor(title, message)
        val color = when {
            "gagal" in (title + message).lowercase() -> 0xFFF43F5E.toInt()
            channel == CHANNEL_REVIEW -> 0xFFC026D3.toInt()
            channel == CHANNEL_VIDEO -> 0xFF7C3AED.toInt()
            else -> 0xFFF97316.toInt()
        }
        val b = NotificationCompat.Builder(c, channel)
            .setSmallIcon(R.drawable.ic_stat_rd)
            .setColor(color)
            .setContentTitle(title)
            .setContentText(message)
            .setStyle(NotificationCompat.BigTextStyle().bigText(message))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setCategory(if (channel == CHANNEL_REVIEW) NotificationCompat.CATEGORY_SOCIAL else NotificationCompat.CATEGORY_STATUS)
            .setAutoCancel(true)
            .setGroup(GROUP)
            .setContentIntent(pi)
        if (isSoundEnabled(c)) {
            b.setDefaults(NotificationCompat.DEFAULT_ALL)
                .setSound(RingtoneManager.getDefaultUri(RingtoneManager.TYPE_NOTIFICATION))
                .setVibrate(longArrayOf(0, 250, 150, 250))
        } else b.setSilent(true)
        try {
            (c.getSystemService(Context.NOTIFICATION_SERVICE) as NotificationManager).notify(id, b.build())
        } catch (_: SecurityException) {
            // Izin notifikasi dicabut pengguna: abaikan tanpa menghentikan aplikasi.
        }
    }
}
