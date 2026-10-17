package id.ruangdharma.studio.data.update

import android.app.NotificationManager
import android.app.PendingIntent
import android.content.BroadcastReceiver
import android.content.Context
import android.content.Intent
import android.content.pm.PackageInfo
import android.content.pm.PackageInstaller
import android.content.pm.PackageManager
import android.os.Build
import androidx.core.app.NotificationCompat
import androidx.work.Constraints
import androidx.work.CoroutineWorker
import androidx.work.ExistingPeriodicWorkPolicy
import androidx.work.ExistingWorkPolicy
import androidx.work.NetworkType
import androidx.work.OneTimeWorkRequestBuilder
import androidx.work.PeriodicWorkRequestBuilder
import androidx.work.WorkManager
import androidx.work.WorkerParameters
import com.google.gson.JsonParser
import id.ruangdharma.studio.BuildConfig
import id.ruangdharma.studio.MainActivity
import id.ruangdharma.studio.R
import id.ruangdharma.studio.RuangDharmaApplication
import id.ruangdharma.studio.notify.NotificationHelper
import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.flow.MutableStateFlow
import kotlinx.coroutines.flow.StateFlow
import kotlinx.coroutines.flow.asStateFlow
import kotlinx.coroutines.withContext
import okhttp3.OkHttpClient
import okhttp3.Request
import java.io.File
import java.io.IOException
import java.security.MessageDigest
import java.util.concurrent.TimeUnit

/** Data pembaruan versi yang tersedia di server / GitHub Releases. */
data class ReleaseInfo(
    val version: String,
    val versionCode: Int,
    val apkUrl: String,
    val apkName: String,
    val apkSize: Long,
    val sha256: String,
    val notes: String,
)

/** Status alur pembaruan untuk UI dan dialog prompt. */
sealed interface UpdateState {
    data object Idle : UpdateState
    data object Checking : UpdateState
    data class UpToDate(val version: String) : UpdateState
    data class Available(val info: ReleaseInfo) : UpdateState
    data class Downloading(val version: String, val percent: Int) : UpdateState
    data class Installing(val version: String) : UpdateState
    data class NeedsInstallPermission(val info: ReleaseInfo) : UpdateState
    data class Failed(val message: String, val info: ReleaseInfo? = null) : UpdateState
}

/**
 * Pembaruan aplikasi otomatis dari dalam aplikasi (Auto Update ala SISINDEN):
 * 1. Memeriksa versi baru ke server (/api/mobile/app-update)
 * 2. Mengunduh berkas APK dengan indikator progres
 * 3. Memverifikasi integritas SHA-256 dan sertifikat tanda tangan (keystore)
 * 4. Memasang lewat PackageInstaller Android (di Android 12+ tanpa konfirmasi manual).
 */
object AppUpdater {
    private const val DEFAULT_BASE_URL = "https://ruangdharma.my.id"
    private const val INFO_PATH = "/api/mobile/app-update"
    private const val PREFS = "rd_update_prefs"
    private const val K_AUTO = "auto_update"
    private const val K_LAST_CHECK = "last_check"
    private const val K_NOTIFIED = "notified_version"
    private const val K_DISMISSED = "dismissed_version"
    const val NOTIFICATION_ID = 0x0BDA7E

    private val _state = MutableStateFlow<UpdateState>(UpdateState.Idle)
    val state: StateFlow<UpdateState> = _state.asStateFlow()

    private val http by lazy {
        OkHttpClient.Builder()
            .connectTimeout(15, TimeUnit.SECONDS)
            .readTimeout(60, TimeUnit.SECONDS)
            .followRedirects(true)
            .build()
    }

    private fun prefs(context: Context) =
        context.applicationContext.getSharedPreferences(PREFS, Context.MODE_PRIVATE)

    fun isAutoEnabled(context: Context) = prefs(context).getBoolean(K_AUTO, true)

    fun setAutoEnabled(context: Context, enabled: Boolean) {
        prefs(context).edit().putBoolean(K_AUTO, enabled).apply()
        if (enabled) schedule(context) else WorkManager.getInstance(context).cancelUniqueWork(WORK_NAME)
    }

    fun dismissPrompt(context: Context, version: String) {
        prefs(context).edit().putString(K_DISMISSED, version).apply()
    }

