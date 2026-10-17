package id.ruangdharma.studio.notify

import android.content.Context
import android.os.Build
import android.util.Log
import com.google.firebase.FirebaseApp
import com.google.firebase.FirebaseOptions
import com.google.firebase.messaging.FirebaseMessaging
import id.ruangdharma.studio.BuildConfig
import id.ruangdharma.studio.data.api.ApiService
import id.ruangdharma.studio.data.model.PushConfig
import kotlinx.coroutines.CoroutineScope
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.SupervisorJob
import kotlinx.coroutines.launch
import kotlin.coroutines.resume
import kotlin.coroutines.suspendCoroutine

/**
 * Push Firebase Cloud Messaging — seperti SISINDEN, konfigurasi klien Firebase dikirim
 * server (GET /api/mobile/config) setelah admin mengunggah google-services.json di
 * Pengaturan web, lalu disimpan di HP agar Firebase bisa diinisialisasi saat aplikasi
 * dibangunkan oleh pesan push. Tidak perlu google-services.json di dalam APK.
 */
object PushManager {
    private const val TAG = "RdPush"
    private const val PREFS = "rd_push"
    private const val K_API = "api_key"
    private const val K_APP = "app_id"
    private const val K_PROJECT = "project_id"
    private const val K_SENDER = "sender_id"
    private const val K_TOKEN = "last_token"

    private val scope = CoroutineScope(SupervisorJob() + Dispatchers.IO)

    private fun prefs(c: Context) = c.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun isReady(c: Context): Boolean = try { FirebaseApp.getApps(c).isNotEmpty() } catch (_: Exception) { false }

    /** Dipanggil di Application.onCreate: pakai konfigurasi tersimpan. */
    fun initFromCache(c: Context) {
        val p = prefs(c)
        val cfg = PushConfig(p.getString(K_API, null), p.getString(K_APP, null), p.getString(K_PROJECT, null), p.getString(K_SENDER, null))
        if (cfg.isComplete) init(c, cfg)
    }

    private fun init(c: Context, cfg: PushConfig): Boolean {
        if (isReady(c)) return true
        return try {
            val options = FirebaseOptions.Builder()
                .setApiKey(cfg.apiKey!!)
                .setApplicationId(cfg.appId!!)
                .setProjectId(cfg.projectId!!)
                .setGcmSenderId(cfg.senderId!!)
                .build()
            FirebaseApp.initializeApp(c.applicationContext, options)
            true
        } catch (e: Exception) {
            Log.w(TAG, "Firebase tidak dapat diinisialisasi: ${e.javaClass.simpleName}")
            false
        }
    }

    fun lastToken(c: Context): String? = prefs(c).getString(K_TOKEN, null)
    internal fun saveToken(c: Context, token: String) = prefs(c).edit().putString(K_TOKEN, token).apply()

    private suspend fun fetchToken(): String? = suspendCoroutine { cont ->
        try {
            FirebaseMessaging.getInstance().token
                .addOnSuccessListener { cont.resume(it) }
                .addOnFailureListener { cont.resume(null) }
        } catch (e: Exception) {
            cont.resume(null)
        }
    }

    /**
     * Ambil konfigurasi dari server, inisialisasi Firebase, lalu daftarkan token FCM
     * ke akun yang sedang masuk. Aman dipanggil berulang.
     * @return true bila push aktif di HP ini.
     */
    suspend fun setup(c: Context, api: ApiService): Boolean {
        try {
            val cfg = api.mobileConfig().body()?.push
            if (cfg != null && cfg.isComplete) {
                prefs(c).edit()
                    .putString(K_API, cfg.apiKey).putString(K_APP, cfg.appId)
                    .putString(K_PROJECT, cfg.projectId).putString(K_SENDER, cfg.senderId)
                    .apply()
                init(c, cfg)
            }
        } catch (_: Exception) { }
        if (!isReady(c)) return false
        val token = fetchToken() ?: return false
        saveToken(c, token)
        return register(api, token)
    }

    suspend fun register(api: ApiService, token: String): Boolean = try {
        api.registerPush(
            mapOf(
                "token" to token, "platform" to "android",
                "device_name" to "${Build.MANUFACTURER} ${Build.MODEL}".trim().take(120),
                "app_version" to BuildConfig.VERSION_NAME,
            ),
        ).isSuccessful
    } catch (_: Exception) { false }

    /** Saat logout: lepas token dari akun agar HP tidak menerima notifikasi akun lama. */
    suspend fun unregister(c: Context, api: ApiService) {
        val token = lastToken(c) ?: return
        try { api.unregisterPush(mapOf("token" to token)) } catch (_: Exception) { }
    }

    /** Token FCM baru (dipanggil dari service) → daftarkan bila ada sesi tersimpan. */
    fun onNewToken(c: Context, token: String, apiFactory: suspend () -> ApiService?) {
        saveToken(c, token)
        scope.launch { apiFactory()?.let { register(it, token) } }
    }
}
