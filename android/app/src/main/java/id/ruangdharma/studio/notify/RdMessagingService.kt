package id.ruangdharma.studio.notify

import com.google.firebase.messaging.FirebaseMessagingService
import com.google.firebase.messaging.RemoteMessage
import id.ruangdharma.studio.data.api.RetrofitClient
import id.ruangdharma.studio.data.api.TokenStore
import id.ruangdharma.studio.data.update.AppUpdater
import kotlinx.coroutines.flow.first

/**
 * Penerima pesan push FCM. Server mengirim pesan *data* berprioritas tinggi sehingga
 * aplikasi sendiri yang menampilkan notifikasi (kanal, suara, warna) — tetap jalan
 * walau aplikasi sedang ditutup.
 */
class RdMessagingService : FirebaseMessagingService() {

    override fun onNewToken(token: String) {
        val ctx = applicationContext
        PushManager.onNewToken(ctx, token) {
            val saved = try { TokenStore.tokenFlow(ctx).first() } catch (_: Exception) { null }
            if (saved.isNullOrBlank()) null else RetrofitClient.create(ctx) { saved }
        }
    }

    override fun onMessageReceived(message: RemoteMessage) {
        val d = message.data
        val title = d["title"].orEmpty().ifBlank { message.notification?.title.orEmpty() }.ifBlank { "Ruang Dharma Studio" }
        val body = d["body"].orEmpty().ifBlank { message.notification?.body.orEmpty() }.ifBlank { "Ada pemberitahuan baru." }
        when (d["type"] ?: "notification") {
            "notification" -> NotificationHelper.showPushed(
                this,
                id = d["id"]?.toIntOrNull() ?: (System.currentTimeMillis() / 1000).toInt(),
                title = title,
                message = body,
                force = d["test"] == "1",
            )
            "app_update" -> AppUpdater.onServerPush(
                this,
                version = d["version"].orEmpty(),
                title = title,
                body = body
            )
        }
    }
}
