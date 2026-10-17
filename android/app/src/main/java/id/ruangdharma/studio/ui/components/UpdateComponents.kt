package id.ruangdharma.studio.ui.components

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import androidx.lifecycle.compose.collectAsStateWithLifecycle
import id.ruangdharma.studio.BuildConfig
import id.ruangdharma.studio.RuangDharmaApplication
import id.ruangdharma.studio.data.update.AppUpdater
import id.ruangdharma.studio.data.update.ReleaseInfo
import id.ruangdharma.studio.data.update.UpdateState
import id.ruangdharma.studio.ui.theme.*
import kotlinx.coroutines.launch

private fun sizeLabel(bytes: Long): String =
    if (bytes >= 1_048_576) "%.1f MB".format(bytes / 1_048_576.0) else if (bytes > 0) "${bytes / 1024} KB" else ""

private fun pendingInfo(state: UpdateState): ReleaseInfo? = when (state) {
    is UpdateState.Available -> state.info
    is UpdateState.NeedsInstallPermission -> state.info
    is UpdateState.Failed -> state.info
    else -> null
}

/**
 * Kartu "Pembaruan aplikasi" di layar Profil:
 * Menampilkan versi terpasang, status pembaruan, tombol periksa manual,
 * dan toggle perbarui otomatis di latar belakang (Wi-Fi).
 */
@Composable
fun UpdateCard(modifier: Modifier = Modifier) {
    val context = LocalContext.current
    val app = context.applicationContext as? RuangDharmaApplication
    val state by AppUpdater.state.collectAsStateWithLifecycle()
    var auto by remember { mutableStateOf(AppUpdater.isAutoEnabled(context)) }
    val busy = state is UpdateState.Checking || state is UpdateState.Downloading || state is UpdateState.Installing
    val pending = pendingInfo(state)
    val c = Rd.colors

    RdCard(modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Box(
                Modifier.size(36.dp).clip(CircleShape).background(Violet.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Filled.SystemUpdate, null, tint = Violet, modifier = Modifier.size(20.dp))
            }
            Spacer(Modifier.width(12.dp))
            Column(Modifier.weight(1f)) {
                Text("Pembaruan aplikasi", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 16.sp, color = c.ink)
                Text("Versi terpasang v${BuildConfig.VERSION_NAME}", fontSize = 12.sp, color = c.muted)
            }
            if (state is UpdateState.UpToDate) {
                Box(
                    Modifier.clip(RoundedCornerShape(8.dp)).background(Color(0xFF10B981).copy(alpha = 0.12f)).padding(horizontal = 8.dp, vertical = 4.dp)
                ) {
                    Text("Terbaru", fontSize = 11.sp, fontWeight = FontWeight.Bold, color = Color(0xFF10B981))
                }
            }
        }

        Spacer(Modifier.height(10.dp))

        val statusText = when (val s = state) {
            UpdateState.Idle -> "Periksa apakah versi baru Ruang Dharma Studio sudah tersedia."
            UpdateState.Checking -> "Sedang memeriksa pembaruan ke server…"
            is UpdateState.UpToDate -> "Aplikasi sudah versi terbaru (v${s.version})."
            is UpdateState.Available -> "Versi ${s.info.version} tersedia" + sizeLabel(s.info.apkSize).let { if (it.isEmpty()) "." else " ($it)." }
            is UpdateState.Downloading -> "Mengunduh v${s.version}… ${s.percent}%"
            is UpdateState.Installing -> "Memasang v${s.version}… Jika diminta, ketuk \"Perbarui\"."
            is UpdateState.NeedsInstallPermission -> "Versi ${s.info.version} sudah diunduh. Izinkan \"Instal aplikasi tidak dikenal\", lalu ketuk \"Pasang sekarang\"."
            is UpdateState.Failed -> s.message
        }

        Text(
            statusText,
            fontSize = 12.sp,
            color = if (state is UpdateState.Failed) Color(0xFFF43F5E) else c.muted,
            lineHeight = 16.sp
        )

        val notes = (state as? UpdateState.Available)?.info?.notes.orEmpty()
        if (notes.isNotBlank()) {
            Spacer(Modifier.height(6.dp))
            Box(
                Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(c.surface).border(1.dp, c.line, RoundedCornerShape(12.dp)).padding(10.dp)
            ) {
                Text(notes, fontSize = 11.sp, color = c.ink, maxLines = 4)
            }
        }

        (state as? UpdateState.Downloading)?.let { d ->
            Spacer(Modifier.height(10.dp))
            LinearProgressIndicator(
                progress = { d.percent / 100f },
                modifier = Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp)),
                color = Violet,
                trackColor = c.line,
            )
        }

        Spacer(Modifier.height(12.dp))

        if (state is UpdateState.NeedsInstallPermission) {
            SecondaryButton("Izinkan pemasangan aplikasi", {
                context.startActivity(AppUpdater.installPermissionIntent(context))
            }, modifier = Modifier.fillMaxWidth())
            Spacer(Modifier.height(8.dp))
        }

        if (pending != null && !busy) {
            GradientButton(
                text = if (state is UpdateState.NeedsInstallPermission) "Pasang sekarang" else "Unduh & pasang v${pending.version}",
                onClick = {
                    app?.appScope?.launch { AppUpdater.downloadAndInstall(context.applicationContext, pending) }
                },
                modifier = Modifier.fillMaxWidth(),
                icon = Icons.Filled.Download,
            )
            Spacer(Modifier.height(8.dp))
        }

        // Toggle perbarui otomatis
        Row(
            Modifier.fillMaxWidth().clip(RoundedCornerShape(14.dp)).background(c.surface).padding(horizontal = 12.dp, vertical = 10.dp),
            verticalAlignment = Alignment.CenterVertically
        ) {
            Column(Modifier.weight(1f)) {
                Text("Perbarui otomatis", fontWeight = FontWeight.SemiBold, fontSize = 13.sp, color = c.ink)
                Text("Unduh & pasang otomatis di latar belakang saat terhubung Wi-Fi", fontSize = 11.sp, color = c.muted)
            }
            Spacer(Modifier.width(8.dp))
            Switch(
                checked = auto,
                onCheckedChange = {
                    auto = it
                    AppUpdater.setAutoEnabled(context, it)
                },
                colors = SwitchDefaults.colors(
                    checkedTrackColor = Violet,
                    checkedThumbColor = Color.White,
                    uncheckedTrackColor = c.line,
                    uncheckedBorderColor = c.line
                )
            )
        }

        Spacer(Modifier.height(10.dp))

        SecondaryButton(
            text = if (busy) "Sedang diproses…" else "Periksa pembaruan sekarang",
            onClick = {
                app?.appScope?.launch { AppUpdater.check(context.applicationContext) }
            },
            enabled = !busy,
            icon = Icons.Filled.Refresh,
            modifier = Modifier.fillMaxWidth(),
        )
    }
}