    fun isPromptDismissed(context: Context, version: String) =
        prefs(context).getString(K_DISMISSED, null) == version

    // ------------------------------------------------------------------ versi

    /** Bandingkan versi numerik: "1.3.0" vs "1.2.1". Mengabaikan awalan 'v' dan suffix debug. */
    fun compareVersions(a: String, b: String): Int {
        fun parts(v: String) = v.trim().removePrefix("v").substringBefore('-').split('.').map { it.toIntOrNull() ?: 0 }
        val x = parts(a)
        val y = parts(b)
        for (i in 0 until maxOf(x.size, y.size)) {
            val c = (x.getOrElse(i) { 0 }).compareTo(y.getOrElse(i) { 0 })
            if (c != 0) return c
        }
        return 0
    }

    /** Parsing JSON respons dari server. */
    fun parseServer(json: String, base: String = DEFAULT_BASE_URL): ReleaseInfo? = try {
        val el = JsonParser.parseString(json)
        if (!el.isJsonObject) null
        else {
            val obj = el.asJsonObject
            if (obj.get("available")?.takeIf { !it.isJsonNull }?.asBoolean != true) null
            else {
                val version = obj.get("version")?.takeIf { !it.isJsonNull }?.asString?.trim().orEmpty()
                if (!Regex("^\\d+(\\.\\d+){1,3}$").matches(version)) null
                else {
                    val sha = obj.get("sha256")?.takeIf { !it.isJsonNull }?.asString?.lowercase().orEmpty()
                    if (!Regex("^[0-9a-f]{64}$").matches(sha)) null
                    else {
                        val root = base.trimEnd('/')
                        val raw = obj.get("download_url")?.takeIf { !it.isJsonNull }?.asString.orEmpty()
                        val url = when {
                            raw.startsWith("http://") || raw.startsWith("https://") -> raw
                            raw.startsWith("$root/") -> raw
                            raw.startsWith("/") -> root + raw
                            else -> "$root/api/mobile/app-update/download?v=$version"
                        }
                        ReleaseInfo(
                            version = version,
                            versionCode = obj.get("version_code")?.takeIf { !it.isJsonNull }?.asInt ?: 0,
                            apkUrl = url,
                            apkName = "RUANGDHARMA-$version.apk",
                            apkSize = obj.get("size")?.takeIf { !it.isJsonNull }?.asLong ?: 0L,
                            sha256 = sha,
                            notes = obj.get("notes")?.takeIf { !it.isJsonNull }?.asString.orEmpty(),
                        )
                    }
                }
            }
        }
    } catch (_: Exception) {
        null
    }

    suspend fun fetchLatest(base: String = DEFAULT_BASE_URL): ReleaseInfo? = withContext(Dispatchers.IO) {
        val root = base.trimEnd('/')
        val req = Request.Builder()
            .url(root + INFO_PATH)
            .header("Accept", "application/json")
            .header("User-Agent", "RuangDharma-Android/${BuildConfig.VERSION_NAME}")
            .build()
        http.newCall(req).execute().use { res ->
            if (res.code == 429) throw UpdateException("Terlalu sering memeriksa. Coba lagi sebentar lagi.")
            if (!res.isSuccessful) throw IOException("server menjawab HTTP ${res.code}")
            parseServer(res.body?.string().orEmpty(), root)
        }
    }

    private fun isNewer(info: ReleaseInfo?): Boolean =
        info != null && compareVersions(info.version, BuildConfig.VERSION_NAME) > 0

    // ------------------------------------------------------------------ alur kerja

    /** Periksa pembaruan sekarang. */
    suspend fun check(context: Context): UpdateState {
        val app = context.applicationContext
        if (isBusy()) return _state.value
        _state.value = UpdateState.Checking
        val result = try {
            val latest = fetchLatest()
            prefs(app).edit().putLong(K_LAST_CHECK, System.currentTimeMillis()).apply()
            if (isNewer(latest)) UpdateState.Available(latest!!) else UpdateState.UpToDate(BuildConfig.VERSION_NAME)
        } catch (e: UpdateException) {
            UpdateState.Failed(e.message ?: "Pemeriksaan gagal.")
        } catch (e: kotlinx.coroutines.CancellationException) {
            throw e
        } catch (e: Exception) {
            UpdateState.Failed("Tidak dapat memeriksa pembaruan: ${reason(e)}")
        }
        _state.value = result
        return result
    }

