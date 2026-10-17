package id.ruangdharma.studio.notify

import android.content.Context
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.NetworkType
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import id.ruangdharma.studio.data.api.ApiService
import id.ruangdharma.studio.data.api.RetrofitClient
import id.ruangdharma.studio.data.api.TokenStore
import kotlinx.coroutines.flow.first
import java.util.concurrent.TimeUnit

/**
 * Sinkronisasi notifikasi bersuara di bilah status (sama seperti SISINDEN):
 *  - latar depan: dipanggil dari loop realtime saat jumlah belum-dibaca berubah;
 *  - latar belakang: [NotificationSyncWorker] lewat WorkManager (minimal 15 menit,
 *    bisa ditunda sistem saat hemat daya / Doze);
 *  - instan walau aplikasi ditutup: push FCM ([RdMessagingService]) bila admin sudah
 *    mengunggah kredensial Firebase di Pengaturan web.
 */
object NotificationSync {
    private const val WORK_NAME = "rd_notification_sync"

    suspend fun run(context: Context, api: ApiService): Int {
        val r = api.notifications(perPage = 20)
        if (!r.isSuccessful) return 0
        val items = r.body()?.data?.items.orEmpty().map {
            NotificationHelper.Item(
                id = it.id,
                title = it.title?.ifBlank { null } ?: "Ruang Dharma Studio",
                message = it.message?.ifBlank { null } ?: "Ada pemberitahuan baru.",
                isRead = it.isRead,
            )
        }
        return NotificationHelper.process(context, items)
    }

    /** Jadwalkan sinkron latar belakang (idempoten). */
    fun schedule(context: Context) {
        val request = PeriodicWorkRequestBuilder<NotificationSyncWorker>(15, TimeUnit.MINUTES)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .build()
        WorkManager.getInstance(context).enqueueUniquePeriodicWork(WORK_NAME, ExistingPeriodicWorkPolicy.KEEP, request)
    }

    fun cancel(context: Context) {
        WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
    }
}

class NotificationSyncWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        val token = try { TokenStore.tokenFlow(applicationContext).first() } catch (_: Exception) { null }
        if (token.isNullOrBlank()) return Result.success()
        return try {
            val api = RetrofitClient.create(applicationContext) { token }
            NotificationSync.run(applicationContext, api)
            Result.success()
        } catch (_: Exception) {
            Result.retry()
        }
    }
}
