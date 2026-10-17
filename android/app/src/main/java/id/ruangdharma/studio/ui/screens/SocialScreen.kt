package id.ruangdharma.studio.ui.screens

import android.content.Intent
import android.net.Uri
import androidx.compose.foundation.background
import androidx.compose.foundation.border
import androidx.compose.foundation.clickable
import androidx.compose.foundation.layout.*
import androidx.compose.foundation.lazy.LazyColumn
import androidx.compose.foundation.lazy.items
import androidx.compose.foundation.shape.RoundedCornerShape
import androidx.compose.material.icons.Icons
import androidx.compose.material.icons.filled.*
import androidx.compose.material3.ExperimentalMaterial3Api
import androidx.compose.material3.Icon
import androidx.compose.material3.Text
import androidx.compose.material3.pulltorefresh.PullToRefreshBox
import androidx.compose.runtime.*
import androidx.compose.runtime.saveable.rememberSaveable
import androidx.compose.ui.Alignment
import androidx.compose.ui.Modifier
import androidx.compose.ui.draw.clip
import androidx.compose.ui.graphics.vector.ImageVector
import androidx.compose.ui.platform.LocalClipboardManager
import androidx.compose.ui.platform.LocalContext
import androidx.compose.ui.text.AnnotatedString
import androidx.compose.ui.text.font.FontWeight
import androidx.compose.ui.text.style.TextOverflow
import androidx.compose.ui.unit.dp
import androidx.compose.ui.unit.sp
import id.ruangdharma.studio.data.model.*
import id.ruangdharma.studio.ui.components.*
import id.ruangdharma.studio.ui.theme.*

