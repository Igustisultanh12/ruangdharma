package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.CircleShape
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.data.model.AppNotification
import id.ruangdharma.studio.notify.NotificationHelper
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.theme.*

/** Ikon & warna per jenis notifikasi (judul dari server). */
private fun notifStyle(title: String, message: String): Pair<ImageVector, String> {
    val t = (title + " " + message).lowercase()
    return when {
        "gagal" in t -> Icons.Filled.ErrorOutline to "rose"
        "revisi" in t -> Icons.Filled.Replay to "rose"
        "disetujui" in t || "terbit" in t -> Icons.Filled.Verified to "emerald"
        "review" in t -> Icons.Filled.HourglassTop to "amber"
        "komentar" in t -> Icons.Filled.ChatBubble to "sky"
        "video" in t || "muse" in t || "job" in t -> Icons.Filled.Movie to "violet"
        else -> Icons.Filled.Notifications to "fuchsia"
    }
}

/** Padanan views/Notifications.vue — tab Semua / Belum dibaca, tandai dibaca. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun NotificationsScreen(
    items: List<AppNotification>,
    unread: Int,
    loading: Boolean,
    onRefresh: () -> Unit,
    onRead: (AppNotification) -> Unit,
    onReadAll: () -> Unit,
    onBack: () -> Unit,
) {
    val c = Rd.colors
    var tab by rememberSaveable { mutableIntStateOf(0) }
    val shown = if (tab == 1) items.filter { !it.isRead } else items
    PullToRefreshBox(isRefreshing = false, onRefresh = onRefresh, modifier = Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(10.dp)) {
            item {
                Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(bottom = 12.dp)) {
                    SecondaryButton("Kembali", onBack, icon = Icons.Filled.ArrowBack, small = true)
                    Spacer(Modifier.weight(1f))
                    SecondaryButton("Tandai semua dibaca", onReadAll, icon = Icons.Filled.DoneAll, small = true, enabled = unread > 0)
                }
                PageHeader("Notifikasi", "Muncul saat video selesai dibuat, direview, atau dikomentari — juga sebagai notifikasi di HP.")
                Segmented(
                    listOf("Semua" to Icons.Filled.Notifications, "Belum dibaca" to Icons.Filled.FiberNew),
                    tab, { tab = it }, Modifier.fillMaxWidth(), counts = listOf(null, unread),
                )
            }
            when {
                loading && items.isEmpty() -> items(4) { ShimmerCard() }
                shown.isEmpty() -> item {
                    RdCard(Modifier.fillMaxWidth()) {
                        EmptyState(
                            if (tab == 1) "Semua sudah dibaca" else "Belum ada notifikasi",
                            "Notifikasi muncul saat video selesai dibuat, direview, atau dikomentari.",
                            Icons.Filled.NotificationsNone,
                        )
                    }
                }
                else -> items(shown, key = { it.id }) { n ->
                    val title = n.title ?: "Ruang Dharma Studio"
                    val msg = n.message.orEmpty()
                    val (icon, color) = notifStyle(title, msg)
                    val t = tone(color)
                    val sh = RoundedCornerShape(20.dp)
                    Row(
                        Modifier.fillMaxWidth().clip(sh)
                            .background(if (n.isRead) c.card else c.surface2)
                            .border(if (n.isRead) 1.dp else 2.dp, if (n.isRead) c.line else Violet.copy(alpha = 0.35f), sh)
                            .then(if (!n.isRead) Modifier.clickable { onRead(n) } else Modifier)
                            .padding(14.dp),
                        verticalAlignment = Alignment.Top,
                    ) {
                        Box(Modifier.size(40.dp).clip(RoundedCornerShape(14.dp)).background(t.bg), contentAlignment = Alignment.Center) {
                            Icon(icon, null, tint = t.fg, modifier = Modifier.size(20.dp))
                        }
                        Spacer(Modifier.width(12.dp))
                        Column(Modifier.weight(1f)) {
                            Row(verticalAlignment = Alignment.CenterVertically) {
                                Text(title, fontWeight = FontWeight.Bold, fontSize = 14.sp, color = c.ink, modifier = Modifier.weight(1f))
                                if (!n.isRead) Box(Modifier.size(8.dp).clip(CircleShape).background(Fuchsia))
                            }
                            if (msg.isNotBlank()) Text(msg, fontSize = 13.sp, color = c.muted, lineHeight = 18.sp, modifier = Modifier.padding(top = 2.dp))
                            Text(relativeTime(n.createdAt), fontSize = 11.sp, color = c.muted, modifier = Modifier.padding(top = 6.dp))
                        }
                    }
                }
            }
            item { Spacer(Modifier.height(8.dp)) }
        }
    }
}

/** Kartu pengaturan notifikasi HP di Profil (izin, aktif, suara, tes). */
@Composable
fun NotificationSettingsCard(
    pushActive: Boolean,
    onRequestPermission: () -> Unit,
    onTestPush: () -> Unit,
) {
    val c = Rd.colors
    val context = LocalContext.current
    var enabled by remember { mutableStateOf(NotificationHelper.isEnabled(context)) }
    var sound by remember { mutableStateOf(NotificationHelper.isSoundEnabled(context)) }
    val permitted = NotificationHelper.hasPermission(context)
    val switchColors = SwitchDefaults.colors(checkedTrackColor = Violet, checkedThumbColor = Color.White, uncheckedTrackColor = c.line, uncheckedBorderColor = c.line)

    RdCard(Modifier.fillMaxWidth()) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Text("Notifikasi HP", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink, modifier = Modifier.weight(1f))
            RdBadge(if (pushActive) "Push instan" else "Sinkron berkala", if (pushActive) "emerald" else "sky", icon = if (pushActive) Icons.Filled.Bolt else Icons.Filled.Sync, small = true)
        }
        Text(
            if (pushActive) "Notifikasi langsung muncul walau aplikasi ditutup."
            else "Aplikasi memeriksa notifikasi baru saat terbuka & ±15 menit di latar belakang. Admin bisa mengaktifkan push instan di Pengaturan → Push HP.",
            fontSize = 12.sp, color = c.muted, modifier = Modifier.padding(top = 4.dp, bottom = 8.dp),
        )
        if (!permitted) {
            AlertBox("Izin notifikasi belum diberikan, jadi notifikasi tidak bisa muncul di bilah status.", color = "amber", icon = Icons.Filled.NotificationsOff)
            Spacer(Modifier.height(10.dp))
            BrutalButton("Izinkan notifikasi", onRequestPermission, icon = Icons.Filled.NotificationsActive, small = true)
            Spacer(Modifier.height(6.dp))
        }
        Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Tampilkan notifikasi", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = c.ink, modifier = Modifier.weight(1f))
            Switch(enabled, { enabled = it; NotificationHelper.setEnabled(context, it) }, colors = switchColors)
        }
        Row(Modifier.fillMaxWidth().padding(vertical = 4.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("Suara & getar", fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = c.ink, modifier = Modifier.weight(1f))
            Switch(sound, { sound = it; NotificationHelper.setSoundEnabled(context, it) }, colors = switchColors, enabled = enabled)
        }
        Spacer(Modifier.height(8.dp))
        SecondaryButton("Kirim notifikasi tes", onTestPush, icon = Icons.Filled.Send, small = true, enabled = enabled && permitted)
    }
}