/**
 * Dialog pemberitahuan pembaruan yang muncul otomatis saat aplikasi dibuka
 * jika server memiliki versi yang lebih baru dari yang terpasang.
 */
@Composable
fun UpdatePromptHost() {
    val context = LocalContext.current
    val app = context.applicationContext as? RuangDharmaApplication
    val state by AppUpdater.state.collectAsStateWithLifecycle()
    var followVersion by rememberSaveable { mutableStateOf<String?>(null) }
    var hidden by rememberSaveable { mutableStateOf(false) }
    val c = Rd.colors

    val s = state
    val info = pendingInfo(s)
    val following = followVersion != null && (s is UpdateState.Downloading || s is UpdateState.Installing || s is UpdateState.NeedsInstallPermission || s is UpdateState.Failed)
    val prompt = s is UpdateState.Available && !AppUpdater.isPromptDismissed(context, s.info.version)
    if (hidden || (!prompt && !following)) return

    val dismiss = {
        if (s is UpdateState.Available) AppUpdater.dismissPrompt(context, s.info.version)
        if (following) hidden = true
        followVersion = null
    }

    AlertDialog(
        onDismissRequest = dismiss,
        containerColor = c.card,
        shape = RoundedCornerShape(24.dp),
        icon = {
            Box(
                Modifier.size(48.dp).clip(RoundedCornerShape(14.dp)).background(Violet.copy(alpha = 0.12f)),
                contentAlignment = Alignment.Center
            ) {
                Icon(Icons.Filled.SystemUpdate, null, tint = Violet, modifier = Modifier.size(24.dp))
            }
        },
        title = {
            Text(
                when (s) {
                    is UpdateState.Downloading -> "Mengunduh pembaruan"
                    is UpdateState.Installing -> "Memasang pembaruan"
                    is UpdateState.Failed -> "Pembaruan gagal"
                    else -> "Pembaruan Tersedia"
                },
                fontFamily = SpaceGrotesk,
                fontWeight = FontWeight.Bold,
                fontSize = 18.sp,
                color = c.ink
            )
        },
        text = {
            Column(Modifier.heightIn(max = 300.dp).verticalScroll(rememberScrollState())) {
                when (s) {
                    is UpdateState.Available -> {
                        Text(
                            "Versi ${s.info.version} sudah tersedia" + sizeLabel(s.info.apkSize).let { if (it.isEmpty()) "." else " ($it)." } + " Versi saat ini: ${BuildConfig.VERSION_NAME}.",
                            fontSize = 13.sp,
                            color = c.muted
                        )
                        if (s.info.notes.isNotBlank()) {
                            Spacer(Modifier.height(8.dp))
                            Text("Catatan rilis:", fontWeight = FontWeight.Bold, fontSize = 12.sp, color = c.ink)
                            Spacer(Modifier.height(4.dp))
                            Box(
                                Modifier.fillMaxWidth().clip(RoundedCornerShape(12.dp)).background(c.surface).border(1.dp, c.line, RoundedCornerShape(12.dp)).padding(10.dp)
                            ) {
                                Text(s.info.notes, fontSize = 11.sp, color = c.ink)
                            }
                        }
                    }
                    is UpdateState.Downloading -> {
                        Text("v${s.version} — ${s.percent}%", fontSize = 13.sp, color = c.ink, fontWeight = FontWeight.SemiBold)
                        Spacer(Modifier.height(8.dp))
                        LinearProgressIndicator(
                            progress = { s.percent / 100f },
                            modifier = Modifier.fillMaxWidth().height(6.dp).clip(RoundedCornerShape(3.dp)),
                            color = Violet,
                            trackColor = c.line,
                        )
                    }
                    is UpdateState.Installing -> {
                        Text("Jika Android meminta konfirmasi, ketuk \"Perbarui\". Aplikasi akan dimulai ulang otomatis.", fontSize = 13.sp, color = c.muted)
                    }
                    is UpdateState.NeedsInstallPermission -> {
                        Text("Izinkan \"Instal aplikasi tidak dikenal\" untuk Ruang Dharma Studio, lalu kembali dan ketuk \"Pasang sekarang\".", fontSize = 13.sp, color = c.muted)
                    }
                    is UpdateState.Failed -> {
                        Text(s.message, fontSize = 13.sp, color = Color(0xFFF43F5E))
                    }
                    else -> Unit
                }
            }
        },
        confirmButton = {
            when (s) {
                is UpdateState.NeedsInstallPermission -> Row {
                    TextButton(onClick = {
                        context.startActivity(AppUpdater.installPermissionIntent(context))
                    }) { Text("Izinkan", color = Violet, fontWeight = FontWeight.Bold) }
                    TextButton(onClick = {
                        app?.appScope?.launch { AppUpdater.downloadAndInstall(context.applicationContext, s.info) }
                    }) { Text("Pasang sekarang", color = Violet, fontWeight = FontWeight.Bold) }
                }
                is UpdateState.Available, is UpdateState.Failed -> if (info != null) {
                    Box(
                        Modifier.clip(RoundedCornerShape(12.dp)).background(Violet).clickable {
                            followVersion = info.version
                            hidden = false
                            app?.appScope?.launch { AppUpdater.downloadAndInstall(context.applicationContext, info) }
                        }.padding(horizontal = 16.dp, vertical = 8.dp)
                    ) {
                        Text(if (s is UpdateState.Failed) "Coba lagi" else "Unduh & pasang", color = Color.White, fontWeight = FontWeight.Bold)
                    }
                }
                else -> Unit
            }
        },
        dismissButton = {
            TextButton(onClick = dismiss) {
                Text(
                    if (s is UpdateState.Downloading || s is UpdateState.Installing) "Sembunyikan" else if (s is UpdateState.Available) "Nanti" else "Tutup",
                    color = c.muted
                )
            }
        }
    )
}