/** Padanan views/Social.vue — statistik IG/FB, post, komen, upload via Muse, TikTok manual. */
@OptIn(ExperimentalMaterial3Api::class)
@Composable
fun SocialScreen(
    stats: Map<String, StatsResponse>,
    publishes: List<SocialPublish>,
    generations: List<Generation>,
    refreshing: Boolean,
    onPublish: (Int, String) -> Unit,
    onRefreshStats: () -> Unit,
    onReload: () -> Unit,
    onToast: (String) -> Unit,
) {
    val c = Rd.colors
    val context = LocalContext.current
    val clipboard = LocalClipboardManager.current
    var tab by rememberSaveable { mutableIntStateOf(0) }
    val platform = listOf("instagram", "facebook", "tiktok")[tab]
    val platformName = listOf("Instagram", "Facebook", "TikTok")[tab]
    fun open(url: String) { runCatching { context.startActivity(Intent(Intent.ACTION_VIEW, Uri.parse(url))) } }

    PullToRefreshBox(isRefreshing = false, onRefresh = onReload, modifier = Modifier.fillMaxSize()) {
        LazyColumn(Modifier.fillMaxSize(), contentPadding = PaddingValues(16.dp), verticalArrangement = Arrangement.spacedBy(14.dp)) {
            item {
                PageHeader("Medsos", "Pantau like, komen & statistik Instagram & Facebook. Upload langsung dari video yang sudah jadi. TikTok tetap manual.")
                SecondaryButton(
                    if (refreshing) "Meminta refresh…" else "Refresh statistik via Muse", onRefreshStats,
                    icon = Icons.Filled.Refresh, enabled = !refreshing, modifier = Modifier.fillMaxWidth(),
                )
                Spacer(Modifier.height(12.dp))
                Segmented(
                    listOf("Instagram" to Icons.Filled.PhotoCamera, "Facebook" to Icons.Filled.ThumbUp, "TikTok" to Icons.Filled.MusicVideo),
                    tab, { tab = it }, Modifier.fillMaxWidth(),
                )
            }

            if (platform == "tiktok") {
                item {
                    RdCard(Modifier.fillMaxWidth()) {
                        Text("TikTok — Manual", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                        Text("TikTok belum ada API otomatis di Muse. Pilih video di bawah, lalu unduh + salin caption dan upload manual di aplikasi TikTok.", fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 4.dp))
                    }
                }
                if (generations.isEmpty()) item { Text("Belum ada video. Buat dulu di menu Buat.", fontSize = 13.sp, color = c.muted) }
                items(generations, key = { "t" + it.id }) { g ->
                    VideoRow(g) {
                        Row(horizontalArrangement = Arrangement.spacedBy(8.dp)) {
                            g.fullVideoUrl()?.let { u -> SecondaryButton("Unduh", { open(u) }, icon = Icons.Filled.Download, small = true) }
                            GradientButton("Salin caption", {
                                clipboard.setText(AnnotatedString(g.captionText()))
                                onToast("Caption disalin. Upload videonya di aplikasi TikTok.")
                                g.fullVideoUrl()?.let { open(it) }
                            }, icon = Icons.Filled.ContentCopy)
                        }
                    }
                }
            } else {
                val res = stats[platform]
                val s = res?.data
                item {
                    if (res == null) ShimmerCard()
                    else if (s == null) RdCard(Modifier.fillMaxWidth()) {
                        Text(res.hint ?: "Belum ada data statistik. Klik Refresh statistik via Muse, lalu di chat Muse bilang \"refresh statistik sosmed\".", fontSize = 13.sp, color = c.muted)
                    } else Column(verticalArrangement = Arrangement.spacedBy(12.dp)) {
                        val sub = "@${s.username ?: "-"} • ${s.fetchedAt?.let { relativeTime(it) } ?: "belum pernah diambil"}"
                        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            SocialStatCard("Followers", s.followers.toLong(), Icons.Filled.Groups, sub, Modifier.weight(1f))
                            SocialStatCard("Total like", s.totalLikes.toLong(), Icons.Filled.Favorite, sub, Modifier.weight(1f))
                        }
                        Row(horizontalArrangement = Arrangement.spacedBy(12.dp)) {
                            SocialStatCard("Total komen", s.totalComments.toLong(), Icons.Filled.ChatBubble, sub, Modifier.weight(1f))
                            SocialStatCard(if (platform == "instagram") "Reach" else "Views", (if (platform == "instagram") s.reach else s.views).toLong(), Icons.Filled.Visibility, sub, Modifier.weight(1f))
                        }
                    }
                }

                item {
                    RdCard(Modifier.fillMaxWidth()) {
                        Text("Post terbaru", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                        val posts = s?.postList().orEmpty()
                        if (posts.isEmpty()) Text("Belum ada data post. Refresh statistik dulu.", fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 6.dp))
                        posts.take(10).forEach { p ->
                            val sh = RoundedCornerShape(16.dp)
                            Row(Modifier.padding(top = 10.dp).fillMaxWidth().clip(sh).border(2.dp, c.line, sh).padding(12.dp)) {
                                Column(Modifier.weight(1f)) {
                                    Text(p.str("caption", "text") ?: "(tanpa caption)", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = c.ink, maxLines = 2, overflow = TextOverflow.Ellipsis)
                                    Text(relativeTime(p.str("created_at")), fontSize = 11.sp, color = c.muted, modifier = Modifier.padding(top = 2.dp))
                                }
                                Spacer(Modifier.width(10.dp))
                                Column(horizontalAlignment = Alignment.End) {
                                    Text("❤️ ${formatNumber(p.num("likes", "like_count"))}", fontSize = 12.sp, color = c.ink)
                                    Text("💬 ${formatNumber(p.num("comments", "comment_count"))}", fontSize = 12.sp, color = c.ink)
                                    p.str("url")?.let { u -> Text("Lihat post", fontSize = 11.sp, color = Violet, fontWeight = FontWeight.Bold, modifier = Modifier.clickable { open(u) }) }
                                }
                            }
                        }
                    }
                }

                item {
                    RdCard(Modifier.fillMaxWidth()) {
                        Text("Komen terbaru", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                        val comments = s?.commentList().orEmpty()
                        if (comments.isEmpty()) Text("Belum ada data komen. Refresh statistik dulu.", fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 6.dp))
                        comments.take(10).forEach { cm ->
                            val sh = RoundedCornerShape(16.dp)
                            Column(Modifier.padding(top = 10.dp).fillMaxWidth().clip(sh).border(2.dp, c.line, sh).padding(12.dp)) {
                                Row(verticalAlignment = Alignment.CenterVertically) {
                                    Avatar(cm.str("author", "username") ?: "S", 26.dp)
                                    Spacer(Modifier.width(8.dp))
                                    Text(cm.str("author", "username") ?: "Seseorang", fontSize = 13.sp, fontWeight = FontWeight.Bold, color = c.ink, modifier = Modifier.weight(1f))
                                    Text(relativeTime(cm.str("created_at")), fontSize = 11.sp, color = c.muted)
                                }
                                Text(cm.str("text", "comment").orEmpty(), fontSize = 13.sp, color = c.ink, modifier = Modifier.padding(top = 6.dp))
                            }
                        }
                    }
                }

                item {
                    Row(verticalAlignment = Alignment.CenterVertically, modifier = Modifier.padding(top = 4.dp)) {
                        Text("Upload video ke $platformName", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink, modifier = Modifier.weight(1f))
                        RdBadge("Via Muse", "sky", icon = Icons.Filled.Movie)
                    }
                    Text("Pilih video yang sudah punya file. Muse akan upload ke $platformName dengan caption dari Riwayat.", fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 4.dp))
                }
                if (generations.isEmpty()) item { Text("Belum ada video.", fontSize = 13.sp, color = c.muted) }
                items(generations, key = { "p" + it.id }) { g ->
                    VideoRow(g) {
                        GradientButton("Upload ke $platformName", { onPublish(g.id, platform) }, icon = Icons.Filled.CloudUpload, enabled = g.videoUrl != null)
                    }
                }

                item {
                    RdCard(Modifier.fillMaxWidth()) {
                        Text("Riwayat upload", fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 18.sp, color = c.ink)
                        if (publishes.isEmpty()) Text("Belum ada job upload.", fontSize = 13.sp, color = c.muted, modifier = Modifier.padding(top = 6.dp))
                        publishes.take(20).forEach { p ->
                            val sh = RoundedCornerShape(12.dp)
                            Row(
                                Modifier.padding(top = 8.dp).fillMaxWidth().clip(sh).border(1.dp, c.line, sh).padding(horizontal = 12.dp, vertical = 10.dp),
                                verticalAlignment = Alignment.CenterVertically,
                            ) {
                                Column(Modifier.weight(1f)) {
                                    Text("${p.generation?.keyword ?: "Video"} • ${p.platform}", fontSize = 13.sp, fontWeight = FontWeight.SemiBold, color = c.ink)
                                    if (!p.error.isNullOrBlank()) Text(p.error, fontSize = 11.sp, color = tone("rose").fg, maxLines = 2)
                                }
                                RdBadge(p.status, when (p.status) { "completed" -> "emerald"; "failed" -> "rose"; else -> "sky" }, small = true)
                                p.postUrl?.let { u -> Spacer(Modifier.width(8.dp)); Icon(Icons.Filled.OpenInNew, "Lihat post", tint = Violet, modifier = Modifier.size(18.dp).clickable { open(u) }) }
                            }
                        }
                    }
                }
            }
            item { Spacer(Modifier.height(8.dp)) }
        }
    }
}