    /** Unduh -> Verifikasi -> Pasang. */
    suspend fun downloadAndInstall(context: Context, info: ReleaseInfo): UpdateState {
        val app = context.applicationContext
        if (_state.value is UpdateState.Downloading || _state.value is UpdateState.Installing) return _state.value
        _state.value = UpdateState.Downloading(info.version, 0)
        val result = try {
            val apk = try {
                download(app, info)
            } catch (e: UpdateException) {
                throw e
            } catch (e: Exception) {
                throw UpdateException("Unduhan v${info.version} gagal: ${reason(e)}")
            }
            verify(app, apk, info)
            when {
                Build.VERSION.SDK_INT >= Build.VERSION_CODES.O && !app.packageManager.canRequestPackageInstalls() ->
                    UpdateState.NeedsInstallPermission(info)
                else -> {
                    try {
                        install(app, apk, info.version)
                    } catch (e: Exception) {
                        throw UpdateException("Pemasangan v${info.version} gagal: ${reason(e)}")
                    }
                    UpdateState.Installing(info.version)
                }
            }
        } catch (e: UpdateException) {
            UpdateState.Failed(e.message ?: "Pembaruan gagal.", info)
        } catch (e: kotlinx.coroutines.CancellationException) {
            _state.value = UpdateState.Available(info)
            throw e
        } catch (e: Exception) {
            UpdateState.Failed("Pembaruan gagal: ${reason(e)}", info)
        }
        _state.value = result
        if (result is UpdateState.NeedsInstallPermission && !RuangDharmaApplication.isAppForeground) {
            notify(app, "Pembaruan Ruang Dharma ${info.version} siap dipasang", "Buka Profil → Pembaruan aplikasi untuk mengizinkan pemasangan.", openIntent(app))
        }
        return result
    }

    suspend fun backgroundCheck(context: Context, allowDownload: Boolean): UpdateState {
        val app = context.applicationContext
        val result = check(app)
        if (result !is UpdateState.Available) {
            if (result is UpdateState.UpToDate) _state.value = UpdateState.Idle
            return result
        }
        val info = result.info
        return if (allowDownload && isAutoEnabled(app)) {
            downloadAndInstall(app, info)
        } else {
            notifyAvailable(app, info)
            result
        }
    }

    fun notifyAvailable(context: Context, info: ReleaseInfo, force: Boolean = false) {
        val p = prefs(context)
        if (!force && p.getString(K_NOTIFIED, null) == info.version) return
        p.edit().putString(K_NOTIFIED, info.version).apply()
        val text = info.notes.ifBlank { "Ketuk untuk mengunduh dan memasang versi terbaru." }
        notify(context, "Pembaruan Ruang Dharma ${info.version} tersedia", text, openIntent(context))
    }

