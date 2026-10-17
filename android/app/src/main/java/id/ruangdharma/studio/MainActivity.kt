package id.ruangdharma.studio

import android.graphics.Color as AColor
import android.Manifest
import android.content.Intent
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import androidx.activity.ComponentActivity
import androidx.activity.SystemBarStyle
import androidx.activity.compose.rememberLauncherForActivityResult
import androidx.activity.compose.setContent
import androidx.activity.result.contract.ActivityResultContracts
import androidx.activity.enableEdgeToEdge
import androidx.compose.animation.AnimatedVisibility
import androidx.compose.animation.core.RepeatMode
import androidx.compose.animation.core.animateFloat
import androidx.compose.animation.core.infiniteRepeatable
import androidx.compose.animation.core.rememberInfiniteTransition
import androidx.compose.animation.core.tween
import androidx.compose.animation.fadeIn
import androidx.compose.animation.fadeOut
import androidx.compose.animation.slideInVertically
import androidx.compose.animation.slideOutVertically
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.isSystemInDarkTheme
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.alpha
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.navigation.NavType
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import androidx.navigation.navArgument
import com.google.gson.JsonObject
import id.ruangdharma.studio.data.api.RetrofitClient
import id.ruangdharma.studio.data.api.TokenStore
import id.ruangdharma.studio.data.model.*
import id.ruangdharma.studio.notify.NotificationHelper
import id.ruangdharma.studio.notify.NotificationSync
import id.ruangdharma.studio.notify.PushManager
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.screens.*
import id.ruangdharma.studio.ui.theme.*
import kotlinx.coroutines.delay
import kotlinx.coroutines.flow.first
import kotlinx.coroutines.isActive
import kotlinx.coroutines.launch
import org.json.JSONObject
import retrofit2.Response
import java.io.IOException

class MainActivity : ComponentActivity() {
    /** Layar yang diminta dibuka dari ketukan notifikasi (mis. "notifications"). */
    private val openRequest = mutableStateOf<String?>(null)

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge()
        openRequest.value = intent?.getStringExtra(NotificationHelper.EXTRA_OPEN)
        setContent { RuangDharmaApp(openRequest.value) { openRequest.value = null } }
    }

    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        setIntent(intent)
        intent.getStringExtra(NotificationHelper.EXTRA_OPEN)?.let { openRequest.value = it }
    }
}

/** Ambil pesan error dari body server ({"message"} atau {"errors": {...}}), fallback per kode HTTP. */
fun parseServerError(code: Int, raw: String?, fallback: String = "Permintaan gagal"): String {
    if (!raw.isNullOrBlank()) {
        try {
            val json = JSONObject(raw)
            val errors = json.optJSONObject("errors")
            if (errors != null) {
                val keys = errors.keys()
                while (keys.hasNext()) {
                    val arr = errors.optJSONArray(keys.next())
                    if (arr != null && arr.length() > 0) return arr.optString(0)
                }
            }
            json.optString("message").takeIf { it.isNotBlank() }?.let { return it }
        } catch (_: Exception) { }
    }
    return when (code) {
        429 -> "Terlalu banyak percobaan. Coba lagi beberapa menit."
        403 -> "Akses ditolak / akun dinonaktifkan. Hubungi admin."
        401, 422 -> "Email atau password salah."
        in 500..599 -> "Server sedang bermasalah (HTTP $code). Coba lagi sebentar."
        else -> "$fallback (HTTP $code)."
    }
}

fun networkMessage(e: Exception): String = when (e) {
    is com.google.gson.JsonParseException, is IllegalStateException, is NumberFormatException ->
        "Format data dari server tidak dikenali. Tarik ke bawah untuk memuat ulang."
    is IOException -> "Tidak bisa terhubung ke server. Periksa koneksi internet lalu coba lagi."
    else -> e.message ?: "Terjadi kesalahan."
}

private fun Response<JsonObject>.message(fallback: String): String {
    if (isSuccessful) return body()?.get("message")?.takeIf { !it.isJsonNull }?.asString ?: fallback
    val raw = try { errorBody()?.string() } catch (_: Exception) { null }
    return parseServerError(code(), raw, fallback)
}

private data class NavItem(val route: String, val label: String, val icon: ImageVector)