@Composable
private fun SocialStatCard(label: String, value: Long, icon: ImageVector, sub: String, modifier: Modifier = Modifier) {
    val c = Rd.colors
    RdCard(modifier, padding = 16.dp) {
        Row(verticalAlignment = Alignment.CenterVertically) {
            Icon(icon, null, tint = c.muted, modifier = Modifier.size(16.dp))
            Spacer(Modifier.width(6.dp))
            Text(label, fontSize = 13.sp, color = c.muted)
        }
        Text(formatNumber(value), fontFamily = SpaceGrotesk, fontWeight = FontWeight.Bold, fontSize = 24.sp, color = c.ink, modifier = Modifier.padding(top = 4.dp))
        Text(sub, fontSize = 11.sp, color = c.muted, maxLines = 1, overflow = TextOverflow.Ellipsis)
    }
}

@Composable
private fun VideoRow(g: Generation, actions: @Composable () -> Unit) {
    val c = Rd.colors
    val sh = RoundedCornerShape(16.dp)
    Column(Modifier.fillMaxWidth().clip(sh).background(c.card).border(2.dp, c.line, sh).padding(14.dp)) {
        Text(g.keyword, fontWeight = FontWeight.Bold, fontSize = 15.sp, color = c.ink)
        Row(Modifier.padding(top = 2.dp), verticalAlignment = Alignment.CenterVertically) {
            Text("${g.durasi} dtk • ${modelInfo(g.model).label} • ${relativeTime(g.createdAt)} • ", fontSize = 12.sp, color = c.muted)
            if (g.videoUrl != null) Text("Ada video", fontSize = 12.sp, color = tone("emerald").fg, fontWeight = FontWeight.SemiBold)
            else Text("Belum ada video", fontSize = 12.sp, color = tone("amber").fg, fontWeight = FontWeight.SemiBold)
        }
        Spacer(Modifier.height(10.dp))
        actions()
    }
}