    fun onServerPush(context: Context, version: String, title: String, body: String) {
        val app = context.applicationContext
        if (version.isNotBlank() && compareVersions(version, BuildConfig.VERSION_NAME) <= 0) return
        prefs(app).edit().putString(K_NOTIFIED, version).apply()
        notify(app, title.ifBlank { "Pembaruan Ruang Dharma $version tersedia" }, body.ifBlank { "Ketuk untuk mengunduh dan memasang versi terbaru." }, openIntent(app))
        if (isAutoEnabled(app) && !BuildConfig.DEBUG) {
            val req = OneTimeWorkRequestBuilder<UpdateWorker>()
                .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.UNMETERED).build())
                .build()
            WorkManager.getInstance(app).enqueueUniqueWork(WORK_NOW, ExistingWorkPolicy.REPLACE, req)
        }
    }

    private fun isBusy() = _state.value.let { it is UpdateState.Checking || it is UpdateState.Downloading || it is UpdateState.Installing }

    internal fun reason(e: Throwable): String = when (e) {
        is java.net.UnknownHostException -> "tidak ada koneksi internet ke server"
        is java.net.SocketTimeoutException -> "koneksi terlalu lama (timeout)"
        is javax.net.ssl.SSLException -> "koneksi aman (HTTPS) gagal — periksa tanggal & jam HP"
        is SecurityException -> "izin pemasangan ditolak Android"
        is IOException -> e.message?.takeIf { it.isNotBlank() } ?: "gangguan jaringan"
        else -> e.javaClass.simpleName + (e.message?.let { ": $it" } ?: "")
    }

    fun installPermissionIntent(context: Context): Intent =
        Intent(android.provider.Settings.ACTION_MANAGE_UNKNOWN_APP_SOURCES, android.net.Uri.parse("package:${context.packageName}"))
            .addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)

    suspend fun autoCheckOnOpen(context: Context) {
        if (BuildConfig.DEBUG) return
        val last = prefs(context).getLong(K_LAST_CHECK, 0L)
        if (System.currentTimeMillis() - last < 30 * 60_000L && _state.value !is UpdateState.Available) return
        val r = check(context)
        if (r is UpdateState.UpToDate) _state.value = UpdateState.Idle
    }

    class UpdateException(message: String) : Exception(message)

    private fun openIntent(context: Context): PendingIntent {
        val intent = Intent(context, MainActivity::class.java).apply {
            flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TOP
            putExtra(NotificationHelper.EXTRA_OPEN, "profile")
        }
        return PendingIntent.getActivity(
            context,
            NOTIFICATION_ID,
            intent,
            PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
        )
    }

    private suspend fun download(context: Context, info: ReleaseInfo): File = withContext(Dispatchers.IO) {
        val dir = File(context.cacheDir, "updates").apply { mkdirs() }
        dir.listFiles()?.filter { !it.name.startsWith(info.apkName) }?.forEach { it.delete() }
        val target = File(dir, info.apkName)
        if (target.exists() && sha256(target) == info.sha256) {
            return@withContext target
        }
        val part = File(dir, info.apkName + ".part")
        val req = Request.Builder()
            .url(info.apkUrl)
            .header("User-Agent", "RuangDharma-Android/${BuildConfig.VERSION_NAME}")
            .build()
        http.newCall(req).execute().use { res ->
            if (res.code == 404) throw UpdateException("Berkas pembaruan tidak ditemukan di server.")
            if (!res.isSuccessful) throw UpdateException("Unduhan pembaruan gagal (HTTP ${res.code}).")
            val body = res.body ?: throw UpdateException("Berkas pembaruan kosong.")
            val total = body.contentLength().takeIf { it > 0 } ?: info.apkSize
            var done = 0L
            var lastPercent = -1
            body.byteStream().use { input ->
                part.outputStream().use { out ->
                    val buf = ByteArray(64 * 1024)
                    while (true) {
                        val n = input.read(buf)
                        if (n < 0) break
                        out.write(buf, 0, n)
                        done += n
                        val percent = if (total > 0) ((done * 100) / total).toInt().coerceIn(0, 100) else 0
                        if (percent != lastPercent) {
                            lastPercent = percent
                            _state.value = UpdateState.Downloading(info.version, percent)
                        }
                    }
                }
            }
        }
        if (target.exists()) target.delete()
        if (!part.renameTo(target)) throw UpdateException("Berkas pembaruan tidak dapat disimpan.")
        target
    }

    private suspend fun verify(context: Context, apk: File, info: ReleaseInfo) = withContext(Dispatchers.IO) {
        // 1) Integritas SHA-256
        if (sha256(apk) != info.sha256) {
            apk.delete()
            throw UpdateException("Berkas pembaruan rusak (SHA-256 tidak cocok). Coba unduh lagi.")
        }
        // 2) Identitas paket & kesamaan kunci tanda tangan (keystore)
        val pm = context.packageManager
        val archive = archiveInfo(pm, apk.absolutePath)
            ?: throw UpdateException("Berkas pembaruan bukan APK Android yang valid.")
        if (archive.packageName != context.packageName) {
            throw UpdateException("Berkas pembaruan bukan untuk aplikasi Ruang Dharma Studio.")
        }
        val installed = installedInfo(pm, context.packageName)
        if (installed != null && signerDigests(archive).intersect(signerDigests(installed)).isEmpty()) {
            apk.delete()
            throw UpdateException(
                "APK v${info.version} ditandatangani dengan kunci yang berbeda dari aplikasi yang terpasang. Pastikan menggunakan APK rilis resmi."
            )
        }
    }

    private fun sha256(file: File): String {
        val md = MessageDigest.getInstance("SHA-256")
        file.inputStream().use { input ->
            val buf = ByteArray(64 * 1024)
            while (true) {
                val n = input.read(buf)
                if (n < 0) break
                md.update(buf, 0, n)
            }
        }
        return md.digest().joinToString("") { "%02x".format(it) }
    }

    @Suppress("DEPRECATION")
    private fun archiveInfo(pm: PackageManager, path: String): PackageInfo? {
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) PackageManager.GET_SIGNING_CERTIFICATES else PackageManager.GET_SIGNATURES
        return pm.getPackageArchiveInfo(path, flags)
    }

    @Suppress("DEPRECATION")
    private fun installedInfo(pm: PackageManager, pkg: String): PackageInfo? = try {
        val flags = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) PackageManager.GET_SIGNING_CERTIFICATES else PackageManager.GET_SIGNATURES
        pm.getPackageInfo(pkg, flags)
    } catch (_: PackageManager.NameNotFoundException) {
        null
    }

    @Suppress("DEPRECATION")
    private fun signerDigests(info: PackageInfo): Set<String> {
        val sigs = if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.P) {
            val si = info.signingInfo ?: return emptySet()
            if (si.hasMultipleSigners()) si.apkContentsSigners else si.signingCertificateHistory
        } else {
            info.signatures
        }
        return sigs.orEmpty().map { sig ->
            MessageDigest.getInstance("SHA-256").digest(sig.toByteArray()).joinToString("") { "%02x".format(it) }
        }.toSet()
    }

    private suspend fun install(context: Context, apk: File, version: String) = withContext(Dispatchers.IO) {
        val installer = context.packageManager.packageInstaller
        val params = PackageInstaller.SessionParams(PackageInstaller.SessionParams.MODE_FULL_INSTALL).apply {
            setAppPackageName(context.packageName)
            setSize(apk.length())
            if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) {
                // Di Android 12+, update tanpa konfirmasi pengguna bila izin diberikan
                setRequireUserAction(PackageInstaller.SessionParams.USER_ACTION_NOT_REQUIRED)
            }
        }
        val sessionId = installer.createSession(params)
        installer.openSession(sessionId).use { session ->
            apk.inputStream().use { input ->
                session.openWrite("base.apk", 0, apk.length()).use { out ->
                    input.copyTo(out, 64 * 1024)
                    session.fsync(out)
                }
            }
            val intent = Intent(context, UpdateInstallReceiver::class.java).putExtra(UpdateInstallReceiver.EXTRA_VERSION, version)
            val flags = PendingIntent.FLAG_UPDATE_CURRENT or
                (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.S) PendingIntent.FLAG_MUTABLE else 0)
            val pi = PendingIntent.getBroadcast(context, sessionId, intent, flags)
            session.commit(pi.intentSender)
        }
    }

    internal fun setState(value: UpdateState) {
        _state.value = value
    }

    internal fun notify(context: Context, title: String, text: String, intent: PendingIntent?) {
        NotificationHelper.createChannels(context)
        val n = NotificationCompat.Builder(context, NotificationHelper.CHANNEL_GENERAL)
            .setSmallIcon(R.drawable.ic_stat_rd)
            .setColor(0xFF7C3AED.toInt())
            .setContentTitle(title)
            .setContentText(text)
            .setStyle(NotificationCompat.BigTextStyle().bigText(text))
            .setPriority(NotificationCompat.PRIORITY_HIGH)
            .setAutoCancel(true)
            .apply { if (intent != null) setContentIntent(intent) }
            .build()
        try {
            context.getSystemService(NotificationManager::class.java).notify(NOTIFICATION_ID, n)
        } catch (_: SecurityException) {}
    }

    // ------------------------------------------------------------------ latar belakang

    private const val WORK_NAME = "rd_app_update_periodic"
    private const val WORK_NOW = "rd_app_update_now"

    fun schedule(context: Context) {
        if (BuildConfig.DEBUG) return
        val wm = WorkManager.getInstance(context)
        val req = PeriodicWorkRequestBuilder<UpdateWorker>(6, TimeUnit.HOURS)
            .setConstraints(Constraints.Builder().setRequiredNetworkType(NetworkType.CONNECTED).build())
            .build()
        wm.enqueueUniquePeriodicWork(WORK_NAME, ExistingPeriodicWorkPolicy.KEEP, req)
    }

    fun isUnmetered(context: Context): Boolean {
        val cm = context.getSystemService(android.net.ConnectivityManager::class.java) ?: return false
        return !cm.isActiveNetworkMetered
    }

    fun onPackageReplaced(context: Context) {
        File(context.cacheDir, "updates").listFiles()?.forEach { it.delete() }
        _state.value = UpdateState.Idle
        notify(
            context,
            "Ruang Dharma diperbarui",
            "Versi ${BuildConfig.VERSION_NAME} berhasil terpasang. Ketuk untuk membuka studio.",
            openIntent(context)
        )
    }
}