private val NAV = listOf(
    NavItem("home", "Beranda", Icons.Filled.Home),
    NavItem("history", "Riwayat", Icons.Filled.History),
    NavItem("generate", "Buat", Icons.Filled.AutoFixHigh),
    NavItem("social", "Medsos", Icons.Filled.Share),
    NavItem("profile", "Profil", Icons.Filled.Person),
)

@Composable
fun RuangDharmaApp(openRequest: String? = null, onOpenHandled: () -> Unit = {}) {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    val mainHandler = remember { Handler(Looper.getMainLooper()) }
    val systemDark = isSystemInDarkTheme()
    val darkPref by TokenStore.darkFlow(context).collectAsState(initial = null)
    val dark = darkPref ?: systemDark

    var booting by remember { mutableStateOf(true) }
    var token by remember { mutableStateOf<String?>(null) }
    var user by remember { mutableStateOf<User?>(null) }
    var loginLoading by remember { mutableStateOf(false) }
    var loginError by remember { mutableStateOf<String?>(null) }

    var quota by remember { mutableStateOf<PulseQuota?>(null) }
    var counts by remember { mutableStateOf<Counts?>(null) }
    var generations by remember { mutableStateOf<List<Generation>>(emptyList()) }
    var loading by remember { mutableStateOf(false) }
    var refreshing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }

    var historyTab by remember { mutableIntStateOf(0) }
    var historySearch by remember { mutableStateOf("") }
    var historyItems by remember { mutableStateOf<List<Generation>>(emptyList()) }
    var historyLoading by remember { mutableStateOf(false) }
    var historyError by remember { mutableStateOf<String?>(null) }

    var socialStats by remember { mutableStateOf<Map<String, StatsResponse>>(emptyMap()) }
    var socialPublishes by remember { mutableStateOf<List<SocialPublish>>(emptyList()) }
    var socialRefreshing by remember { mutableStateOf(false) }
    var tokenUsage by remember { mutableStateOf<TokenUsage?>(null) }
    var usageError by remember { mutableStateOf<String?>(null) }

    var detailGen by remember { mutableStateOf<Generation?>(null) }
    var notifItems by remember { mutableStateOf<List<AppNotification>>(emptyList()) }
    var notifLoading by remember { mutableStateOf(false) }
    var unread by remember { mutableIntStateOf(0) }
    var pushActive by remember { mutableStateOf(false) }
    var submitting by remember { mutableStateOf(false) }
    var submitResult by remember { mutableStateOf<SubmitResult?>(null) }
    var ideaKeyword by remember { mutableStateOf("") }
    var ideaModel by remember { mutableStateOf("") }

    val snackbar = remember { SnackbarHostState() }
    fun toast(msg: String) { scope.launch { snackbar.currentSnackbarData?.dismiss(); snackbar.showSnackbar(msg) } }

    fun clearSession(message: String? = null) {
        token = null; user = null; quota = null; counts = null
        generations = emptyList(); historyItems = emptyList(); socialStats = emptyMap(); socialPublishes = emptyList()
        tokenUsage = null; detailGen = null; submitResult = null
        notifItems = emptyList(); unread = 0; pushActive = false
        loginError = message
        NotificationSync.cancel(context)
        NotificationHelper.reset(context)
        scope.launch { TokenStore.save(context, null) }
    }

    // Satu klien API; token dibaca saat request, 401 → kembali ke login.
    val api = remember {
        RetrofitClient.create(context, onUnauthorized = {
            mainHandler.post { if (token != null) clearSession("Sesi berakhir. Silakan login kembali.") }
        }) { token }
    }

    // Izin notifikasi Android 13+ (seperti SISINDEN: diminta setelah login).
    val notifPermission = rememberLauncherForActivityResult(ActivityResultContracts.RequestPermission()) { granted ->
        if (!granted) toast("Izin notifikasi ditolak. Aktifkan di Profil → Notifikasi HP agar notifikasi muncul.")
    }
    fun requestNotifPermission() {
        if (Build.VERSION.SDK_INT >= 33 && !NotificationHelper.hasPermission(context)) notifPermission.launch(Manifest.permission.POST_NOTIFICATIONS)
    }

    // Warna ikon status bar mengikuti mode terang/gelap aplikasi.
    LaunchedEffect(dark) {
        (context as? ComponentActivity)?.enableEdgeToEdge(
            statusBarStyle = if (dark) SystemBarStyle.dark(AColor.TRANSPARENT) else SystemBarStyle.light(AColor.TRANSPARENT, AColor.TRANSPARENT),
            navigationBarStyle = if (dark) SystemBarStyle.dark(AColor.TRANSPARENT) else SystemBarStyle.light(AColor.TRANSPARENT, AColor.TRANSPARENT),
        )
    }

    suspend fun loadMe() {
        try {
            val r = api.me()
            if (r.isSuccessful) r.body()?.resolvedUser()?.let { user = it }
        } catch (_: Exception) { }
    }
    suspend fun loadCounts() { try { api.counts().body()?.data?.let { counts = it } } catch (_: Exception) { } }
    suspend fun loadQuota() {
        try { api.pulse().body()?.quota?.let { quota = it } } catch (_: Exception) { }
    }
    suspend fun loadGenerations() {
        if (generations.isEmpty()) loading = true
        try {
            val r = api.generations()
            if (r.isSuccessful) { generations = r.body()?.data?.items ?: emptyList(); error = null }
            else error = parseServerError(r.code(), try { r.errorBody()?.string() } catch (_: Exception) { null }, "Gagal memuat")
        } catch (e: Exception) { error = networkMessage(e) }
        loading = false
    }
    suspend fun loadHistory() {
        if (historyItems.isEmpty()) historyLoading = true
        try {
            val r = when (historyTab) {
                1 -> api.generations(favorites = "1", archived = "all", search = historySearch.ifBlank { null })
                2 -> api.generations(archived = "true", search = historySearch.ifBlank { null })
                else -> api.generations(archived = "false", search = historySearch.ifBlank { null })
            }
            if (r.isSuccessful) { historyItems = r.body()?.data?.items ?: emptyList(); historyError = null }
            else historyError = parseServerError(r.code(), try { r.errorBody()?.string() } catch (_: Exception) { null }, "Gagal memuat")
        } catch (e: Exception) { historyError = networkMessage(e) }
        historyLoading = false
    }
    suspend fun loadDetail() {
        val id = detailGen?.id ?: return
        try { api.generation(id).body()?.data?.let { detailGen = it } } catch (_: Exception) { }
    }
    suspend fun loadSocial() {
        val map = mutableMapOf<String, StatsResponse>()
        for (p in listOf("instagram", "facebook")) {
            try { api.socialStats(p).body()?.let { map[p] = it } } catch (_: Exception) { }
        }
        if (map.isNotEmpty()) socialStats = map
        try { api.socialPublishes().body()?.data?.items?.let { socialPublishes = it } } catch (_: Exception) { }
    }
    suspend fun loadUsage() {
        if (user?.isAdmin != true) return
        try {
            val r = api.tokenUsage()
            if (r.isSuccessful) { tokenUsage = r.body()?.data; usageError = null }
            else usageError = parseServerError(r.code(), try { r.errorBody()?.string() } catch (_: Exception) { null }, "Gagal memuat")
        } catch (e: Exception) { usageError = networkMessage(e) }
    }
    suspend fun loadNotifications() {
        if (notifItems.isEmpty()) notifLoading = true
        try {
            val r = api.notifications()
            if (r.isSuccessful) r.body()?.let { notifItems = it.data?.items.orEmpty(); unread = it.unreadCount }
        } catch (_: Exception) { }
        notifLoading = false
    }
    suspend fun syncSystemNotifications() {
        try { NotificationSync.run(context, api) } catch (_: Exception) { }
    }
    suspend fun reloadGenerations() { loadGenerations(); loadHistory(); loadCounts(); loadDetail() }

    // 1) Pulihkan sesi tersimpan saat app dibuka (tidak perlu login ulang), validasi lewat /me.
    LaunchedEffect(Unit) {
        val saved = try { TokenStore.tokenFlow(context).first() } catch (_: Exception) { null }
        if (!saved.isNullOrBlank()) {
            token = saved
            try {
                val r = api.me()
                when {
                    r.isSuccessful -> user = r.body()?.resolvedUser()
                    r.code() == 401 -> clearSession()
                }
            } catch (_: Exception) { /* offline: tetap masuk, data dimuat ulang saat online */ }
        }
        booting = false
    }

    // 2) Realtime: muat semua data lalu pantau /realtime/pulse tiap 5 detik — tanpa refresh manual.
    LaunchedEffect(token) {
        if (token == null) return@LaunchedEffect
        if (user == null) loadMe()
        launch { loadGenerations() }
        launch { loadCounts() }
        launch { loadQuota() }
        launch { loadSocial() }
        launch { loadUsage() }
        // Notifikasi HP: penanda awal, sinkron latar belakang, push FCM, izin Android 13+.
        launch { loadNotifications(); syncSystemNotifications() }
        NotificationSync.schedule(context)
        launch { pushActive = PushManager.setup(context, api) }
        requestNotifPermission()
        var stamps: Map<String, String>? = null
        var lastUnread = -1
        var tick = 0
        while (isActive && token != null) {
            delay(5000)
            tick++
            try {
                val r = api.pulse()
                val p = r.body() ?: continue
                if (!p.active) { clearSession("Akun dinonaktifkan. Hubungi admin."); break }
                p.quota?.let { quota = it }
                if (p.unread != lastUnread) {
                    // Ada notifikasi baru/dibaca → bunyikan banner di bilah status & segarkan daftar.
                    if (lastUnread >= 0) syncSystemNotifications()
                    lastUnread = p.unread
                    unread = p.unread
                    loadNotifications()
                }
                val now = p.stamps.orEmpty()
                val old = stamps
                if (old != null) {
                    if (now["generations"] != old["generations"] || now["review"] != old["review"]) reloadGenerations()
                    if (now["me"] != old["me"]) loadMe()
                    if (now["muse"] != old["muse"]) loadUsage()
                }
                stamps = now
                if (tick % 6 == 0) loadSocial() // statistik medsos tiap ±30 detik
                if (generations.any { it.isPendingMuse } && tick % 2 == 0) reloadGenerations()
            } catch (_: Exception) { }
        }
    }

    // Pencarian & tab Riwayat (debounce seperti input pencarian web).
    LaunchedEffect(token, historyTab, historySearch) {
        if (token == null) return@LaunchedEffect
        delay(if (historySearch.isBlank()) 0 else 350)
        loadHistory()
    }

    RuangDharmaTheme(darkTheme = dark) {
        val c = Rd.colors
        val toggleDark: () -> Unit = { scope.launch { TokenStore.saveDark(context, !dark) } }

        if (booting) { Splash(); return@RuangDharmaTheme }

        if (token == null) {
            LoginScreen(
                onLogin = { email, pass ->
                    scope.launch {
                        loginLoading = true; loginError = null
                        try {
                            val r = api.login(mapOf("email" to email, "password" to pass))
                            val body = r.body()
                            // Server mengirim token di level ATAS (body.token); body.data.token sebagai cadangan.
                            val t = body?.resolvedToken()
                            if (r.isSuccessful && !t.isNullOrBlank()) {
                                user = body?.resolvedUser()
                                TokenStore.save(context, t)
                                token = t
                                toast("Om Swastyastu, ${user?.firstName ?: "Sahabat"}!")
                            } else if (r.isSuccessful) {
                                loginError = "Server tidak mengirim token login. Pastikan web sudah versi terbaru."
                            } else {
                                val raw = try { r.errorBody()?.string() } catch (_: Exception) { null }
                                loginError = parseServerError(r.code(), raw, "Login gagal")
                            }
                        } catch (e: Exception) { loginError = networkMessage(e) }
                        loginLoading = false
                    }
                },
                loading = loginLoading, error = loginError, dark = dark, onToggleDark = toggleDark,
            )
            return@RuangDharmaTheme
        }

        val nav = rememberNavController()
        // Ketukan notifikasi di bilah status → buka layar Notifikasi.
        LaunchedEffect(openRequest) {
            if (openRequest == "notifications") { nav.navigate("notifications") { launchSingleTop = true }; onOpenHandled() }
        }
        val backStack by nav.currentBackStackEntryAsState()
        val route = backStack?.destination?.route ?: "home"
        fun go(r: String) {
            nav.navigate(r) { popUpTo("home") { saveState = true }; launchSingleTop = true; restoreState = true }
        }
        fun open(g: Generation) { detailGen = g; nav.navigate("detail") }
        fun toggleFav(g: Generation) {
            scope.launch {
                try {
                    val r = api.toggleFavorite(g.id)
                    if (r.isSuccessful) { r.body()?.data?.let { if (detailGen?.id == it.id) detailGen = it }; toast(if (g.isFavorite) "Favorit dibatalkan." else "Ditandai favorit."); reloadGenerations() }
                    else toast("Gagal mengubah favorit (HTTP ${r.code()}).")
                } catch (e: Exception) { toast(networkMessage(e)) }
            }
        }
        fun publish(id: Int, platform: String) {
            scope.launch {
                try {
                    val r = api.socialPublish(mapOf("generation_id" to id, "platform" to platform))
                    toast(r.message("Job publish dikirim ke Muse."))
                    loadSocial()
                } catch (e: Exception) { toast(networkMessage(e)) }
            }
        }

        Scaffold(
            containerColor = c.surface,
            snackbarHost = {
                SnackbarHost(snackbar) { data ->
                    BrutalBox(Modifier.padding(horizontal = 16.dp).padding(end = 5.dp, bottom = 5.dp), shape = RoundedCornerShape(18.dp), background = c.card) {
                        Row(Modifier.padding(horizontal = 16.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
                            Box(Modifier.size(28.dp).clip(RoundedCornerShape(9.dp)).background(BrandGradient), contentAlignment = Alignment.Center) {
                                CanangMark(size = 20, frame = Color.White)
                            }
                            Spacer(Modifier.width(10.dp))
                            Text(data.visuals.message, color = c.ink, fontSize = 13.sp, fontWeight = FontWeight.SemiBold)
                        }
                    }
                }
            },
            topBar = { if (route != "detail" && route != "notifications") TopBar(user, unread, onProfile = { go("profile") }, onBell = { nav.navigate("notifications") { launchSingleTop = true } }) },
            bottomBar = { if (route != "detail") BottomBar(route, ::go) },
        ) { pad ->
            NavHost(nav, "home", Modifier.padding(pad).fillMaxSize().background(c.surface)) {
                composable("home") {
                    HomeScreen(
                        user = user, quota = quota, counts = counts, generations = generations,
                        loading = loading, refreshing = refreshing, error = error,
                        onRefresh = { scope.launch { refreshing = true; loadGenerations(); loadCounts(); loadQuota(); refreshing = false } },
                        onOpen = ::open, onNavigate = ::go,
                        onIdea = { kw, m -> ideaKeyword = kw; ideaModel = m; submitResult = null; go("generate") },
                    )
                }
                composable("generate") {
                    val trackedId = submitResult?.generationId
                    GenerateScreen(
                        initialKeyword = ideaKeyword, initialModel = ideaModel.ifBlank { user?.defaultModel ?: "narasi" },
                        defaultDurasi = user?.defaultDurasi ?: 60, quota = quota, submitting = submitting, result = submitResult,
                        tracked = trackedId?.let { id -> generations.firstOrNull { it.id == id } },
                        onSubmit = { req ->
                            scope.launch {
                                submitting = true
                                try {
                                    val r = api.createGeneration(
                                        mapOf(
                                            "keyword" to req.keyword, "durasi" to req.durasi, "model" to req.model, "via_muse" to req.viaMuse,
                                            "backsound_volume" to req.volume, "backsound_trim_start" to req.trimStart, "backsound_trim_end" to req.trimEnd,
                                        ),
                                    )
                                    if (r.isSuccessful) {
                                        val id = r.body()?.getAsJsonObject("generation")?.get("id")?.asInt
                                        submitResult = SubmitResult(r.message(if (req.viaMuse) "Job dikirim ke Muse." else "Video berhasil dibuat."), false, id)
                                        reloadGenerations(); loadQuota()
                                    } else submitResult = SubmitResult(r.message("Gagal membuat video"), true)
                                } catch (e: Exception) { submitResult = SubmitResult(networkMessage(e), true) }
                                submitting = false
                            }
                        },
                        onOpen = ::open,
                        onReset = { submitResult = null; ideaKeyword = ""; ideaModel = "" },
                    )
                }
                composable("history") {
                    HistoryScreen(
                        generations = historyItems, counts = counts, tab = historyTab, search = historySearch,
                        loading = historyLoading, refreshing = refreshing, error = historyError,
                        onTab = { historyTab = it }, onSearch = { historySearch = it },
                        onRefresh = { scope.launch { refreshing = true; loadHistory(); loadCounts(); refreshing = false } },
                        onOpen = ::open, onToggleFav = ::toggleFav, onCreate = { go("generate") },
                    )
                }
                composable("detail") {
                    val g = detailGen
                    if (g == null) {
                        EmptyState("Pilih video dulu", "Buka video dari Beranda atau Riwayat.", Icons.Filled.Movie) {
                            SecondaryButton("Kembali", { nav.popBackStack() }, icon = Icons.Filled.ArrowBack)
                        }
                    } else {
                        LaunchedEffect(g.id) { loadDetail() }
                        DetailScreen(
                            g = g,
                            onBack = { nav.popBackStack() },
                            onSaveAudio = { vol, ts, te ->
                                scope.launch {
                                    try {
                                        val r = api.updateBacksound(g.id, mapOf("backsound_volume" to vol, "backsound_trim_start" to ts, "backsound_trim_end" to te))
                                        toast(r.message("Pengaturan audio disimpan."))
                                        if (r.isSuccessful) reloadGenerations()
                                    } catch (e: Exception) { toast(networkMessage(e)) }
                                }
                            },
                            onPublish = { platform -> publish(g.id, platform) },
                            onToggleFav = ::toggleFav,
                            onToast = ::toast,
                        )
                    }
                }
                composable("social") {
                    SocialScreen(
                        stats = socialStats, publishes = socialPublishes,
                        generations = generations.filter { !it.isArchived },
                        refreshing = socialRefreshing,
                        onPublish = ::publish,
                        onRefreshStats = {
                            scope.launch {
                                socialRefreshing = true
                                try { toast(api.socialRefresh().message("Permintaan refresh dikirim ke Muse.")) } catch (e: Exception) { toast(networkMessage(e)) }
                                loadSocial(); socialRefreshing = false
                            }
                        },
                        onReload = { scope.launch { loadSocial(); loadGenerations() } },
                        onToast = ::toast,
                    )
                }
                composable("notifications") {
                    LaunchedEffect(Unit) { loadNotifications(); NotificationHelper.cancelAll(context) }
                    NotificationsScreen(
                        items = notifItems, unread = unread, loading = notifLoading,
                        onRefresh = { scope.launch { loadNotifications() } },
                        onRead = { n -> scope.launch { try { api.readNotification(n.id) } catch (_: Exception) { }; loadNotifications() } },
                        onReadAll = { scope.launch { try { toast(api.readAllNotifications().message("Semua notifikasi ditandai dibaca.")) } catch (e: Exception) { toast(networkMessage(e)) }; loadNotifications() } },
                        onBack = { nav.popBackStack() },
                    )
                }
                composable("muse") {
                    LaunchedEffect(Unit) { loadUsage() }
                    Column {
                        Row(Modifier.padding(start = 16.dp, top = 12.dp)) { SecondaryButton("Kembali", { nav.popBackStack() }, icon = Icons.Filled.ArrowBack, small = true) }
                        MuseScreen(tokenUsage, usageError, onRefresh = { scope.launch { loadUsage() } })
                    }
                }
                composable("profile") {
                    ProfileScreen(
                        user = user, quota = quota, dark = dark, onToggleDark = toggleDark,
                        onOpenMuse = if (user?.isAdmin == true) ({ nav.navigate("muse") }) else null,
                        notificationCard = {
                            NotificationSettingsCard(
                                pushActive = pushActive,
                                onRequestPermission = ::requestNotifPermission,
                                onTestPush = {
                                    scope.launch {
                                        if (pushActive) {
                                            try { toast(api.pushTest().message("Notifikasi tes dikirim.")) } catch (e: Exception) { toast(networkMessage(e)) }
                                        } else {
                                            NotificationHelper.showPushed(context, (System.currentTimeMillis() / 1000).toInt(), "Tes notifikasi Ruang Dharma", "Notifikasi HP berhasil. Om Swastyastu 🙏", force = true)
                                        }
                                    }
                                },
                            )
                        },
                        onLogout = {
                            scope.launch {
                                PushManager.unregister(context, api)
                                try { api.logout() } catch (_: Exception) { }
                                clearSession()
                            }
                        },
                    )
                }
            }
        }
    }
}

