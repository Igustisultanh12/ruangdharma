package id.ruangdharma.studio

import android.os.Bundle
import androidx.activity.ComponentActivity
import androidx.activity.compose.setContent
import androidx.compose.foundation.layout.*
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Modifier
import androidx.compose.ui.unit.dp
import androidx.compose.ui.platform.LocalContext
import androidx.navigation.compose.NavHost
import androidx.navigation.compose.composable
import androidx.navigation.compose.currentBackStackEntryAsState
import androidx.navigation.compose.rememberNavController
import kotlinx.coroutines.launch
import org.json.JSONObject
import id.ruangdharma.studio.data.api.RetrofitClient
import id.ruangdharma.studio.data.api.TokenStore
import id.ruangdharma.studio.data.model.*
import id.ruangdharma.studio.ui.components.CanangMark
import id.ruangdharma.studio.ui.screens.*
import id.ruangdharma.studio.ui.theme.RuangDharmaTheme

class MainActivity : ComponentActivity() {
    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContent { RuangDharmaApp() }
    }
}

@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun RuangDharmaApp() {
    val context = LocalContext.current
    val scope = rememberCoroutineScope()
    var token by remember { mutableStateOf<String?>(null) }
    var user by remember { mutableStateOf<User?>(null) }
    var generations by remember { mutableStateOf<List<Generation>>(emptyList()) }
    var loading by remember { mutableStateOf(false) }
    var refreshing by remember { mutableStateOf(false) }
    var error by remember { mutableStateOf<String?>(null) }
    var loginLoading by remember { mutableStateOf(false) }
    var loginError by remember { mutableStateOf<String?>(null) }
    var submitResult by remember { mutableStateOf<String?>(null) }
    var submitting by remember { mutableStateOf(false) }
    var socialStats by remember { mutableStateOf<List<SocialStats>>(emptyList()) }
    var socialPublishes by remember { mutableStateOf<List<SocialPublish>>(emptyList()) }
    var tokenUsage by remember { mutableStateOf<TokenUsage?>(null) }
    var selected by remember { mutableStateOf<Generation?>(null) }

    val api = remember(token) { RetrofitClient.create(context) { token } }

    fun loadGenerations() {
        scope.launch {
            loading = true; error = null
            try {
                val r = api.generations()
                val body = r.body()
                generations = body?.data?.items ?: emptyList()
                if (!r.isSuccessful) error = "Gagal memuat: " + r.code()
            } catch (e: Exception) { error = e.message ?: "Gagal memuat" }
            loading = false
        }
    }
    fun loadSocial() {
        scope.launch {
            try { socialStats = api.socialStats().body()?.data ?: emptyList() } catch (_: Exception) {}
            try { socialPublishes = api.socialPublishes().body()?.data ?: emptyList() } catch (_: Exception) {}
        }
    }
    fun loadUsage() {
        scope.launch { try { tokenUsage = api.tokenUsage().body()?.data } catch (_: Exception) {} }
    }

    // Ambil pesan error dari body server ({"message": ...} atau {"errors": {"email": [...]}}),
    // fallback ke pesan per kode HTTP agar user tahu penyebabnya, bukan sekadar "cek email & password".
    fun parseLoginError(code: Int, raw: String?): String {
        if (!raw.isNullOrBlank()) {
            try {
                val json = JSONObject(raw)
                val msg = json.optString("message").takeIf { it.isNotBlank() }
                if (msg != null) return msg
                val errors = json.optJSONObject("errors")
                if (errors != null) {
                    val keys = errors.keys()
                    while (keys.hasNext()) {
                        val arr = errors.optJSONArray(keys.next())
                        if (arr != null && arr.length() > 0) return arr.optString(0)
                    }
                }
            } catch (_: Exception) { }
        }
        return when (code) {
            429 -> "Terlalu banyak percobaan login. Coba lagi beberapa menit."
            403 -> "Akun dinonaktifkan. Hubungi admin."
            422 -> "Email atau password salah."
            401 -> "Email atau password salah."
            else -> "Login gagal (HTTP $code) — cek email & password."
        }
    }

    LaunchedEffect(token) {
        if (token != null) { loadGenerations(); loadSocial(); loadUsage() }
    }

    RuangDharmaTheme {
        if (token == null) {
            LoginScreen(
                onLogin = { email, pass ->
                    scope.launch {
                        loginLoading = true; loginError = null
                        try {
                            val r = api.login(mapOf("email" to email, "password" to pass))
                            val body = r.body()
                            // Server mengirim token di level ATAS (body.token), bukan body.data.token.
                            val t = body?.resolvedToken()
                            if (r.isSuccessful && t != null) {
                                token = t
                                TokenStore.save(context, t)
                                user = body?.resolvedUser()
                            } else {
                                val raw = try { r.errorBody()?.string() } catch (_: Exception) { null }
                                loginError = parseLoginError(r.code(), raw)
                            }
                        } catch (e: Exception) { loginError = e.message ?: "Login gagal" }
                        loginLoading = false
                    }
                }, loading = loginLoading, error = loginError
            )
            return@RuangDharmaTheme
        }

        val nav = rememberNavController()
        val backStack by nav.currentBackStackEntryAsState()
        val route = backStack?.destination?.route ?: "home"
        Scaffold(
            topBar = {
                CenterAlignedTopAppBar(title = { Row { CanangMark(size = 28); Spacer(Modifier.width(6.dp)); Text("Ruang Dharma") } })
            },
            bottomBar = {
                NavigationBar {
                    listOf("home" to ("Beranda" to Icons.Filled.Home), "generate" to ("Buat" to Icons.Filled.AddCircle), "history" to ("Riwayat" to Icons.Filled.History), "social" to ("Medsos" to Icons.Filled.Share), "muse" to ("Muse" to Icons.Filled.Psychology), "profile" to ("Profil" to Icons.Filled.Person)).forEach { (r, pair) ->
                        NavigationBarItem(selected = route == r, onClick = { nav.navigate(r) { popUpTo("home") { saveState = true }; launchSingleTop = true } }, icon = { Icon(pair.second, null) }, label = { Text(pair.first) })
                    }
                }
            }
        ) { pad ->
            NavHost(nav, "home", Modifier.padding(pad)) {
                composable("home") {
                    HomeScreen(generations, loading, refreshing, error, onRefresh = { refreshing = true; loadGenerations(); refreshing = false }, onOpen = { selected = it; nav.navigate("detail") })
                }
                composable("generate") {
                    GenerateScreen(onSubmit = { kw, dur, mdl, via, vol, ts, te ->
                        scope.launch {
                            submitting = true
                            try {
                                val r = api.createGeneration(mapOf("keyword" to kw, "durasi" to dur, "model" to mdl, "via_muse" to via, "backsound_volume" to vol, "backsound_trim_start" to ts, "backsound_trim_end" to te))
                                submitResult = if (r.isSuccessful) "Job dikirim ke Muse. Cek Riwayat untuk status." else "Gagal: " + r.code()
                                if (r.isSuccessful) loadGenerations()
                            } catch (e: Exception) { submitResult = e.message }
                            submitting = false
                        }
                    }, submitting = submitting, result = submitResult)
                }
                composable("history") {
                    HistoryScreen(generations, onOpen = { selected = it; nav.navigate("detail") })
                }
                composable("detail") {
                    val g = selected
                    if (g != null) DetailScreen(g,
                        onBack = { nav.popBackStack() },
                        onSaveAudio = { vol, ts, te ->
                            scope.launch {
                                try { api.updateBacksound(g.id, mapOf("backsound_volume" to vol, "backsound_trim_start" to ts, "backsound_trim_end" to te)); loadGenerations() } catch (_: Exception) {}
                            }
                        },
                        onPublish = { platform ->
                            scope.launch {
                                try { api.socialPublish(mapOf("generation_id" to g.id, "platform" to platform)); loadSocial() } catch (_: Exception) {}
                            }
                        }
                    ) else Text("Pilih video dulu dari Beranda / Riwayat.")
                }
                composable("social") {
                    SocialScreen(socialStats, socialPublishes, onPublish = { gid, plat ->
                        scope.launch { try { api.socialPublish(mapOf("generation_id" to gid, "platform" to plat)); loadSocial() } catch (_: Exception) {} }
                    }, onRefresh = { scope.launch { try { api.socialRefresh() } catch (_: Exception) {}; loadSocial() } }, generations)
                }
                composable("muse") { MuseScreen(tokenUsage, onRefresh = { loadUsage() }) }
                composable("profile") {
                    ProfileScreen(user, onLogout = { scope.launch { try { api.logout() } catch (_: Exception) {}; token = null; TokenStore.save(context, null); user = null } })
                }
            }
        }
    }
}
