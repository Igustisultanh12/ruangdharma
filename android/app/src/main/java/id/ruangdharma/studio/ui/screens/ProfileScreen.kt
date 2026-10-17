package id.ruangdharma.studio.ui.screens

import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.rememberScrollState
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.foundation.verticalScroll
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.*
import androidx.compose.runtime.*
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.Color
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.BuildConfig
import id.ruangdharma.studio.data.model.PulseQuota
import id.ruangdharma.studio.data.model.User
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.theme.*

/** Padanan views/Profile.vue + tombol keluar & mode gelap dari Sidebar/Topbar web. */
@Composable
fun ProfileScreen(
    user: User?,
    quota: PulseQuota?,
    dark: Boolean,
    onToggleDark: () -> Unit,
    onOpenMuse: (() -> Unit)?,
    onLogout: () -> Unit,
    notificationCard: @Composable () -> Unit = {},
) {
    val c = Rd.colors
    var confirm by remember { mutableStateOf(false) }

    Column(Modifier.fillMaxSize().verticalScroll(rememberScrollState()).padding(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
        PageHeader("Profil", "Kelola data diri, keamanan akun, dan pengaturan default saat membuat video.")

        // Kartu identitas bergaya brutal dengan sampul gradasi.
        BrutalBox(Modifier.fillMaxWidth().padding(end = 5.dp, bottom = 5.dp)) {
            Column {
                Box(Modifier.fillMaxWidth().height(86.dp).background(BrandGradient).dotBackground()) {
                    Box(Modifier.align(Alignment.TopEnd).padding(10.dp)) { CanangMark(size = 54, frame = Color.White) }
                }
                Column(Modifier.padding(horizontal = 20.dp).padding(bottom = 20.dp).offset(y = (-28).dp)) {
                    BrutalBox(Modifier.size(64.dp), shape = RoundedCornerShape(20.dp), brush = BrandGradient, offset = 3.dp) {
                        val initials = (user?.name ?: "RD").split(" ").filter { it.isNotBlank() }.take(2).joinToString("") { it.first().uppercase() }
                        Text(initials, color = Color.White, fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 22.sp, modifier = Modifier.align(Alignment.Center))
                    }
                    Text(user?.name ?: "—", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 22.sp, color = c.ink, modifier = Modifier.padding(top = 12.dp))
                    Text(user?.email ?: "", fontSize = 13.sp, color = c.muted)
                    Spacer(Modifier.height(10.dp))
                    Eyebrow(
                        "Panel ${user?.roleLabel ?: "Pengguna"}",
                        icon = when { user?.isAdmin == true -> Icons.Filled.EmojiEvents; user?.isReviewer == true -> Icons.Filled.Edit; else -> Icons.Filled.AutoAwesome },
                    )
                }
            }
        }

        RdCard(Modifier.fillMaxWidth()) {
            Text("Kuota harian", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
            val used = quota?.used ?: user?.quota?.usedToday ?: 0
            val limit = quota?.limit ?: user?.quota?.limit ?: 0
            QuotaBar(used, limit, Modifier.padding(top = 12.dp))
            Text("Sisa ${quota?.remaining ?: user?.quota?.remaining ?: 0} video hari ini · reset tiap tengah malam.", fontSize = 12.sp, color = c.muted, modifier = Modifier.padding(top = 8.dp))
        }

        RdCard(Modifier.fillMaxWidth(), padding = 8.dp) {
            InfoRow(Icons.Filled.Person, "Role", user?.roleLabel ?: "—")
            InfoRow(Icons.Filled.Timer, "Durasi default", "${user?.defaultDurasi ?: 60} dtk")
            InfoRow(Icons.Filled.Mic, "Gaya default", modelInfo(user?.defaultModel ?: "narasi").label)
            InfoRow(Icons.Filled.Event, "Bergabung", user?.createdAt?.let { relativeTime(it) } ?: "—")
        }

        RdCard(Modifier.fillMaxWidth(), padding = 8.dp) {
            ActionRow(if (dark) Icons.Filled.LightMode else Icons.Filled.DarkMode, if (dark) "Mode terang" else "Mode gelap", onToggleDark) {
                Switch(dark, { onToggleDark() }, colors = SwitchDefaults.colors(checkedTrackColor = Violet, checkedThumbColor = Color.White, uncheckedTrackColor = c.line, uncheckedBorderColor = c.line))
            }
            if (onOpenMuse != null) ActionRow(Icons.Filled.CloudSync, "Integrasi Muse (admin)", onOpenMuse) {
                Icon(Icons.Filled.ChevronRight, null, tint = c.muted)
            }
        }

        notificationCard()

        SecondaryButton("Keluar", { confirm = true }, icon = Icons.Filled.Logout, danger = true, modifier = Modifier.fillMaxWidth())
        Text("Ruang Dharma Studio v${BuildConfig.VERSION_NAME} · @ruangdharma_", fontSize = 11.sp, color = c.muted, modifier = Modifier.fillMaxWidth().padding(bottom = 8.dp), textAlign = androidx.compose.ui.text.style.TextAlign.Center)
    }

    if (confirm) {
        AlertDialog(
            onDismissRequest = { confirm = false },
            containerColor = c.card,
            shape = RoundedCornerShape(28.dp),
            icon = {
                Box(Modifier.size(52.dp).clip(RoundedCornerShape(16.dp)).background(tone("rose").bg), contentAlignment = Alignment.Center) {
                    Icon(Icons.Filled.Logout, null, tint = tone("rose").fg)
                }
            },
            title = { Text("Keluar dari studio?", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, color = c.ink) },
            text = { Text("Sesi di perangkat ini akan diakhiri.", color = c.muted) },
            confirmButton = {
                Box(
                    Modifier.clip(RoundedCornerShape(14.dp)).background(Color(0xFFF43F5E)).clickable { confirm = false; onLogout() }.padding(horizontal = 18.dp, vertical = 10.dp),
                ) { Text("Keluar", color = Color.White, fontWeight = FontWeight.Bold) }
            },
            dismissButton = { SecondaryButton("Batal", { confirm = false }) },
        )
    }
}

@Composable
private fun InfoRow(icon: ImageVector, label: String, value: String) {
    val c = Rd.colors
    Row(Modifier.fillMaxWidth().padding(horizontal = 12.dp, vertical = 12.dp), verticalAlignment = Alignment.CenterVertically) {
        Box(Modifier.size(36.dp).clip(RoundedCornerShape(12.dp)).background(c.surface2), contentAlignment = Alignment.Center) {
            Icon(icon, null, tint = Violet, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Text(label, fontSize = 14.sp, color = c.muted, modifier = Modifier.weight(1f))
        Text(value, fontSize = 14.sp, fontWeight = FontWeight.Bold, color = c.ink)
    }
}

@Composable
private fun ActionRow(icon: ImageVector, label: String, onClick: () -> Unit, trailing: @Composable () -> Unit) {
    val c = Rd.colors
    Row(
        Modifier.fillMaxWidth().clip(RoundedCornerShape(16.dp)).clickable(onClick = onClick).padding(horizontal = 12.dp, vertical = 8.dp),
        verticalAlignment = Alignment.CenterVertically,
    ) {
        Box(Modifier.size(36.dp).clip(RoundedCornerShape(12.dp)).background(c.surface2), contentAlignment = Alignment.Center) {
            Icon(icon, null, tint = Violet, modifier = Modifier.size(18.dp))
        }
        Spacer(Modifier.width(12.dp))
        Text(label, fontSize = 14.sp, fontWeight = FontWeight.SemiBold, color = c.ink, modifier = Modifier.weight(1f))
        trailing()
    }
}