@Composable
private fun Splash() {
    val c = Rd.colors
    Box(Modifier.fillMaxSize().background(c.surface), contentAlignment = Alignment.Center) {
        Column(horizontalAlignment = Alignment.CenterHorizontally) {
            CanangMark(size = 96, spin = true, stroke = c.ink)
            Spacer(Modifier.height(20.dp))
            RdLogo()
        }
    }
}

/** Padanan components/layout/Topbar.vue versi ponsel. */
@Composable
private fun TopBar(user: User?, unread: Int, onProfile: () -> Unit, onBell: () -> Unit) {
    val c = Rd.colors
    val infinite = rememberInfiniteTransition(label = "live")
    val a by infinite.animateFloat(0.35f, 1f, infiniteRepeatable(tween(900), RepeatMode.Reverse), label = "a")
    Column(Modifier.background(c.card)) {
        Row(
            Modifier.fillMaxWidth().statusBarsPadding().padding(horizontal = 16.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            RdLogo(Modifier.weight(1f))
            Row(
                Modifier.clip(RoundedCornerShape(999.dp)).background(tone("emerald").bg).padding(horizontal = 10.dp, vertical = 5.dp),
                verticalAlignment = Alignment.CenterVertically,
            ) {
                Box(Modifier.size(7.dp).alpha(a).clip(CircleShape).background(Color(0xFF10B981)))
                Spacer(Modifier.width(6.dp))
                Text("Live", fontSize = 11.sp, fontWeight = FontWeight.ExtraBold, color = tone("emerald").fg)
            }
            Spacer(Modifier.width(6.dp))
            Box(Modifier.size(42.dp).clip(RoundedCornerShape(14.dp)).clickable(onClick = onBell), contentAlignment = Alignment.Center) {
                Icon(if (unread > 0) Icons.Filled.NotificationsActive else Icons.Filled.Notifications, "Notifikasi", tint = c.ink, modifier = Modifier.size(22.dp))
                if (unread > 0) {
                    Box(
                        Modifier.align(Alignment.TopEnd).padding(top = 4.dp, end = 2.dp).clip(RoundedCornerShape(999.dp))
                            .background(Color(0xFFF43F5E)).border(2.dp, c.card, RoundedCornerShape(999.dp)).padding(horizontal = 5.dp, vertical = 1.dp),
                    ) { Text(if (unread > 99) "99+" else unread.toString(), color = Color.White, fontSize = 9.sp, fontWeight = FontWeight.ExtraBold) }
                }
            }
            Spacer(Modifier.width(6.dp))
            Box(Modifier.clickable(onClick = onProfile)) { Avatar(user?.name ?: "RD", 38.dp) }
        }
        Box(Modifier.fillMaxWidth().height(1.dp).background(c.line))
    }
}

/** Navigasi bawah: item aktif = pil kuning brutal (seperti menu aktif sidebar web), "Buat" = tombol gradasi. */
@Composable
private fun BottomBar(route: String, onGo: (String) -> Unit) {
    val c = Rd.colors
    Column(Modifier.background(c.card)) {
        Box(Modifier.fillMaxWidth().height(1.dp).background(c.line))
        Row(
            Modifier.fillMaxWidth().navigationBarsPadding().padding(horizontal = 8.dp, vertical = 8.dp),
            verticalAlignment = Alignment.CenterVertically,
        ) {
            NAV.forEach { item ->
                val active = route == item.route
                Column(
                    Modifier.weight(1f).clip(RoundedCornerShape(16.dp)).clickable { onGo(item.route) }.padding(vertical = 4.dp),
                    horizontalAlignment = Alignment.CenterHorizontally,
                ) {
                    if (item.route == "generate") {
                        BrutalBox(Modifier.size(48.dp).padding(end = 3.dp, bottom = 3.dp), shape = RoundedCornerShape(16.dp), brush = BrandGradient, offset = 3.dp) {
                            Icon(item.icon, item.label, tint = Color.White, modifier = Modifier.align(Alignment.Center).size(22.dp))
                        }
                    } else if (active) {
                        BrutalBox(Modifier.padding(end = 3.dp, bottom = 3.dp), shape = RoundedCornerShape(12.dp), background = Yellow, offset = 3.dp) {
                            Icon(item.icon, item.label, tint = Zinc950, modifier = Modifier.padding(horizontal = 14.dp, vertical = 5.dp).size(20.dp))
                        }
                    } else {
                        Icon(item.icon, item.label, tint = c.muted, modifier = Modifier.padding(horizontal = 14.dp, vertical = 7.dp).size(20.dp))
                    }
                    Text(
                        item.label, fontSize = 11.sp,
                        fontWeight = if (active) FontWeight.ExtraBold else FontWeight.SemiBold,
                        color = if (active) c.ink else c.muted, modifier = Modifier.padding(top = 2.dp),
                    )
                }
            }
        }
    }
}