/** Pekerja latar belakang untuk memeriksa pembaruan berkala. */
class UpdateWorker(context: Context, params: WorkerParameters) : CoroutineWorker(context, params) {
    override suspend fun doWork(): Result {
        AppUpdater.backgroundCheck(applicationContext, allowDownload = AppUpdater.isUnmetered(applicationContext))
        return Result.success()
    }
}

/** Penerima hasil status sesi PackageInstaller. */
class UpdateInstallReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        val version = intent.getStringExtra(EXTRA_VERSION).orEmpty()
        when (intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)) {
            PackageInstaller.STATUS_PENDING_USER_ACTION -> {
                @Suppress("DEPRECATION")
                val confirm: Intent = (if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
                    intent.getParcelableExtra(Intent.EXTRA_INTENT, Intent::class.java)
                } else {
                    intent.getParcelableExtra(Intent.EXTRA_INTENT)
                }) ?: return
                confirm.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK)
                if (RuangDharmaApplication.isAppForeground) {
                    try {
                        context.startActivity(confirm)
                        return
                    } catch (_: Exception) {}
                }
                val pi = PendingIntent.getActivity(
                    context,
                    AppUpdater.NOTIFICATION_ID,
                    confirm,
                    PendingIntent.FLAG_UPDATE_CURRENT or PendingIntent.FLAG_IMMUTABLE
                )
                AppUpdater.notify(context, "Pembaruan Ruang Dharma $version siap", "Ketuk untuk memasang pembaruan.", pi)
            }
            PackageInstaller.STATUS_SUCCESS -> AppUpdater.setState(UpdateState.UpToDate(version))
            else -> {
                val msg = intent.getStringExtra(PackageInstaller.EXTRA_STATUS_MESSAGE).orEmpty()
                val status = intent.getIntExtra(PackageInstaller.EXTRA_STATUS, PackageInstaller.STATUS_FAILURE)
                val text = when (status) {
                    PackageInstaller.STATUS_FAILURE_ABORTED -> "Pemasangan dibatalkan."
                    PackageInstaller.STATUS_FAILURE_CONFLICT ->
                        "Pembaruan bentrok dengan aplikasi terpasang (tanda tangan berbeda). Pasang APK resmi versi $version."
                    PackageInstaller.STATUS_FAILURE_STORAGE -> "Ruang penyimpanan HP tidak cukup."
                    else -> "Pemasangan gagal${if (msg.isNotBlank()) ": $msg" else "."}"
                }
                AppUpdater.setState(UpdateState.Failed(text))
                if (!RuangDharmaApplication.isAppForeground && status != PackageInstaller.STATUS_FAILURE_ABORTED) {
                    AppUpdater.notify(context, "Pembaruan Ruang Dharma gagal", text, null)
                }
            }
        }
    }

    companion object {
        const val EXTRA_VERSION = "version"
    }
}

/** Penerima broadcast MY_PACKAGE_REPLACED saat APK baru selesai terpasang. */
class UpdatePackageReplacedReceiver : BroadcastReceiver() {
    override fun onReceive(context: Context, intent: Intent) {
        if (intent.action == Intent.ACTION_MY_PACKAGE_REPLACED) {
            AppUpdater.onPackageReplaced(context)
        }
    }
}
